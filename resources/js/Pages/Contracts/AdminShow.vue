<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    DocumentTextIcon,
    BanknotesIcon,
    UserIcon,
    CalendarDaysIcon,
    ArrowPathIcon,
    ClipboardDocumentIcon,
    CheckCircleIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    BuildingOfficeIcon,
    LinkIcon,
    ArrowTopRightOnSquareIcon,
    PlusIcon,
    PencilSquareIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    contract:         { type: Object, required: true },
    totalPaid:        { type: Number, default: 0 },
    totalPending:     { type: Number, default: 0 },
    daysUntilEnd:     { type: Number, default: null },
    contractServices: { type: Array,  default: () => [] },
    activeCycle:      { type: Object, default: null },
});

const fmt      = (n) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n) || 0);
const fmtDate  = (d) => d ? new Date(d + 'T00:00:00').toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
const fmtDt    = (d) => d ? new Date(d).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

const statusLabel = { signed: 'Firmado', pending: 'Pendiente firma', cancelled: 'Cancelado' };
const statusBadge = (s) => ({
    signed:    'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    pending:   'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    cancelled: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
}[s] || 'bg-gray-100 text-gray-700');

const renewalLabel = { none: 'Sin marca', pending: 'Pendiente', notified: 'Notificado', renewed: 'Renovado', declined: 'Declinado', overdue: 'Vencido' };
const renewalBadge = (s) => ({
    none:     'bg-gray-100 text-gray-700 dark:bg-zinc-800 dark:text-zinc-300',
    pending:  'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    notified: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    renewed:  'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    declined: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
    overdue:  'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
}[s] || 'bg-gray-100 text-gray-700');

const paymentStatusLabel = { programado: 'Programado', registrado: 'Registrado', conciliado: 'Conciliado', facturado: 'Facturado', cancelado: 'Cancelado' };
const paymentStatusBadge = (s) => ({
    programado: 'bg-amber-100 text-amber-800',
    registrado: 'bg-blue-100 text-blue-800',
    conciliado: 'bg-emerald-100 text-emerald-800',
    facturado:  'bg-purple-100 text-purple-800',
    cancelado:  'bg-gray-200 text-gray-600',
}[s] || 'bg-gray-100 text-gray-700');

const typeLabel = { anticipo: 'Anticipo', mensualidad: 'Mensualidad', pago_unico: 'Pago único', ajuste: 'Ajuste', reembolso: 'Reembolso' };

const contractUrl = (token) => window.location.origin + '/contratodeservicio/' + token;

const copyLink = (token) => {
    navigator.clipboard.writeText(contractUrl(token))
        .then(() => alert('Enlace copiado: ' + contractUrl(token)));
};

const remainingLabel = () => {
    if (props.daysUntilEnd === null) return null;
    if (props.daysUntilEnd < 0)  return `Venció hace ${Math.abs(props.daysUntilEnd)} días`;
    if (props.daysUntilEnd === 0) return 'Vence hoy';
    return `Vence en ${props.daysUntilEnd} días`;
};

const remainingColor = () => {
    if (props.daysUntilEnd === null) return 'text-gray-400';
    if (props.daysUntilEnd < 0)    return 'text-rose-600';
    if (props.daysUntilEnd <= 30)  return 'text-rose-500';
    if (props.daysUntilEnd <= 90)  return 'text-amber-600';
    return 'text-emerald-600';
};

const c = props.contract;

/* -------------------------------------------------------------------- */
/* Entregables recurrentes (módulo Clientes Recurrentes)                */
/* -------------------------------------------------------------------- */
const showServiceModal = ref(false);
const editingServiceId = ref(null);

const serviceForm = useForm({
    name: '',
    prefix: '',
    color: '',
    quantity_per_cycle: 0,
    unit_type: 'fixed',
    rollover_allowed: false,
    auto_create_tickets: true,
    sort_order: 0,
});

const unitTypeLabels = {
    fixed: 'Cuota fija (auto-genera tickets)',
    on_demand_pool: 'Bolsa on-demand',
    unlimited: 'Ilimitado',
};

