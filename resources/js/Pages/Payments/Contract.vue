<script setup>
import { useMoney } from '@/Composables/useMoney';
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    contract:       { type: Object, required: true },
    payments:       { type: Array,  default: () => [] },
    invoices:       { type: Array,  default: () => [] },
    totals:         { type: Object, default: () => ({}) },
    paymentMethods: { type: Array,  default: () => [] },
    types:          { type: Array,  default: () => [] },
});

const { fmt: _fmtMoney } = useMoney();
const fmtMoney = (n, c) => _fmtMoney(n, c);
const fmtDate  = (d) => d ? new Date(d + 'T00:00:00').toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';

const statusColors = {
    programado: 'bg-amber-100 text-amber-800',
    registrado: 'bg-blue-100 text-blue-800',
    conciliado: 'bg-emerald-100 text-emerald-800',
    facturado:  'bg-purple-100 text-purple-800',
    cancelado:  'bg-gray-200 text-gray-600',
};

const methodLabel = (m) => ({
    efectivo: 'Efectivo',
    cheque: 'Cheque',
    transferencia: 'Transferencia',
    tarjeta: 'Tarjeta',
}[m] || m || '—');

// Invoice por payment_id (para mostrar UUID en el mismo row).
const invoiceByPaymentId = computed(() => {
    const map = {};
    for (const inv of props.invoices) {
        if (inv.client_payment_id) map[inv.client_payment_id] = inv;
    }
    return map;
});

// ----- Modal Registrar Pago -----
const showCreate = ref(false);
const createForm = useForm({
    client_id:      props.contract.client?.id ?? '',
    contract_id:    props.contract.id,
    type:           'pago_unico',
    concept:        '',
    amount:         '',
    currency:       'MXN',
    payment_method: 'transferencia',
    reference:      '',
    paid_at:        new Date().toISOString().slice(0, 10),
    evidence_file:  null,
    notes:          '',
});

const openCreate = () => {
    createForm.reset();
    createForm.client_id      = props.contract.client?.id ?? '';
    createForm.contract_id    = props.contract.id;
    createForm.payment_method = 'transferencia';
    createForm.type           = 'pago_unico';
    createForm.paid_at        = new Date().toISOString().slice(0, 10);
    showCreate.value = true;
};

const submitCreate = () => {
    createForm.post(route('payments.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { showCreate.value = false; },
    });
};

// ----- Modal Cobrar (settle programado) -----
const settleTarget = ref(null);
const settleForm = useForm({
    amount:         '',
    payment_method: 'transferencia',
    reference:      '',
    paid_at:        new Date().toISOString().slice(0, 10),
    evidence_file:  null,
    notes:          '',
});

const openSettle = (p) => {
    settleTarget.value = p;
    settleForm.reset();
    settleForm.amount         = p.amount;
    settleForm.payment_method = 'transferencia';
    settleForm.paid_at        = new Date().toISOString().slice(0, 10);
};

const submitSettle = () => {
    settleForm.post(route('payments.settle', settleTarget.value.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { settleTarget.value = null; },
    });
};

const cancelPayment = (p) => {
    if (! confirm(`¿Cancelar pago «${p.concept}»?`)) return;
    router.post(route('payments.cancel', p.id), {}, { preserveScroll: true });
};

// El CFDI sólo se permite cuando el contrato/pago está en MXN. Facturama
// opera únicamente en MXN; las cobranzas en USD u otras divisas se manejan
// con el recibo de pago / solicitud de cobro.
const canInvoice = (p) => {
    const cur = (p?.currency || props.contract.currency || 'MXN').toUpperCase();
    if (cur !== 'MXN') return false;
    return ['programado', 'registrado', 'conciliado'].includes(p.status);
};

const issueInvoice = (p) => {
    if (! canInvoice(p)) return;
    const msg = p.status === 'programado'
        ? `¿Emitir CFDI por adelantado para «${p.concept}»? El pago quedará marcado como facturado; cuando recibas el depósito podrás registrarlo y conciliarlo.`
        : `¿Emitir CFDI para «${p.concept}»? El cliente debe tener RFC, código postal y régimen fiscal capturados.`;
    if (! confirm(msg)) return;
    router.post(route('invoices.issueForPayment', p.id), {}, { preserveScroll: true });
};

// Solicitud de pago / recibo (PDF en la moneda original del pago).
const paymentReceiptUrl = (p) => route('payments.receipt', p.id);
</script>

