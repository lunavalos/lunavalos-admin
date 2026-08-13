<script setup>
import { useMoney } from '@/Composables/useMoney';
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    quote: { type: Object, required: true },
    taxRegimes: { type: Object, default: () => ({}) },
    paymentMethods: { type: Array, default: () => [] },
    downPaymentPercent: { type: Number, default: 50 },
    canConvert: { type: Boolean, default: false },
});

const { fmt: _fmt } = useMoney();
const fmt = (n, c) => _fmt(n, c);
const pct = (n) => `${Number(n || 0).toFixed(2)}%`;

const statusColor = computed(() => ({
    'Borrador': 'bg-gray-200 text-gray-700',
    'Enviada': 'bg-blue-100 text-blue-700',
    'Aceptada': 'bg-emerald-100 text-emerald-700',
    'Rechazada': 'bg-rose-100 text-rose-700',
    'Expirada': 'bg-amber-100 text-amber-700',
    'Convertida': 'bg-violet-100 text-violet-700',
    'Requiere ajustes': 'bg-yellow-100 text-yellow-800',
}[props.quote.status] || 'bg-gray-200 text-gray-700'));

const regimeLabel = computed(() => {
    if (!props.quote.tax_regime) return null;
    const r = props.taxRegimes[props.quote.tax_regime];
    return r ? `${props.quote.tax_regime} · ${r.label}` : props.quote.tax_regime;
});

// --- Transiciones rápidas de estado ---
const transitionForm = useForm({ status: '' });
const transition = (to) => {
    transitionForm.status = to;
    transitionForm.post(route('quotes.transition', props.quote.id), { preserveScroll: true });
};

// --- Modal de conversión ---
const showConvert = ref(false);

const planMonths = computed(() => Math.max(1, Number(props.quote.package_payment_plan_months) || 1));
const total = computed(() => Number(props.quote.total) || 0);

// Buckets por tipo de cobro (vienen como appends del modelo Quote).
const totalUnique  = computed(() => Number(props.quote.total_unique)  || 0);
const totalMonthly = computed(() => Number(props.quote.total_monthly) || 0);
const totalAnnual  = computed(() => Number(props.quote.total_annual)  || 0);

/**
 * Tipo de cobro dominante del contrato.
 * 1) Del paquete (si viene cargado).
 * 2) Inferido del mix de items: monthly > annual > unique.
 */
const packageBilling = computed(() => {
    const fromPackage = props.quote.package?.billing_type;
    if (fromPackage) return fromPackage;
    if (totalMonthly.value > 0) return 'monthly';
    if (totalAnnual.value  > 0) return 'annual';
    return 'unique';
});

/**
 * Anualidad recurrente (año 2 en adelante): renovación de los items + addons
 * anuales. En `annual`, el precio base NO entra aquí: es el pago inicial del
 * desarrollo, que se cubre con el anticipo + el plan de mensualidades.
 */
const annualRenewal = computed(() => {
    const items = (props.quote.items || []).reduce(
        (s, i) => s + Number(i.unit_renewal_price || 0) * Number(i.quantity || 1), 0
    );
    const addons = (props.quote.addons || [])
        .filter(a => ['annual', 'semiannual'].includes(a.billing_cycle))
        .reduce((s, a) => s + Number(a.unit_price || 0) * Number(a.quantity || 1), 0);

    return Math.round((items + addons) * 100) / 100;
});

/**
 * Anticipo sugerido SEGÚN el tipo de cobro:
 *   - monthly: 1 mes (total_monthly o total/N como fallback)
 *   - annual / unique: el pago inicial se difiere en N meses → total/N.
 */
const suggestedAnticipo = computed(() => {
    const round2 = (n) => Math.round(n * 100) / 100;

    if (packageBilling.value === 'monthly') {
        return round2(totalMonthly.value > 0 ? totalMonthly.value : total.value / planMonths.value);
    }
    // annual / unique: ambos financian un pago inicial único.
    if (planMonths.value > 1) {
        return round2(total.value / planMonths.value);
    }
    if (planMonths.value === 1) {
        return total.value;
    }
    return round2((total.value * props.downPaymentPercent) / 100);
});

