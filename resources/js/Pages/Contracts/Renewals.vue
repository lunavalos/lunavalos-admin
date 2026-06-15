<script setup>
import { useMoney } from '@/Composables/useMoney';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    contracts: { type: Array, required: true },
    forecast:  { type: Array, default: () => [] },
    kpis:      { type: Object, default: () => ({}) },
});

const { fmt: _fmtMoney } = useMoney();
const fmtMoney = (n, c) => _fmtMoney(n, c);
const fmtDate  = (d) => d ? new Date(d + 'T00:00:00').toLocaleDateString('es-MX') : '—';

const statusColors = {
    none:     'bg-gray-100 text-gray-700',
    pending:  'bg-amber-100 text-amber-800',
    notified: 'bg-blue-100 text-blue-800',
    renewed:  'bg-emerald-100 text-emerald-800',
    declined: 'bg-gray-200 text-gray-600',
    overdue:  'bg-rose-100 text-rose-800',
};

const tab = ref('all'); // all | next_30 | next_90 | overdue
const visibleContracts = computed(() => {
    switch (tab.value) {
        case 'overdue':  return props.contracts.filter(c => c.is_overdue);
        case 'next_30':  return props.contracts.filter(c => c.days_remaining >= 0 && c.days_remaining <= 30);
        case 'next_90':  return props.contracts.filter(c => c.days_remaining >= 0 && c.days_remaining <= 90);
        default:         return props.contracts;
    }
});

const forecastMax = computed(() => Math.max(1, ...props.forecast.map(f => Number(f.amount) || 0)));

const startRenewal = (c) => {
    if (! confirm(`¿Renovar el contrato ${c.contract_number}?\n\nEsto extenderá la vigencia y registrará un pago pendiente.`)) return;
    router.post(route('contracts.renewals.start', c.id), {}, { preserveScroll: true });
};

const sendReceipt = (c) => {
    if (! confirm(`¿Enviar recibo de renovación por correo a ${c.client_email}?`)) return;
    router.post(route('contracts.renewals.send-receipt', c.id), {}, { preserveScroll: true });
};

const declineRenewal = (c) => {
    if (! confirm(`Marcar como NO renovado ${c.contract_number}?`)) return;
    router.post(route('contracts.renewals.decline', c.id), {}, { preserveScroll: true });
};

