<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    DocumentTextIcon,
    BanknotesIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    LinkIcon,
    ArrowPathIcon,
    EyeIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    contracts: Array,
    filters: Object,
    kpis: Object,
});

const fmt = (n) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n) || 0);
const fmtDate = (d) => d ? new Intl.DateTimeFormat('es-MX', { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(d)) : '—';

const search        = ref(props.filters?.search ?? '');
const statusFilter  = ref(props.filters?.status ?? '');
const renewalFilter = ref(props.filters?.renewal_status ?? '');

const apply = () => {
    router.get(route('contracts.index'), {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        renewal_status: renewalFilter.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

let t;
watch(search, () => { clearTimeout(t); t = setTimeout(apply, 350); });
watch([statusFilter, renewalFilter], apply);

const statusBadge = (s) => ({
    'signed':   'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    'pending':  'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    'cancelled':'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
}[s] || 'bg-gray-100 text-gray-800 dark:bg-zinc-800 dark:text-zinc-300');

const renewalBadge = (s) => ({
    'none':     'bg-gray-100 text-gray-700 dark:bg-zinc-800 dark:text-zinc-300',
    'pending':  'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    'notified': 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    'renewed':  'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    'declined': 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
    'overdue':  'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
}[s] || 'bg-gray-100 text-gray-700');

const daysToEnd = (d) => {
    if (!d) return null;
    const end = new Date(d); end.setHours(0,0,0,0);
    const now = new Date(); now.setHours(0,0,0,0);
    return Math.round((end - now) / 86400000);
};

const copyLink = (token) => {
    const url = window.location.origin + '/contratodeservicio/' + token;
    navigator.clipboard.writeText(url).then(() => alert('Enlace copiado: ' + url));
};
</script>

<template>
    <Head title="Contratos" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">Contratos</h2>
                <Link :href="route('contracts.renewals.index')" class="btn bg-primary hover:bg-secondary text-white text-sm font-bold py-2 px-4 rounded inline-flex items-center gap-2">
                    <ArrowPathIcon class="w-4 h-4" /> Ver renovaciones
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="container mx-auto max-w-7xl px-4 space-y-6">
                <!-- KPIs -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-4 border border-gray-100 dark:border-zinc-800">
                        <div class="flex items-center gap-2 text-emerald-600">
                            <CheckCircleIcon class="w-5 h-5" />
                            <span class="text-[11px] uppercase font-bold tracking-wide">Firmados</span>
                        </div>
                        <div class="mt-2 text-2xl font-bold text-gray-800 dark:text-gray-100">{{ fmt(kpis.total_signed_amount) }}</div>
                        <div class="text-xs text-gray-400">{{ kpis.count_signed }} contratos</div>
                    </div>
                    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-4 border border-gray-100 dark:border-zinc-800">
                        <div class="flex items-center gap-2 text-amber-600">
                            <ClockIcon class="w-5 h-5" />
                            <span class="text-[11px] uppercase font-bold tracking-wide">Pendientes</span>
                        </div>
                        <div class="mt-2 text-2xl font-bold text-gray-800 dark:text-gray-100">{{ fmt(kpis.total_pending_amount) }}</div>
                        <div class="text-xs text-gray-400">{{ kpis.count_pending }} contratos</div>
                    </div>
                    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-4 border border-gray-100 dark:border-zinc-800">
                        <div class="flex items-center gap-2 text-primary">
                            <BanknotesIcon class="w-5 h-5" />
                            <span class="text-[11px] uppercase font-bold tracking-wide">MRR activo</span>
                        </div>
                        <div class="mt-2 text-2xl font-bold text-gray-800 dark:text-gray-100">{{ fmt(kpis.mrr) }}</div>
                        <div class="text-xs text-gray-400">Recurrencia mensual</div>
                    </div>
                    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-4 border border-gray-100 dark:border-zinc-800">
                        <div class="flex items-center gap-2 text-blue-600">
                            <ArrowPathIcon class="w-5 h-5" />
                            <span class="text-[11px] uppercase font-bold tracking-wide">Renueva ≤90 días</span>
                        </div>
                        <div class="mt-2 text-2xl font-bold text-gray-800 dark:text-gray-100">{{ kpis.upcoming_90d }}</div>
                        <div class="text-xs text-gray-400">Por gestionar</div>
                    </div>
                    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-4 border border-gray-100 dark:border-zinc-800">
                        <div class="flex items-center gap-2 text-rose-600">
                            <ExclamationTriangleIcon class="w-5 h-5" />
                            <span class="text-[11px] uppercase font-bold tracking-wide">Vencidos</span>
                        </div>
                        <div class="mt-2 text-2xl font-bold text-gray-800 dark:text-gray-100">{{ kpis.overdue }}</div>
                        <div class="text-xs text-gray-400">Sin renovar</div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-4 border border-gray-100 dark:border-zinc-800 flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-[11px] uppercase font-bold text-gray-500 mb-1">Buscar</label>
                        <input type="text" v-model="search" placeholder="No. contrato, razón social, cliente…"
                               class="block w-full border-gray-300 dark:border-zinc-700 dark:bg-zinc-950 rounded-md text-sm" />
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase font-bold text-gray-500 mb-1">Estado</label>
                        <select v-model="statusFilter" class="border-gray-300 dark:border-zinc-700 dark:bg-zinc-950 rounded-md text-sm">
                            <option value="">Todos</option>
                            <option value="pending">Pendiente firma</option>
                            <option value="signed">Firmado</option>
                            <option value="cancelled">Cancelado</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase font-bold text-gray-500 mb-1">Renovación</label>
                        <select v-model="renewalFilter" class="border-gray-300 dark:border-zinc-700 dark:bg-zinc-950 rounded-md text-sm">
                            <option value="">Todas</option>
                            <option value="none">Sin marca</option>
                            <option value="pending">Pendiente</option>
                            <option value="notified">Notificado</option>
                            <option value="renewed">Renovado</option>
                            <option value="declined">Declinado</option>
                            <option value="overdue">Vencido</option>
                        </select>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-zinc-800 text-sm">
                        <thead class="bg-gray-50 dark:bg-zinc-950/40">
                            <tr class="text-left text-[11px] uppercase tracking-wider text-gray-500 dark:text-zinc-400">
                                <th class="px-4 py-3">No.</th>
                                <th class="px-4 py-3">Cliente</th>
                                <th class="px-4 py-3">Inicio</th>
                                <th class="px-4 py-3">Vence</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-right">Mensual</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3">Renovación</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <tr v-if="contracts.length === 0">
                                <td colspan="9" class="text-center py-10 text-gray-400 italic">Sin contratos para mostrar.</td>
                            </tr>
                            <tr v-for="c in contracts" :key="c.id" class="hover:bg-gray-50 dark:hover:bg-zinc-800/40">
                                <td class="px-4 py-3 font-semibold">
                                    <Link :href="route('contracts.admin.show', c.id)"
                                          class="text-primary hover:underline">
                                        {{ c.contract_number || ('#' + c.id) }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-700 dark:text-gray-200">{{ c.client?.name || c.legal_name || '—' }}</div>
                                    <div class="text-xs text-gray-400">{{ c.client?.email || '' }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-zinc-300">{{ fmtDate(c.start_date) }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-600 dark:text-zinc-300">{{ fmtDate(c.end_date) }}</div>
                                    <div v-if="c.end_date" class="text-[11px]" :class="daysToEnd(c.end_date) < 0 ? 'text-rose-600' : (daysToEnd(c.end_date) <= 90 ? 'text-amber-600' : 'text-gray-400')">
                                        <template v-if="daysToEnd(c.end_date) < 0">vencido hace {{ Math.abs(daysToEnd(c.end_date)) }}d</template>
                                        <template v-else>en {{ daysToEnd(c.end_date) }}d</template>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-100">{{ fmt(c.total_amount) }}</td>
                                <td class="px-4 py-3 text-right text-gray-600 dark:text-zinc-300">{{ fmt(c.monthly_amount) }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-[11px] font-semibold" :class="statusBadge(c.status)">{{ c.status }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-[11px] font-semibold" :class="renewalBadge(c.renewal_status)">{{ c.renewal_status || 'none' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <Link :href="route('contracts.admin.show', c.id)"
                                              class="text-gray-500 hover:text-primary hover:bg-primary/10 p-2 rounded-full transition-colors inline-flex items-center" title="Ver detalle">
                                            <EyeIcon class="w-5 h-5" />
                                        </Link>
                                        <a v-if="c.token" :href="'/contratodeservicio/' + c.token" target="_blank"
                                           class="text-blue-600 hover:text-blue-800 hover:bg-blue-50 p-2 rounded-full transition-colors inline-flex items-center" title="Abrir contrato">
                                            <DocumentTextIcon class="w-5 h-5" />
                                        </a>
                                        <button v-if="c.status === 'pending' && c.token" @click="copyLink(c.token)"
                                                class="text-teal-600 hover:text-teal-800 hover:bg-teal-50 p-2 rounded-full transition-colors inline-flex items-center" title="Copiar link de firma">
                                            <LinkIcon class="w-5 h-5" />
                                        </button>
                                        <Link v-if="c.status === 'signed'" :href="route('payments.contract.show', c.id)"
                                              class="text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 p-2 rounded-full transition-colors inline-flex items-center" title="Ver pagos">
                                            <BanknotesIcon class="w-5 h-5" />
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