// Cuántas cuotas adicionales se programarán y por cuánto.
// Las cuotas del plan siempre son MENSUALES (también en `annual`: financian el
// pago inicial). Las renovaciones anuales se agendan aparte, desde el año 2.
const remainingInstallments = computed(() => Math.max(0, planMonths.value - 1));
const remainingPerMonth = computed(() => {
    if (remainingInstallments.value === 0) return 0;
    const anticipo = Number(convertForm?.anticipo_amount || suggestedAnticipo.value);

    if (packageBilling.value === 'monthly') {
        // La cuota recurrente es el monto mensual; el remanente del componente
        // único se distribuye entre las cuotas restantes.
        const recurring = totalMonthly.value > 0 ? totalMonthly.value : total.value / planMonths.value;
        const excess = Math.max(0, anticipo - recurring);
        const uniqueRemainder = Math.max(0, totalUnique.value - excess);
        return Math.round((recurring + uniqueRemainder / remainingInstallments.value) * 100) / 100;
    }
    const bal = Math.max(0, total.value - anticipo);
    return Math.round((bal / remainingInstallments.value) * 100) / 100;
});

const convertForm = useForm({
    legal_name:           props.quote.client?.legal_name || props.quote.client_name || '',
    rfc:                  props.quote.client?.rfc || '',
    tax_regime:           props.quote.tax_regime || props.quote.client?.tax_regime || '',
    fiscal_address:       props.quote.client?.fiscal_address || '',
    tax_zip_code:         props.quote.client?.tax_zip_code || '',
    legal_representative: '',
    start_date:           new Date().toISOString().slice(0, 10),
    notes:                '',

    anticipo_amount:   suggestedAnticipo.value,
    payment_method:    'transferencia',
    payment_reference: '',
    paid_at:           new Date().toISOString().slice(0, 10),
    payment_notes:     '',
    evidence_file:     null,
});

const onPickFile = (e) => { convertForm.evidence_file = e.target.files?.[0] || null; };

const submitConvert = () => {
    convertForm.post(route('quotes.convert', props.quote.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { showConvert.value = false; },
    });
};
</script>