const runCheck = () => {
    router.post(route('contracts.renewals.check'), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Renovaciones" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Renovaciones de contratos</h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="bg-white rounded-lg shadow p-4">
                        <p class="text-xs text-gray-500 uppercase">Total a vigilar</p>
                        <p class="text-2xl font-bold text-gray-800">{{ kpis.total }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4">
                        <p class="text-xs text-gray-500 uppercase">Próximos 30 días</p>
                        <p class="text-2xl font-bold text-amber-600">{{ kpis.next_30 }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4">
                        <p class="text-xs text-gray-500 uppercase">Notificados</p>
                        <p class="text-2xl font-bold text-blue-600">{{ kpis.notified }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4">
                        <p class="text-xs text-gray-500 uppercase">Vencidos</p>
                        <p class="text-2xl font-bold text-rose-600">{{ kpis.overdue }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4">
                        <p class="text-xs text-gray-500 uppercase">Oportunidad 18m</p>
                        <p class="text-2xl font-bold text-emerald-600">{{ fmtMoney(kpis.forecast_total_18m) }}</p>
                    </div>
                </div>

                <!-- Forecast bar chart -->
                <div v-if="forecast.length" class="bg-white shadow rounded-lg p-5">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-gray-800">Pronóstico de renovaciones por mes</h3>
                        <span class="text-xs text-gray-400">Monto total de contratos cuyo end_date cae en cada mes</span>
                    </div>
                    <div class="flex items-end gap-2 h-44">
                        <div v-for="f in forecast" :key="f.month" class="flex-1 flex flex-col items-center group">
                            <div class="text-[10px] text-gray-500 mb-1 truncate">{{ f.amount ? fmtMoney(f.amount) : '' }}</div>
                            <div class="w-full rounded-t transition-all"
                                 :class="f.amount > 0 ? 'bg-primary group-hover:bg-secondary' : 'bg-gray-100'"
                                 :style="{ height: ((Number(f.amount) || 0) / forecastMax * 100) + '%', minHeight: f.amount > 0 ? '4px' : '0' }">
                            </div>
                            <div class="text-[10px] text-gray-500 mt-1">{{ f.label }}</div>
                            <div v-if="f.count" class="text-[10px] font-bold text-gray-700">{{ f.count }}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white shadow rounded-lg p-4 flex justify-between items-center">
                    <p class="text-sm text-gray-600">
                        El proceso automático corre diariamente a las 08:00. Puedes ejecutarlo manualmente:
                    </p>
                    <button @click="runCheck" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm">
                        Ejecutar revisión ahora
                    </button>
                </div>

                <!-- Tabs -->
                <div class="flex gap-2 border-b border-gray-200">
                    <button v-for="(label, key) in { all: 'Todos', next_30: 'Próx. 30 días', next_90: 'Próx. 90 días', overdue: 'Vencidos' }"
                            :key="key" @click="tab = key"
                            :class="['px-4 py-2 text-sm font-semibold border-b-2 -mb-px transition-colors',
                                     tab === key ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700']">
                        {{ label }}
                    </button>
                </div>

                <div class="bg-white shadow rounded-lg overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-left">Contrato</th>
                                <th class="px-4 py-2 text-left">Cliente</th>
                                <th class="px-4 py-2 text-left">Termina</th>
                                <th class="px-4 py-2 text-right">Días</th>
                                <th class="px-4 py-2 text-right">Monto renovación</th>
                                <th class="px-4 py-2 text-left">Estatus</th>
                                <th class="px-4 py-2 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="c in visibleContracts" :key="c.id" :class="{ 'bg-rose-50/50': c.is_overdue }">
                                <td class="px-4 py-2 font-mono">{{ c.contract_number }}</td>
                                <td class="px-4 py-2">
                                    <div class="font-medium text-gray-800">{{ c.client_name }}</div>
                                    <div class="text-xs text-gray-500">{{ c.client_email }}</div>
                                    <div v-if="c.domain_names" class="text-xs text-indigo-600 font-mono mt-0.5">{{ c.domain_names }}</div>
                                </td>
                                <td class="px-4 py-2">{{ fmtDate(c.end_date) }}</td>
                                <td class="px-4 py-2 text-right" :class="c.is_overdue ? 'text-rose-600 font-semibold' : 'text-gray-700'">
                                    {{ c.days_remaining }}
                                </td>
                                <td class="px-4 py-2 text-right font-mono">{{ fmtMoney(c.total_amount) }}</td>
                                <td class="px-4 py-2">
                                    <span :class="['inline-block px-2 py-0.5 rounded text-xs font-semibold', statusColors[c.renewal_status] || 'bg-gray-100']">
                                        {{ c.renewal_status }}
                                    </span>
                                    <div v-if="c.renewal_last_notified_at" class="text-[10px] text-gray-400 mt-1">
                                        último aviso: {{ new Date(c.renewal_last_notified_at).toLocaleDateString('es-MX') }}
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                                    <button @click="startRenewal(c)" class="text-emerald-700 hover:underline text-xs font-semibold">
                                        Renovar
                                    </button>
                                    <a :href="route('contracts.renewals.receipt', c.id)" target="_blank" class="text-blue-600 hover:underline text-xs">
                                        Ver PDF
                                    </a>
                                    <button @click="sendReceipt(c)" class="text-violet-600 hover:underline text-xs">
                                        Enviar correo
                                    </button>
                                    <button @click="declineRenewal(c)" class="text-gray-500 hover:underline text-xs">
                                        Declinar
                                    </button>
                                    <a v-if="c.token" :href="`/contratodeservicio/${c.token}`" target="_blank" class="text-indigo-600 hover:underline text-xs">
                                        Contrato
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="!visibleContracts.length">
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400">No hay contratos por renovar.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
