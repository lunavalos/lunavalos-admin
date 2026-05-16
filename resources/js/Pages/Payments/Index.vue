<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    contracts: { type: Array,  default: () => [] },
    filters:   { type: Object, default: () => ({}) },
    kpis:      { type: Object, default: () => ({}) },
});

const tab = ref(props.filters.tab || 'pendientes');
const q   = ref(props.filters.q   || '');

const fmtMoney = (n) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n || 0));

const tabs = [
    { key: 'pendientes', label: 'Pendientes' },
    { key: 'pagados',    label: 'Pagados' },
    { key: 'vencidos',   label: 'Vencidos' },
];

const changeTab = (k) => {
    tab.value = k;
    router.get(route('payments.index'), { tab: k, q: q.value }, { preserveScroll: true, preserveState: true });
};

const search = () => {
    router.get(route('payments.index'), { tab: tab.value, q: q.value }, { preserveScroll: true, preserveState: true });
};

const progressColor = (c) => {
    if (c.is_fully_paid)  return 'bg-emerald-500';
    if (c.has_overdue)    return 'bg-rose-500';
    if (c.progress_pct > 0) return 'bg-amber-500';
    return 'bg-gray-300';
};
</script>

<template>
    <Head title="Cobranza" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cobranza</h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- KPIs -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white rounded-lg shadow p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Contratos</p>
                        <p class="text-2xl font-bold text-gray-800 mt-1">{{ kpis.contracts_count }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Ingresos esperados</p>
                        <p class="text-2xl font-bold text-gray-800 mt-1">{{ fmtMoney(kpis.expected_total) }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Cobrado</p>
                        <p class="text-2xl font-bold text-emerald-600 mt-1">{{ fmtMoney(kpis.collected_total) }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Pendiente</p>
                        <p class="text-2xl font-bold text-rose-600 mt-1">{{ fmtMoney(kpis.pending_total) }}</p>
                    </div>
                </div>

                <!-- Tabs + search -->
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <div class="flex gap-2">
                            <button
                                v-for="t in tabs" :key="t.key"
                                @click="changeTab(t.key)"
                                :class="[
                                    'px-4 py-1.5 rounded border text-sm transition',
                                    tab === t.key
                                        ? 'border-indigo-600 text-indigo-700 bg-indigo-50 font-semibold'
                                        : 'border-gray-300 text-gray-600 hover:bg-gray-50'
                                ]"
                            >{{ t.label }}</button>
                        </div>
                        <div class="relative">
                            <input
                                v-model="q"
                                @keyup.enter="search"
                                placeholder="Buscar contrato o cliente…"
                                class="border rounded-full pl-9 pr-4 py-1.5 text-sm w-72"
                            />
                            <span class="absolute left-3 top-1.5 text-gray-400">🔍</span>
                        </div>
                    </div>
                </div>

                <!-- Tabla por contrato -->
                <div class="bg-white shadow rounded-lg overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Contrato</th>
                                <th class="px-4 py-3 text-left">Cliente</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-right">Cobrado</th>
                                <th class="px-4 py-3 text-left w-72">Progreso</th>
                                <th class="px-4 py-3 text-center">Pagos</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="!contracts.length">
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                                    No hay contratos en este filtro.
                                </td>
                            </tr>
                            <tr v-for="c in contracts" :key="c.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ c.contract_number }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-800">{{ c.client?.business_name || '—' }}</div>
                                    <div class="text-xs text-gray-500" v-if="c.client?.contact_name">{{ c.client.contact_name }}</div>
                                </td>
                                <td class="px-4 py-3 text-right font-mono">{{ fmtMoney(c.total_amount) }}</td>
                                <td class="px-4 py-3 text-right font-mono text-emerald-600">{{ fmtMoney(c.collected_amount) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 bg-gray-200 rounded-full h-2 overflow-hidden">
                                            <div :class="['h-2 rounded-full transition-all', progressColor(c)]"
                                                 :style="{ width: Math.min(100, c.progress_pct) + '%' }"></div>
                                        </div>
                                        <span class="text-xs text-gray-600 w-10 text-right">{{ c.progress_pct }}%</span>
                                    </div>
                                    <div v-if="c.has_overdue" class="text-xs text-rose-600 mt-1">
                                        ⚠ {{ c.overdue_payments_count }} pago(s) vencido(s)
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-gray-700">{{ c.paid_payments_count }}</span>
                                    <span class="text-gray-400">/{{ c.payments_count }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="route('payments.contract.show', c.id)"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded border border-gray-300 hover:border-indigo-600 hover:text-indigo-700 text-xs font-semibold"
                                    >
                                        Ver pagos <span aria-hidden>›</span>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