const creditsByServiceId = computed(() => {
    const map = {};
    if (props.activeCycle?.credits) {
        props.activeCycle.credits.forEach(cr => { map[cr.contract_service_id] = cr; });
    }
    return map;
});

function openNewServiceModal() {
    editingServiceId.value = null;
    serviceForm.reset();
    serviceForm.unit_type = 'fixed';
    serviceForm.auto_create_tickets = true;
    serviceForm.sort_order = (props.contractServices?.length || 0) + 1;
    showServiceModal.value = true;
}

function openEditServiceModal(svc) {
    editingServiceId.value = svc.id;
    serviceForm.name = svc.name;
    serviceForm.prefix = svc.prefix;
    serviceForm.color = svc.color || '';
    serviceForm.quantity_per_cycle = svc.quantity_per_cycle;
    serviceForm.unit_type = svc.unit_type;
    serviceForm.rollover_allowed = !!svc.rollover_allowed;
    serviceForm.auto_create_tickets = !!svc.auto_create_tickets;
    serviceForm.sort_order = svc.sort_order;
    showServiceModal.value = true;
}

function submitService() {
    const onDone = () => { showServiceModal.value = false; serviceForm.reset(); };
    if (editingServiceId.value) {
        serviceForm.put(route('contracts.services.update', [c.id, editingServiceId.value]), { onSuccess: onDone });
    } else {
        serviceForm.post(route('contracts.services.store', c.id), { onSuccess: onDone });
    }
}

function deleteService(svc) {
    if (!confirm(`¿Eliminar el entregable "${svc.name}"?`)) return;
    router.delete(route('contracts.services.destroy', [c.id, svc.id]));
}

function openCurrentCycle() {
    if (!confirm('¿Abrir el ciclo del mes actual? Esto pre-cargará los tickets fijos en el backlog.')) return;
    router.post(route('contracts.openCycle', c.id));
}
</script>