<template>
    <Head :title="`Pagos: ${contract.contract_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('payments.index')" class="text-gray-500 hover:text-indigo-600 text-sm">‹ Cobranza</Link>
                <h2 class="font-semibold text-xl text-gray-800">Pagos: {{ contract.contract_number }}</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Columna izquierda -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Resumen del contrato -->
                    <div class="bg-white rounded-lg shadow p-5">
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Total contrato</p>
                                <p class="text-xl font-bold text-gray-800">{{ fmtMoney(totals.total) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Cobrado</p>
                                <p class="text-xl font-bold text-emerald-600">{{ fmtMoney(totals.collected) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Saldo pendiente</p>
                                <p class="text-xl font-bold text-rose-600">{{ fmtMoney(totals.pending) }}</p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="bg-gray-200 rounded-full h-2 overflow-hidden">
                                <div class="bg-rose-500 h-2 rounded-full" :style="{ width: Math.min(100, totals.progress) + '%' }"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1 text-right">{{ totals.progress }}%</p>
                        </div>
                    </div>

                    <!-- Historial de Pagos -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="flex items-center justify-between p-5 pb-3">
                            <h3 class="font-semibold text-gray-800">Historial de Pagos</h3>
                            <button
                                @click="openCreate"
                                class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-1.5 rounded text-sm font-semibold inline-flex items-center gap-1"
                            >
                                <span class="text-lg leading-none">+</span> Registrar Pago
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                    <tr>
                                        <th class="px-4 py-2 text-left">#</th>
                                        <th class="px-4 py-2 text-left">Concepto</th>
                                        <th class="px-4 py-2 text-left">Vence</th>
                                        <th class="px-4 py-2 text-left">Pagado</th>
                                        <th class="px-4 py-2 text-right">Monto</th>
                                        <th class="px-4 py-2 text-left">Método</th>
                                        <th class="px-4 py-2 text-left">Estatus</th>
                                        <th class="px-4 py-2 text-left">CFDI</th>
                                        <th class="px-4 py-2 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-if="!payments.length">
                                        <td colspan="9" class="px-4 py-8 text-center text-gray-400">Sin pagos registrados.</td>
                                    </tr>
                                    <tr v-for="(p, idx) in payments" :key="p.id">
                                        <td class="px-4 py-2 text-gray-400">{{ idx + 1 }}</td>
                                        <td class="px-4 py-2">
                                            <div class="font-medium text-gray-800">{{ p.concept }}</div>
                                            <div class="text-xs text-gray-500" v-if="p.reference">Ref: {{ p.reference }}</div>
                                        </td>
                                        <td class="px-4 py-2 text-gray-600">{{ fmtDate(p.due_date) }}</td>
                                        <td class="px-4 py-2 text-gray-600">{{ fmtDate(p.paid_at) }}</td>
                                        <td class="px-4 py-2 text-right font-mono text-emerald-700">{{ fmtMoney(p.amount) }}</td>
                                        <td class="px-4 py-2 text-gray-600">
                                            <span class="inline-block px-2 py-0.5 rounded bg-gray-100 text-xs">{{ methodLabel(p.payment_method) }}</span>
                                        </td>
                                        <td class="px-4 py-2">
                                            <span :class="['inline-block px-2 py-0.5 rounded text-xs font-semibold', statusColors[p.status] || 'bg-gray-100']">
                                                {{ p.status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-xs">
                                            <template v-if="invoiceByPaymentId[p.id]">
                                                <div class="font-mono text-purple-700">{{ invoiceByPaymentId[p.id].folio || invoiceByPaymentId[p.id].uuid?.slice(0,8) }}</div>
                                                <div class="flex gap-1 mt-0.5">
                                                    <a v-if="invoiceByPaymentId[p.id].pdf_path"
                                                       :href="route('invoices.download', { invoice: invoiceByPaymentId[p.id].id, type: 'pdf' })"
                                                       class="text-rose-600 hover:underline">PDF</a>
                                                    <a v-if="invoiceByPaymentId[p.id].xml_path"
                                                       :href="route('invoices.download', { invoice: invoiceByPaymentId[p.id].id, type: 'xml' })"
                                                       class="text-blue-600 hover:underline">XML</a>
                                                </div>
                                            </template>
                                            <span v-else class="text-gray-400">—</span>
                                        </td>
                                        <td class="px-4 py-2 text-right whitespace-nowrap space-x-2">
                                            <button v-if="p.status === 'programado'"
                                                @click="openSettle(p)"
                                                class="text-emerald-700 hover:underline text-xs font-semibold">Cobrar</button>
                                            <a :href="paymentReceiptUrl(p)" target="_blank"
                                                class="text-amber-700 hover:underline text-xs font-semibold">Solicitud / Recibo</a>
                                            <button v-if="canInvoice(p) && !invoiceByPaymentId[p.id]"
                                                @click="issueInvoice(p)"
                                                class="text-purple-700 hover:underline text-xs font-semibold">Facturar</button>
                                            <a v-if="p.evidence_file_path"
                                                :href="`/storage/${p.evidence_file_path}`" target="_blank"
                                                class="text-indigo-600 hover:underline text-xs">Evidencia</a>
                                            <button v-if="['programado','registrado'].includes(p.status)"
                                                @click="cancelPayment(p)"
                                                class="text-rose-600 hover:underline text-xs">Cancelar</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Columna derecha (contrato + cliente) -->
                <aside class="space-y-6">
                    <div class="bg-white rounded-lg shadow p-5">
                        <h4 class="font-semibold text-gray-700 uppercase text-xs tracking-wide mb-3">Contrato</h4>
                        <p class="text-sm">Número: <span class="font-mono text-amber-700">{{ contract.contract_number }}</span></p>
                        <p class="text-sm">Estado: <span class="font-semibold">{{ contract.status }}</span></p>
                        <p class="text-sm">Inicio: {{ fmtDate(contract.start_date) }}</p>
                        <p class="text-sm">Fin: {{ fmtDate(contract.end_date) }}</p>
                        <a v-if="contract.pdf_file_path" :href="`/storage/${contract.pdf_file_path}`" target="_blank"
                           class="mt-2 inline-block text-xs text-indigo-600 hover:underline">Descargar PDF</a>
                    </div>

                    <div v-if="contract.client" class="bg-white rounded-lg shadow p-5">
                        <h4 class="font-semibold text-gray-700 uppercase text-xs tracking-wide mb-3">Cliente</h4>
                        <p class="text-base font-semibold text-gray-800">{{ contract.client.business_name }}</p>
                        <p v-if="contract.client.contact_name" class="text-sm text-gray-600">{{ contract.client.contact_name }}</p>
                        <p v-if="contract.client.phone" class="text-sm text-amber-700 mt-2">{{ contract.client.phone }}</p>
                        <p v-if="contract.client.email" class="text-sm text-gray-600">{{ contract.client.email }}</p>
                        <p v-if="contract.client.rfc" class="text-xs text-gray-500 mt-2">RFC: {{ contract.client.rfc }}</p>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-5 text-center">
                        <p class="text-xs text-amber-800 uppercase tracking-wide">Saldo pendiente</p>
                        <p class="text-2xl font-bold text-amber-700 mt-1">{{ fmtMoney(totals.pending) }}</p>
                    </div>
                </aside>
            </div>
        </div>

        <!-- Modal Registrar Pago -->
        <div v-if="showCreate" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showCreate = false">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-bold mb-4">Registrar pago</h3>
                <form @submit.prevent="submitCreate" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Tipo *</label>
                            <select v-model="createForm.type" class="w-full border rounded px-2 py-1.5 text-sm">
                                <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Método *</label>
                            <select v-model="createForm.payment_method" class="w-full border rounded px-2 py-1.5 text-sm">
                                <option v-for="m in paymentMethods" :key="m" :value="m">{{ methodLabel(m) }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Concepto *</label>
                        <input v-model="createForm.concept" class="w-full border rounded px-2 py-1.5 text-sm" required />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Monto *</label>
                            <input v-model="createForm.amount" type="number" step="0.01" min="0.01" class="w-full border rounded px-2 py-1.5 text-sm" required />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha de pago *</label>
                            <input v-model="createForm.paid_at" type="date" class="w-full border rounded px-2 py-1.5 text-sm" required />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Referencia</label>
                        <input v-model="createForm.reference" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Evidencia (PDF/imagen)</label>
                        <input type="file" @change="createForm.evidence_file = $event.target.files[0]" class="w-full text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Notas</label>
                        <textarea v-model="createForm.notes" rows="2" class="w-full border rounded px-2 py-1.5 text-sm"></textarea>
                    </div>
                    <div v-if="Object.keys(createForm.errors).length" class="p-2 bg-rose-50 text-rose-800 text-xs rounded">
                        <p v-for="(msg, k) in createForm.errors" :key="k">{{ msg }}</p>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showCreate = false" class="px-4 py-1.5 rounded border text-sm">Cancelar</button>
                        <button type="submit" :disabled="createForm.processing" class="px-4 py-1.5 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold disabled:opacity-50">
                            {{ createForm.processing ? 'Guardando…' : 'Registrar pago' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Cobrar -->
        <div v-if="settleTarget" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="settleTarget = null">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-bold mb-1">Cobrar mensualidad</h3>
                <p class="text-xs text-gray-500 mb-4">{{ settleTarget.concept }} · Vence {{ fmtDate(settleTarget.due_date) }}</p>
                <form @submit.prevent="submitSettle" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Monto</label>
                            <input v-model="settleForm.amount" type="number" step="0.01" min="0.01" class="w-full border rounded px-2 py-1.5 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Método</label>
                            <select v-model="settleForm.payment_method" class="w-full border rounded px-2 py-1.5 text-sm">
                                <option v-for="m in paymentMethods" :key="m" :value="m">{{ methodLabel(m) }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha de pago</label>
                        <input v-model="settleForm.paid_at" type="date" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Referencia</label>
                        <input v-model="settleForm.reference" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Evidencia</label>
                        <input type="file" @change="settleForm.evidence_file = $event.target.files[0]" class="w-full text-sm" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="settleTarget = null" class="px-4 py-1.5 rounded border text-sm">Cancelar</button>
                        <button type="submit" :disabled="settleForm.processing" class="px-4 py-1.5 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold disabled:opacity-50">
                            Cobrar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