<template>
    <Head :title="`Cotización #${quote.id}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100">Cotización #{{ quote.id }}</h2>
                <span class="px-3 py-1 text-sm rounded-full font-semibold" :class="statusColor">{{ quote.status }}</span>
            </div>
        </template>

        <div class="py-8 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Cliente -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">Cliente</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div><span class="text-gray-500">Razón social:</span> <strong>{{ quote.client_name }}</strong></div>
                    <div><span class="text-gray-500">Contacto:</span> {{ quote.contact_name || '—' }}</div>
                    <div><span class="text-gray-500">Email:</span> {{ quote.email || '—' }}</div>
                    <div><span class="text-gray-500">Teléfono:</span> {{ quote.phone || '—' }}</div>
                    <div><span class="text-gray-500">Vigencia:</span> {{ quote.issue_date }} → {{ quote.valid_until }}</div>
                    <div><span class="text-gray-500">Duración:</span> {{ quote.duration || '—' }}</div>
                </div>
            </div>

            <!-- Configuración fiscal -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">Configuración fiscal (snapshot)</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div><span class="text-gray-500">Régimen:</span> {{ regimeLabel || '— No especificado —' }}</div>
                    <div><span class="text-gray-500">IVA:</span>
                        <span v-if="quote.applies_iva">Sí · {{ pct(quote.iva_rate) }} ({{ fmt(quote.iva_amount) }})</span>
                        <span v-else>No</span>
                    </div>
                    <div><span class="text-gray-500">Ret. ISR:</span>
                        <span v-if="quote.applies_isr_retention">{{ pct(quote.isr_retention_rate) }} ({{ fmt(quote.isr_retention_amount) }})</span>
                        <span v-else>—</span>
                    </div>
                    <div><span class="text-gray-500">Ret. IVA:</span>
                        <span v-if="quote.applies_iva_retention">{{ pct(quote.iva_retention_rate) }} ({{ fmt(quote.iva_retention_amount) }})</span>
                        <span v-else>—</span>
                    </div>
                </div>
            </div>

            <!-- Conceptos -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">Paquete y addons</h3>
                <ul class="divide-y divide-gray-100 dark:divide-zinc-700 text-sm">
                    <li v-for="item in quote.items" :key="`item-${item.id}`" class="py-2 flex justify-between">
                        <div>
                            <div class="font-semibold">{{ item.concept }}</div>
                            <div class="text-xs text-gray-500">{{ item.description }}</div>
                        </div>
                        <div class="text-right">
                            <div class="font-bold">{{ fmt(item.unit_price * item.quantity) }}</div>
                            <div class="text-xs text-gray-500">{{ item.billing_type }} · x{{ item.quantity }}</div>
                        </div>
                    </li>
                    <li v-for="addon in quote.addons" :key="`addon-${addon.id}`" class="py-2 flex justify-between">
                        <div>
                            <div class="font-semibold">{{ addon.service_addon?.name || 'Addon' }}</div>
                            <div class="text-xs text-gray-500">{{ addon.billing_cycle }} · x{{ addon.quantity }}</div>
                        </div>
                        <div class="font-bold">{{ fmt(addon.unit_price * addon.quantity) }}</div>
                    </li>
                </ul>
            </div>

            <!-- Totales -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">Totales</h3>
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between"><span>Subtotal</span><span>{{ fmt(quote.subtotal) }}</span></div>
                    <div v-if="quote.discount_amount > 0" class="flex justify-between text-amber-700">
                        <span>Descuento</span><span>-{{ fmt(quote.discount_amount) }}</span>
                    </div>
                    <div v-if="quote.applies_iva" class="flex justify-between text-blue-700">
                        <span>+ IVA {{ pct(quote.iva_rate) }}</span><span>{{ fmt(quote.iva_amount) }}</span>
                    </div>
                    <div v-if="quote.applies_isr_retention" class="flex justify-between text-rose-600">
                        <span>− Ret. ISR</span><span>-{{ fmt(quote.isr_retention_amount) }}</span>
                    </div>
                    <div v-if="quote.applies_iva_retention" class="flex justify-between text-rose-600">
                        <span>− Ret. IVA</span><span>-{{ fmt(quote.iva_retention_amount) }}</span>
                    </div>
                    <div class="flex justify-between text-lg font-bold border-t pt-2 mt-2 border-gray-200 dark:border-zinc-700">
                        <span>Total</span><span class="text-green-600">{{ fmt(quote.total) }}</span>
                    </div>
                </div>
            </div>

            <!-- Acciones -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">Acciones</h3>

                <div v-if="$page.props.flash?.success" class="mb-4 p-3 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.errors?.conversion" class="mb-4 p-3 rounded bg-rose-50 border border-rose-200 text-rose-700 text-sm">
                    {{ $page.props.errors.conversion }}
                </div>

                <div class="flex flex-wrap gap-2">
                    <a :href="route('quotes.show', quote.id)" target="_blank"
                        class="px-4 py-2 rounded bg-gray-100 hover:bg-gray-200 dark:bg-zinc-700 dark:hover:bg-zinc-600 text-sm font-semibold">
                        Descargar PDF
                    </a>

                    <Link v-if="!quote.contract && !quote.legacy"
                        :href="route('quotes.wizard.edit', quote.id)"
                        class="px-4 py-2 rounded bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold">
                        Editar cotización
                    </Link>

                    <button v-if="quote.status === 'Borrador'" type="button"
                        @click="transition('Enviada')"
                        class="px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold">
                        Marcar como enviada
                    </button>

                    <button v-if="quote.status === 'Enviada'" type="button"
                        @click="transition('Aceptada')"
                        class="px-4 py-2 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">
                        Marcar como aceptada
                    </button>

                    <button v-if="quote.status === 'Enviada' || quote.status === 'Aceptada'" type="button"
                        @click="transition('Rechazada')"
                        class="px-4 py-2 rounded bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold">
                        Marcar como rechazada
                    </button>

                    <button v-if="canConvert" type="button"
                        @click="showConvert = true"
                        class="px-4 py-2 rounded bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold">
                        Cerrar venta → Contrato
                    </button>

                    <Link v-if="quote.contract" :href="`/contratodeservicio/${quote.contract.token}`"
                        class="px-4 py-2 rounded bg-violet-100 text-violet-800 text-sm font-semibold">
                        Ver contrato {{ quote.contract.contract_number || `#${quote.contract.id}` }}
                    </Link>
                </div>

                <p v-if="!canConvert && !quote.contract" class="mt-3 text-xs text-gray-500">
                    Para cerrar venta primero marca la cotización como <strong>Aceptada</strong>.
                </p>
            </div>
        </div>

        <!-- Calendario de pagos del contrato -->
        <div v-if="quote.contract && quote.contract.payments" class="max-w-5xl mx-auto sm:px-6 lg:px-8 mt-6">
            <div class="bg-white dark:bg-zinc-800 shadow rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100">Plan de pagos · {{ quote.contract.contract_number }}</h3>
                    <Link :href="route('payments.index', { contract_id: quote.contract.id })"
                        class="text-sm text-indigo-600 hover:underline">Ir a Cobranza →</Link>
                </div>

                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500 border-b">
                        <tr>
                            <th class="text-left py-2">#</th>
                            <th class="text-left py-2">Concepto</th>
                            <th class="text-right py-2">Monto</th>
                            <th class="text-left py-2">Vence</th>
                            <th class="text-left py-2">Pagado</th>
                            <th class="text-left py-2">Estatus</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="p in quote.contract.payments" :key="p.id">
                            <td class="py-2 text-gray-500">{{ p.installment_number || (p.type === 'anticipo' ? 'A' : '—') }}</td>
                            <td class="py-2">{{ p.concept }}</td>
                            <td class="py-2 text-right font-mono">${{ Number(p.amount).toLocaleString('es-MX', { minimumFractionDigits: 2 }) }}</td>
                            <td class="py-2 text-gray-600">{{ p.due_date || '—' }}</td>
                            <td class="py-2 text-gray-600">{{ p.paid_at || '—' }}</td>
                            <td class="py-2">
                                <span :class="['inline-block px-2 py-0.5 rounded text-xs font-semibold',
                                    p.status === 'programado' ? 'bg-amber-100 text-amber-800'
                                    : p.status === 'registrado' ? 'bg-blue-100 text-blue-800'
                                    : p.status === 'conciliado' ? 'bg-emerald-100 text-emerald-800'
                                    : p.status === 'facturado'  ? 'bg-purple-100 text-purple-800'
                                    : 'bg-gray-200 text-gray-600']">
                                    {{ p.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal conversión -->
        <div v-if="showConvert" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700 flex justify-between items-center">
                    <h3 class="font-semibold">Cerrar venta — generar contrato</h3>
                    <button type="button" @click="showConvert = false" class="text-gray-500 hover:text-gray-800">&times;</button>
                </div>

                <form @submit.prevent="submitConvert" class="p-6 space-y-6">
                    <!-- Datos legales -->
                    <section>
                        <h4 class="font-semibold mb-2">Datos legales del cliente</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Razón social legal *" />
                                <TextInput v-model="convertForm.legal_name" class="w-full" />
                                <InputError :message="convertForm.errors.legal_name" />
                            </div>
                            <div>
                                <InputLabel value="RFC" />
                                <TextInput v-model="convertForm.rfc" class="w-full" />
                                <InputError :message="convertForm.errors.rfc" />
                            </div>
                            <div>
                                <InputLabel value="Régimen fiscal" />
                                <select v-model="convertForm.tax_regime"
                                    class="mt-1 block w-full rounded border-gray-300 dark:border-zinc-600 dark:bg-zinc-900">
                                    <option value="">— No especificado —</option>
                                    <option v-for="(reg, code) in taxRegimes" :key="code" :value="code">{{ code }} · {{ reg.label }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="CP fiscal" />
                                <TextInput v-model="convertForm.tax_zip_code" class="w-full" />
                            </div>
                            <div class="md:col-span-2">
                                <InputLabel value="Domicilio fiscal" />
                                <TextInput v-model="convertForm.fiscal_address" class="w-full" />
                            </div>
                            <div>
                                <InputLabel value="Representante legal" />
                                <TextInput v-model="convertForm.legal_representative" class="w-full" />
                            </div>
                            <div>
                                <InputLabel value="Fecha de inicio" />
                                <TextInput type="date" v-model="convertForm.start_date" class="w-full" />
                            </div>
                        </div>
                    </section>

                    <!-- Anticipo -->
                    <section class="rounded-lg border border-emerald-200 bg-emerald-50/40 dark:bg-emerald-900/10 dark:border-emerald-900/40 p-4">
                        <h4 class="font-semibold mb-1 text-emerald-900 dark:text-emerald-300">
                            Primer pago / Anticipo
                        </h4>
                        <p class="text-xs text-emerald-900/80 dark:text-emerald-300/80 mb-3">
                            <template v-if="packageBilling === 'monthly'">
                                Plan <strong>mensual</strong> a <strong>{{ planMonths }} meses</strong> de
                                <strong>{{ fmt(totalMonthly || total / planMonths) }}</strong> cada mes.
                                Este pago cuenta como la <strong>mensualidad 1 de {{ planMonths }}</strong>.
                                Se programarán automáticamente {{ remainingInstallments }} cuotas mensuales restantes de {{ fmt(remainingPerMonth) }}.
                            </template>
                            <template v-else-if="packageBilling === 'annual'">
                                <strong>Pago inicial único</strong> de <strong>{{ fmt(total) }}</strong> por el desarrollo del proyecto
                                <template v-if="planMonths > 1">
                                    , diferido a <strong>{{ planMonths }} mensualidades</strong>. Este pago cuenta como la
                                    <strong>mensualidad 1 de {{ planMonths }}</strong>. Se programarán
                                    {{ remainingInstallments }} cuotas mensuales restantes de {{ fmt(remainingPerMonth) }}.
                                </template>
                                <template v-else>. Cubre el año 1 de servicio.</template>
                                <template v-if="annualRenewal > 0">
                                    Aparte, la <strong>anualidad de {{ fmt(annualRenewal) }}</strong> se agenda cada año
                                    <strong>a partir del año 2</strong>.
                                </template>
                            </template>
                            <template v-else-if="planMonths > 1">
                                Pago único de <strong>{{ fmt(total) }}</strong> diferido a
                                <strong>{{ planMonths }} mensualidades</strong>. Este pago cuenta como la
                                <strong>mensualidad 1 de {{ planMonths }}</strong>. Se programarán
                                {{ remainingInstallments }} cuotas restantes de {{ fmt(remainingPerMonth) }}.
                            </template>
                            <template v-else>
                                Cotización a un solo pago: monto total {{ fmt(total) }}.
                            </template>
                            <template v-if="annualRenewal > 0 && packageBilling !== 'annual'">
                                La <strong>renovación anual de {{ fmt(annualRenewal) }}</strong> se agenda aparte,
                                cada año <strong>a partir del año 2</strong>.
                            </template>
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Monto del primer pago *" />
                                <TextInput type="number" step="0.01" min="0" v-model="convertForm.anticipo_amount" class="w-full" />
                                <InputError :message="convertForm.errors.anticipo_amount" />
                                <p class="text-xs text-gray-500 mt-1">Sugerido: {{ fmt(suggestedAnticipo) }}</p>
                            </div>
                            <div>
                                <InputLabel value="Método de pago *" />
                                <select v-model="convertForm.payment_method"
                                    class="mt-1 block w-full rounded border-gray-300 dark:border-zinc-600 dark:bg-zinc-900">
                                    <option v-for="m in paymentMethods" :key="m" :value="m">{{ m }}</option>
                                </select>
                                <InputError :message="convertForm.errors.payment_method" />
                            </div>
                            <div>
                                <InputLabel value="Referencia / folio" />
                                <TextInput v-model="convertForm.payment_reference" class="w-full" />
                            </div>
                            <div>
                                <InputLabel value="Fecha de pago" />
                                <TextInput type="date" v-model="convertForm.paid_at" class="w-full" />
                            </div>
                            <div class="md:col-span-2">
                                <InputLabel value="Evidencia (PDF/imagen, máx 10MB)" />
                                <input type="file" accept=".pdf,image/*" @change="onPickFile"
                                    class="mt-1 block w-full text-sm" />
                                <InputError :message="convertForm.errors.evidence_file" />
                            </div>
                            <div class="md:col-span-2">
                                <InputLabel value="Notas del pago" />
                                <textarea v-model="convertForm.payment_notes" rows="2"
                                    class="mt-1 block w-full rounded border-gray-300 dark:border-zinc-600 dark:bg-zinc-900" />
                            </div>
                        </div>
                    </section>

                    <div>
                        <InputLabel value="Notas internas del contrato" />
                        <textarea v-model="convertForm.notes" rows="3"
                            class="mt-1 block w-full rounded border-gray-300 dark:border-zinc-600 dark:bg-zinc-900" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-gray-200 dark:border-zinc-700">
                        <button type="button" @click="showConvert = false"
                            class="px-4 py-2 rounded border border-gray-300 dark:border-zinc-600">Cancelar</button>
                        <button type="submit" :disabled="convertForm.processing"
                            class="px-5 py-2 rounded bg-violet-600 hover:bg-violet-700 text-white font-semibold disabled:opacity-40">
                            {{ convertForm.processing ? 'Procesando…' : 'Confirmar cierre de venta' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