<template>
    <Head :title="`Contrato ${c.contract_number || '#' + c.id}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link :href="route('contracts.index')"
                          class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                        ← Contratos
                    </Link>
                    <span class="text-gray-300 dark:text-zinc-600">/</span>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                        {{ c.contract_number || ('#' + c.id) }}
                    </h2>
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wide"
                          :class="statusBadge(c.status)">
                        {{ statusLabel[c.status] || c.status }}
                    </span>
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold"
                          :class="renewalBadge(c.renewal_status)">
                        {{ renewalLabel[c.renewal_status] || c.renewal_status || 'none' }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <a v-if="c.token" :href="contractUrl(c.token)" target="_blank"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-zinc-700 text-sm text-gray-600 dark:text-zinc-300 hover:border-primary hover:text-primary transition-colors">
                        <ArrowTopRightOnSquareIcon class="w-4 h-4" /> Ver contrato
                    </a>
                    <button v-if="c.token && c.status === 'pending'" @click="copyLink(c.token)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-zinc-700 text-sm text-teal-600 hover:border-teal-500 hover:bg-teal-50 transition-colors">
                        <LinkIcon class="w-4 h-4" /> Copiar link
                    </button>
                    <Link v-if="c.status === 'signed'" :href="route('payments.contract.show', c.id)"
                          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary hover:bg-secondary text-white text-sm font-semibold transition-colors">
                        <BanknotesIcon class="w-4 h-4" /> Gestionar pagos
                    </Link>
                    <Link :href="route('contracts.renewals.index')"
                          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-zinc-700 text-sm text-blue-600 hover:border-blue-500 hover:bg-blue-50 transition-colors">
                        <ArrowPathIcon class="w-4 h-4" /> Renovaciones
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="container mx-auto max-w-6xl px-4 space-y-6">

                <!-- KPI strip -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-4">
                        <div class="flex items-center gap-2 text-emerald-600 mb-2">
                            <CheckCircleIcon class="w-5 h-5" />
                            <span class="text-[11px] uppercase font-bold tracking-wide">Total contrato</span>
                        </div>
                        <div class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ fmt(c.total_amount) }}</div>
                        <div class="text-xs text-gray-400 mt-0.5">{{ c.payment_plan_months }} meses</div>
                    </div>
                    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-4">
                        <div class="flex items-center gap-2 text-primary mb-2">
                            <BanknotesIcon class="w-5 h-5" />
                            <span class="text-[11px] uppercase font-bold tracking-wide">Mensualidad</span>
                        </div>
                        <div class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ fmt(c.monthly_amount) }}</div>
                        <div class="text-xs text-gray-400 mt-0.5">Anticipo: {{ fmt(c.anticipo_amount) }}</div>
                    </div>
                    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-4">
                        <div class="flex items-center gap-2 text-blue-600 mb-2">
                            <ClipboardDocumentIcon class="w-5 h-5" />
                            <span class="text-[11px] uppercase font-bold tracking-wide">Cobrado</span>
                        </div>
                        <div class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ fmt(totalPaid) }}</div>
                        <div class="text-xs text-gray-400 mt-0.5">Pendiente: {{ fmt(totalPending) }}</div>
                    </div>
                    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-4">
                        <div class="flex items-center gap-2 mb-2" :class="remainingColor()">
                            <CalendarDaysIcon class="w-5 h-5" />
                            <span class="text-[11px] uppercase font-bold tracking-wide">Vigencia</span>
                        </div>
                        <div class="text-lg font-bold text-gray-800 dark:text-gray-100">{{ fmtDate(c.end_date) }}</div>
                        <div class="text-xs mt-0.5 font-medium" :class="remainingColor()">{{ remainingLabel() }}</div>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-6">

                    <!-- Datos del cliente / firmante -->
                    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-5 space-y-4">
                        <div class="flex items-center gap-2 border-b border-gray-100 dark:border-zinc-800 pb-3">
                            <UserIcon class="w-5 h-5 text-primary" />
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100">Cliente / Firmante</h3>
                        </div>
                        <dl class="space-y-2 text-sm">
                            <div class="flex gap-2">
                                <dt class="text-gray-400 w-36 shrink-0">Cliente CRM</dt>
                                <dd class="text-gray-700 dark:text-gray-200 font-medium">
                                    <Link v-if="c.client" :href="route('clients.show', c.client.id)"
                                          class="text-primary hover:underline">
                                        {{ c.client.name }}
                                    </Link>
                                    <span v-else>—</span>
                                    <span v-if="c.client?.email" class="text-gray-400 ml-1">· {{ c.client.email }}</span>
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="text-gray-400 w-36 shrink-0">Razón social</dt>
                                <dd class="text-gray-700 dark:text-gray-200">{{ c.legal_name || '—' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="text-gray-400 w-36 shrink-0">RFC</dt>
                                <dd class="text-gray-700 dark:text-gray-200 font-mono">{{ c.tax_id || '—' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="text-gray-400 w-36 shrink-0">Dirección fiscal</dt>
                                <dd class="text-gray-700 dark:text-gray-200">{{ c.fiscal_address || '—' }}<span v-if="c.postal_code" class="text-gray-400"> C.P. {{ c.postal_code }}</span></dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="text-gray-400 w-36 shrink-0">Representante</dt>
                                <dd class="text-gray-700 dark:text-gray-200">{{ c.legal_representative || '—' }}</dd>
                            </div>
                            <div v-if="c.signature_ip" class="flex gap-2">
                                <dt class="text-gray-400 w-36 shrink-0">IP de firma</dt>
                                <dd class="font-mono text-gray-700 dark:text-gray-200">{{ c.signature_ip }}</dd>
                            </div>
                            <div v-if="c.signed_at" class="flex gap-2">
                                <dt class="text-gray-400 w-36 shrink-0">Firmado el</dt>
                                <dd class="text-gray-700 dark:text-gray-200">{{ fmtDt(c.signed_at) }}</dd>
                            </div>
                            <div v-if="c.csf_file_path" class="flex gap-2">
                                <dt class="text-gray-400 w-36 shrink-0">CSF</dt>
                                <dd>
                                    <a :href="'/storage/' + c.csf_file_path" target="_blank"
                                       class="text-primary hover:underline text-sm inline-flex items-center gap-1">
                                        <DocumentTextIcon class="w-4 h-4" /> Ver documento
                                    </a>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Datos del contrato -->
                    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-5 space-y-4">
                        <div class="flex items-center gap-2 border-b border-gray-100 dark:border-zinc-800 pb-3">
                            <DocumentTextIcon class="w-5 h-5 text-primary" />
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100">Datos del contrato</h3>
                        </div>
                        <dl class="space-y-2 text-sm">
                            <div class="flex gap-2">
                                <dt class="text-gray-400 w-36 shrink-0">No. contrato</dt>
                                <dd class="font-mono font-semibold text-gray-800 dark:text-gray-100">{{ c.contract_number || ('ID ' + c.id) }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="text-gray-400 w-36 shrink-0">Vigencia</dt>
                                <dd class="text-gray-700 dark:text-gray-200">{{ fmtDate(c.start_date) }} — {{ fmtDate(c.end_date) }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="text-gray-400 w-36 shrink-0">Plan de pago</dt>
                                <dd class="text-gray-700 dark:text-gray-200">{{ c.payment_plan_months }} meses</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="text-gray-400 w-36 shrink-0">Subtotal</dt>
                                <dd class="text-gray-700 dark:text-gray-200">{{ fmt(c.subtotal) }}</dd>
                            </div>
                            <div v-if="Number(c.discount_amount) > 0" class="flex gap-2">
                                <dt class="text-gray-400 w-36 shrink-0">Descuento</dt>
                                <dd class="text-rose-600">– {{ fmt(c.discount_amount) }}</dd>
                            </div>
                            <div v-if="Number(c.iva_amount) > 0" class="flex gap-2">
                                <dt class="text-gray-400 w-36 shrink-0">IVA</dt>
                                <dd class="text-gray-700 dark:text-gray-200">{{ fmt(c.iva_amount) }}</dd>
                            </div>
                            <div v-if="Number(c.retentions_total) > 0" class="flex gap-2">
                                <dt class="text-gray-400 w-36 shrink-0">Retenciones</dt>
                                <dd class="text-rose-600">– {{ fmt(c.retentions_total) }}</dd>
                            </div>
                            <div class="flex gap-2 pt-1 border-t border-gray-100 dark:border-zinc-800">
                                <dt class="text-gray-400 w-36 shrink-0 font-semibold">Total</dt>
                                <dd class="text-gray-800 dark:text-gray-100 font-bold text-base">{{ fmt(c.total_amount) }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="text-gray-400 w-36 shrink-0">Mensualidad</dt>
                                <dd class="text-gray-700 dark:text-gray-200">{{ fmt(c.monthly_amount) }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="text-gray-400 w-36 shrink-0">Anticipo</dt>
                                <dd class="text-gray-700 dark:text-gray-200">{{ fmt(c.anticipo_amount) }}</dd>
                            </div>
                            <div class="flex gap-2 pt-1 border-t border-gray-100 dark:border-zinc-800">
                                <dt class="text-gray-400 w-36 shrink-0">Creado por</dt>
                                <dd class="text-gray-700 dark:text-gray-200">{{ c.created_by?.name || '—' }}</dd>
                            </div>
                            <div v-if="c.renewed_contract" class="flex gap-2">
                                <dt class="text-gray-400 w-36 shrink-0">Renovado en</dt>
                                <dd>
                                    <Link :href="route('contracts.admin.show', c.renewed_contract.id)"
                                          class="text-primary hover:underline">
                                        {{ c.renewed_contract.contract_number }}
                                    </Link>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Cotización origen -->
                <div v-if="c.quote" class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-5">
                    <div class="flex items-center justify-between border-b border-gray-100 dark:border-zinc-800 pb-3 mb-4">
                        <div class="flex items-center gap-2">
                            <ClipboardDocumentIcon class="w-5 h-5 text-primary" />
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100">Cotización origen</h3>
                        </div>
                        <a :href="route('quotes.show', c.quote.id)" target="_blank"
                           class="text-sm text-primary hover:underline inline-flex items-center gap-1">
                            Ver cotización PDF <ArrowTopRightOnSquareIcon class="w-3.5 h-3.5" />
                        </a>
                    </div>
                    <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <dt class="text-gray-400 text-[11px] uppercase font-bold tracking-wide">ID</dt>
                            <dd class="font-mono font-semibold text-gray-800 dark:text-gray-100">#{{ c.quote.id }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-400 text-[11px] uppercase font-bold tracking-wide">Cliente (cotización)</dt>
                            <dd class="text-gray-700 dark:text-gray-200">{{ c.quote.client_name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-400 text-[11px] uppercase font-bold tracking-wide">Total cotizado</dt>
                            <dd class="font-semibold text-gray-800 dark:text-gray-100">{{ fmt(c.quote.total) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-400 text-[11px] uppercase font-bold tracking-wide">Estado</dt>
                            <dd>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                                    {{ c.quote.status }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Historial de pagos -->
                <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-zinc-800">
                        <div class="flex items-center gap-2">
                            <BanknotesIcon class="w-5 h-5 text-primary" />
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100">Pagos</h3>
                            <span class="text-xs text-gray-400">({{ contract.payments?.length ?? 0 }} registros)</span>
                        </div>
                        <Link v-if="c.status === 'signed'" :href="route('payments.contract.show', c.id)"
                              class="text-sm text-primary hover:underline inline-flex items-center gap-1">
                            Gestionar pagos <ArrowTopRightOnSquareIcon class="w-3.5 h-3.5" />
                        </Link>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-zinc-800 text-sm">
                            <thead class="bg-gray-50 dark:bg-zinc-950/40">
                                <tr class="text-left text-[11px] uppercase tracking-wider text-gray-500 dark:text-zinc-400">
                                    <th class="px-4 py-3">Concepto</th>
                                    <th class="px-4 py-3">Tipo</th>
                                    <th class="px-4 py-3">Vencimiento</th>
                                    <th class="px-4 py-3">Pagado el</th>
                                    <th class="px-4 py-3 text-right">Monto</th>
                                    <th class="px-4 py-3">Estado</th>
                                    <th class="px-4 py-3">Método</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-if="!contract.payments?.length">
                                    <td colspan="7" class="text-center py-8 text-gray-400 italic">Sin pagos registrados.</td>
                                </tr>
                                <tr v-for="p in contract.payments" :key="p.id"
                                    class="hover:bg-gray-50 dark:hover:bg-zinc-800/40">
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ p.concept || '—' }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ typeLabel[p.type] || p.type }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-zinc-300">{{ fmtDate(p.due_date) }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-zinc-300">{{ fmtDate(p.paid_at) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-800 dark:text-gray-100">{{ fmt(p.amount) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold"
                                              :class="paymentStatusBadge(p.status)">
                                            {{ paymentStatusLabel[p.status] || p.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 capitalize">{{ p.payment_method || '—' }}</td>
                                </tr>
                            </tbody>
                            <tfoot v-if="contract.payments?.length" class="bg-gray-50 dark:bg-zinc-950/40 text-sm font-semibold">
                                <tr>
                                    <td colspan="4" class="px-4 py-3 text-gray-500 text-right">Total cobrado</td>
                                    <td class="px-4 py-3 text-right text-emerald-700">{{ fmt(totalPaid) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                                <tr v-if="totalPending > 0">
                                    <td colspan="4" class="px-4 py-3 text-gray-500 text-right">Pendiente</td>
                                    <td class="px-4 py-3 text-right text-amber-600">{{ fmt(totalPending) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Entregables Recurrentes (módulo Clientes Recurrentes) -->
                <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-5">
                    <div class="flex items-center justify-between border-b border-gray-100 dark:border-zinc-800 pb-3 mb-4">
                        <div class="flex items-center gap-2">
                            <ArrowPathIcon class="w-5 h-5 text-primary" />
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100">Entregables Recurrentes</h3>
                            <span v-if="activeCycle"
                                  class="ml-2 inline-flex items-center gap-1 rounded-full bg-blue-50 dark:bg-blue-900/30 px-2 py-0.5 text-[10px] font-medium text-blue-700 dark:text-blue-300">
                                Ciclo activo: {{ activeCycle.label }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button v-if="contractServices.length && !activeCycle && c.status === 'signed'"
                                @click="openCurrentCycle"
                                class="inline-flex items-center gap-1 rounded-md bg-emerald-600 hover:bg-emerald-700 px-3 py-1.5 text-xs font-medium text-white">
                                <ArrowPathIcon class="w-4 h-4" /> Abrir ciclo del mes
                            </button>
                            <Link v-if="activeCycle && c.client"
                                :href="route('recurring.clients.show', c.client.id)"
                                class="inline-flex items-center gap-1 rounded-md border border-gray-200 dark:border-zinc-700 px-3 py-1.5 text-xs font-medium text-blue-600 hover:bg-blue-50">
                                <ArrowTopRightOnSquareIcon class="w-4 h-4" /> Ver tablero
                            </Link>
                            <button @click="openNewServiceModal"
                                class="inline-flex items-center gap-1 rounded-md bg-primary hover:bg-secondary px-3 py-1.5 text-xs font-medium text-white">
                                <PlusIcon class="w-4 h-4" /> Agregar
                            </button>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-zinc-400 mb-3">
                        Define la cuota mensual de entregables que se generarán automáticamente cada ciclo
                        (ej. 8 Reels, 4 Blogs). Los tipos <strong>Cuota fija</strong> pre-cargan tickets;
                        las <strong>Bolsas on-demand</strong> permiten crear tickets sueltos que descuentan créditos.
                    </p>

                    <div v-if="!contractServices.length"
                        class="rounded-lg border border-dashed border-gray-300 dark:border-zinc-700 p-8 text-center">
                        <p class="text-sm text-gray-500 dark:text-zinc-400">
                            Aún no hay entregables recurrentes configurados.
                        </p>
                        <button @click="openNewServiceModal"
                            class="mt-3 inline-flex items-center gap-1 rounded-md bg-primary hover:bg-secondary px-3 py-1.5 text-xs font-medium text-white">
                            <PlusIcon class="w-4 h-4" /> Agregar primer entregable
                        </button>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-zinc-950/40 text-left text-xs text-gray-500 uppercase tracking-wide">
                                <tr>
                                    <th class="px-4 py-2">Prefijo</th>
                                    <th class="px-4 py-2">Nombre</th>
                                    <th class="px-4 py-2">Tipo</th>
                                    <th class="px-4 py-2 text-right">Cuota</th>
                                    <th class="px-4 py-2">Rollover</th>
                                    <th class="px-4 py-2">Auto-tickets</th>
                                    <th class="px-4 py-2 text-right">Consumo ciclo</th>
                                    <th class="px-4 py-2 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-for="svc in contractServices" :key="svc.id" class="hover:bg-gray-50 dark:hover:bg-zinc-800/40">
                                    <td class="px-4 py-2 font-mono text-xs">
                                        <span class="inline-block rounded bg-gray-100 dark:bg-zinc-700 px-2 py-0.5">{{ svc.prefix }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-800 dark:text-gray-100">{{ svc.name }}</td>
                                    <td class="px-4 py-2 text-gray-600 dark:text-zinc-300">{{ unitTypeLabels[svc.unit_type] }}</td>
                                    <td class="px-4 py-2 text-right font-semibold text-gray-800 dark:text-gray-100">
                                        <template v-if="svc.unit_type === 'unlimited'">∞</template>
                                        <template v-else>{{ svc.quantity_per_cycle }}</template>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span :class="svc.rollover_allowed ? 'text-emerald-600' : 'text-gray-400'">
                                            {{ svc.rollover_allowed ? 'Sí' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span :class="svc.auto_create_tickets ? 'text-emerald-600' : 'text-gray-400'">
                                            {{ svc.auto_create_tickets ? 'Sí' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-right text-gray-600 dark:text-zinc-300">
                                        <template v-if="creditsByServiceId[svc.id]">
                                            <span v-if="creditsByServiceId[svc.id].is_unlimited">
                                                {{ creditsByServiceId[svc.id].consumed }} / ∞
                                            </span>
                                            <span v-else>
                                                {{ creditsByServiceId[svc.id].consumed }} /
                                                {{ creditsByServiceId[svc.id].total + creditsByServiceId[svc.id].rolled_over }}
                                            </span>
                                        </template>
                                        <span v-else class="text-gray-300">—</span>
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <button @click="openEditServiceModal(svc)"
                                            class="inline-flex items-center p-1 text-gray-500 hover:text-primary" title="Editar">
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </button>
                                        <button @click="deleteService(svc)"
                                            class="inline-flex items-center p-1 text-gray-500 hover:text-rose-600" title="Eliminar">
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Notas -->
                <div v-if="c.notes" class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-5">
                    <div class="flex items-center gap-2 border-b border-gray-100 dark:border-zinc-800 pb-3 mb-3">
                        <ClipboardDocumentIcon class="w-5 h-5 text-primary" />
                        <h3 class="font-semibold text-gray-800 dark:text-gray-100">Notas internas</h3>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-zinc-300 whitespace-pre-line">{{ c.notes }}</p>
                </div>

            </div>
        </div>

        <!-- Modal: crear/editar entregable recurrente -->
        <div v-if="showServiceModal"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
             @click.self="showServiceModal = false">
            <div class="w-full max-w-lg rounded-xl bg-white dark:bg-zinc-900 shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-zinc-800 px-5 py-3">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100">
                        {{ editingServiceId ? 'Editar entregable' : 'Nuevo entregable recurrente' }}
                    </h3>
                    <button @click="showServiceModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>
                <form @submit.prevent="submitService" class="p-5 space-y-4">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-600 dark:text-zinc-300 mb-1">Nombre</label>
                            <input v-model="serviceForm.name" type="text" required
                                class="w-full rounded-md border border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm" />
                            <p v-if="serviceForm.errors.name" class="text-xs text-rose-600 mt-1">{{ serviceForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-zinc-300 mb-1">Prefijo</label>
                            <input v-model="serviceForm.prefix" type="text" maxlength="8" required
                                placeholder="R, B, EM…"
                                class="w-full rounded-md border border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm font-mono uppercase" />
                            <p v-if="serviceForm.errors.prefix" class="text-xs text-rose-600 mt-1">{{ serviceForm.errors.prefix }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-zinc-300 mb-1">Tipo de unidad</label>
                            <select v-model="serviceForm.unit_type"
                                class="w-full rounded-md border border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm">
                                <option value="fixed">Cuota fija</option>
                                <option value="on_demand_pool">Bolsa on-demand</option>
                                <option value="unlimited">Ilimitado</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-zinc-300 mb-1">
                                Cuota por ciclo
                                <span v-if="serviceForm.unit_type === 'unlimited'" class="text-gray-400">(ignorado)</span>
                            </label>
                            <input v-model.number="serviceForm.quantity_per_cycle" type="number" min="0" max="999"
                                :disabled="serviceForm.unit_type === 'unlimited'"
                                class="w-full rounded-md border border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm disabled:opacity-50" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-zinc-300">
                            <input v-model="serviceForm.rollover_allowed" type="checkbox"
                                class="rounded border-gray-300 text-primary focus:ring-primary" />
                            Permitir rollover de créditos
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-zinc-300">
                            <input v-model="serviceForm.auto_create_tickets" type="checkbox"
                                :disabled="serviceForm.unit_type !== 'fixed'"
                                class="rounded border-gray-300 text-primary focus:ring-primary disabled:opacity-50" />
                            Auto-crear tickets al abrir ciclo
                        </label>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-zinc-300 mb-1">Color (opcional)</label>
                            <input v-model="serviceForm.color" type="text" placeholder="#264ab3"
                                class="w-full rounded-md border border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-zinc-300 mb-1">Orden</label>
                            <input v-model.number="serviceForm.sort_order" type="number"
                                class="w-full rounded-md border border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-gray-100 dark:border-zinc-800">
                        <button type="button" @click="showServiceModal = false"
                            class="px-3 py-1.5 rounded-md text-sm text-gray-600 hover:bg-gray-100 dark:hover:bg-zinc-800">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="serviceForm.processing"
                            class="px-4 py-1.5 rounded-md bg-primary hover:bg-secondary text-white text-sm font-medium disabled:opacity-50">
                            {{ editingServiceId ? 'Guardar cambios' : 'Crear entregable' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
