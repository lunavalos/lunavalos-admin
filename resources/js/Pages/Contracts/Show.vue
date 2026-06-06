<script setup>
import { useMoney } from '@/Composables/useMoney';
import { ref, computed } from 'vue';
import ContractBody from '@/Components/ContractBody.vue';
import { Head, useForm } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    contract: Object,
    settings: Object,
});

const isReadingContract = ref(false);

const form = useForm({
    legal_name: '',
    tax_id: '',
    fiscal_address: '',
    postal_code: '',
    legal_representative: '',
    csf_file: null,
    accept_terms: false,
});

const { fmt: _fmt } = useMoney();
const fmt = (n, c) => _fmt(n, c);
const fmtDate = (d) => {
    if (!d) return '—';
    const part = String(d).split('T')[0].split(' ')[0]; // handles both 'YYYY-MM-DD' and 'YYYY-MM-DD HH:MM:SS'
    return new Date(part + 'T12:00:00').toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' });
};
const fmtDt = (d) => d ? new Date(String(d).replace(' ', 'T')).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

// Contract display data: uses form values in preview mode, contract values when signed.
const cd = computed(() => {
    const c      = props.contract;
    const signed = c.status === 'signed';

    // Renewal = migrated contract (no quote) OR no anticipo recorded.
    // Differentiates "pago único de renovación anual" from "proyecto a meses".
    const isRenewal = !c.quote_id || parseFloat(c.anticipo_amount || 0) === 0;

    // Services: prefer quote items; fall back to client_services for migrated contracts.
    const services = (c.quote?.items?.length)
        ? c.quote.items.map(i => ({ name: i.concept, detail: i.description || null }))
        : (c.client?.services?.length)
            ? c.client.services.map(s => ({ name: s.service_name, detail: null }))
            : [];

    return {
        providerName:   props.settings?.company_legal_name || props.settings?.company_commercial_name || 'Luna Avalos',
        providerSite:   props.settings?.company_website || 'lunavalos.com',
        contractNumber: c.contract_number || ('#' + c.id),
        date:           fmtDate(c.start_date || c.created_at),
        startDate:      fmtDate(c.start_date),
        endDate:        fmtDate(c.end_date),
        quoteId:        c.quote?.id || null,
        clientName:     signed ? (c.legal_name || '—')              : (form.legal_name || '[Razón Social]'),
        taxId:          signed ? (c.tax_id || '—')                  : (form.tax_id || '[RFC]'),
        fiscalAddress:  signed ? (c.fiscal_address || '—')          : (form.fiscal_address || '[Dirección fiscal]'),
        postalCode:     signed ? (c.postal_code || '')               : (form.postal_code || ''),
        legalRep:       signed ? (c.legal_representative || '')      : (form.legal_representative || ''),
        subtotal:       fmt(c.subtotal),
        iva:            fmt(c.iva_amount),
        total:          fmt(c.total_amount),
        anticipo:       fmt(c.anticipo_amount),
        monthly:        fmt(c.monthly_amount),
        months:         c.payment_plan_months || 1,
        currency:       c.currency || 'MXN',
        isRenewal,
        services,
        signedAt:       c.signed_at ? fmtDt(c.signed_at) : null,
        signatureIp:    c.signature_ip,
    };
});

const proceedToContract = () => {
    const isPersonaMoral = form.tax_id.length === 12;
    const isMissingData = !form.legal_name || !form.tax_id || !form.fiscal_address || !form.postal_code || !form.csf_file || (isPersonaMoral && !form.legal_representative);
    if (isMissingData) {
        alert('Por favor completa todos tus datos fiscales (incluyendo representante legal si eres persona moral) y adjunta tu CSF antes de proceder.');
        return;
    }
    isReadingContract.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const submit = () => {
    if (!form.accept_terms) {
        alert('Debes aceptar los términos y condiciones para continuar.');
        return;
    }
    form.post(route('contracts.sign', props.contract.token));
};

const pdfUrl = `/contratodeservicio/${props.contract.token}/pdf`;
</script>

<template>
    <Head title="Contrato de Servicio" />

    <div class="min-h-screen bg-gray-100 flex flex-col items-center pt-6 sm:pt-0 pb-16">
        <div class="w-full max-w-4xl mt-6 px-6 py-8 bg-white shadow-xl overflow-hidden sm:rounded-xl">

            <!-- Header -->
            <div class="flex flex-col items-center mb-8 border-b pb-6">
                <ApplicationLogo class="w-auto h-20 fill-current text-gray-800 mb-4" />
                <h2 class="text-2xl font-bold text-gray-800 text-center">Formalización de Proyecto</h2>
                <p v-if="contract.status === 'pending'" class="text-gray-500 mt-2 text-center">
                    Estás a un paso de iniciar a trabajar con {{ cd.providerName }}.
                </p>
            </div>

            <!-- Flash messages -->
            <div v-if="$page.props.flash?.success" class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                {{ $page.props.flash.error }}
            </div>

            <!-- ── SIGNED VIEW ─────────────────────────────── -->
            <div v-if="contract.status === 'signed'" class="space-y-6">
                <!-- Signed banner + PDF download -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 bg-emerald-50 border border-emerald-300 rounded-xl">
                    <div>
                        <p class="font-bold text-emerald-800 text-lg">✓ Contrato firmado exitosamente</p>
                        <p class="text-sm text-emerald-700 mt-0.5">Firmado el {{ cd.signedAt }} · IP {{ cd.signatureIp }}</p>
                    </div>
                    <a :href="pdfUrl"
                       class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#264ab3] hover:bg-[#193074] text-white font-bold rounded-lg shadow transition text-sm shrink-0">
                        <ArrowDownTrayIcon class="w-5 h-5" />
                        Descargar PDF
                    </a>
                </div>

                <!-- Signed contract body (read-only) -->
                <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <div class="bg-[#264ab3] text-white px-8 py-5 flex items-start justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-widest opacity-70">Contrato de Prestación de Servicios</p>
                            <p class="font-bold text-lg mt-0.5">{{ cd.contractNumber }}</p>
                            <p class="text-sm opacity-80 mt-0.5">Fecha: {{ cd.date }}</p>
                        </div>
                        <div class="text-right text-xs opacity-70 mt-1">
                            <p>{{ cd.providerName }}</p>
                            <p>{{ cd.providerSite }}</p>
                        </div>
                    </div>
                    <div class="p-8 font-serif text-sm text-gray-800 leading-relaxed space-y-5">
                        <ContractBody :cd="cd" />
                    </div>
                </div>
            </div>

            <!-- ── PENDING VIEW ────────────────────────────── -->
            <div v-else-if="contract.status === 'pending' || contract.status === 'activo'">

                <!-- Step 1: Fiscal data -->
                <div v-show="!isReadingContract" class="space-y-6">
                    <div class="bg-blue-50 p-4 border border-blue-200 rounded-lg text-sm text-blue-800">
                        <strong>Paso 1 de 2:</strong> Para generar tu contrato legal y emitir facturas, completa tus datos fiscales.
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <InputLabel for="legal_name" value="Razón Social o Nombre Legal" />
                            <TextInput id="legal_name" type="text" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                v-model="form.legal_name" required />
                            <InputError class="mt-2" :message="form.errors.legal_name" />
                        </div>
                        <div>
                            <InputLabel for="tax_id" value="RFC" />
                            <TextInput id="tax_id" type="text" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                v-model="form.tax_id" required />
                            <InputError class="mt-2" :message="form.errors.tax_id" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="fiscal_address" value="Dirección Fiscal Completa" />
                            <TextInput id="fiscal_address" type="text" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                v-model="form.fiscal_address" required />
                            <InputError class="mt-2" :message="form.errors.fiscal_address" />
                        </div>
                        <div>
                            <InputLabel for="postal_code" value="Código Postal" />
                            <TextInput id="postal_code" type="text" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                v-model="form.postal_code" required />
                            <InputError class="mt-2" :message="form.errors.postal_code" />
                        </div>
                        <div>
                            <InputLabel for="legal_representative" value="Nombre del Representante Legal" />
                            <TextInput id="legal_representative" type="text" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                v-model="form.legal_representative" />
                            <InputError class="mt-2" :message="form.errors.legal_representative" />
                        </div>
                        <div class="md:col-span-2 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                            <InputLabel for="csf_file" value="Constancia de Situación Fiscal (PDF o Imagen)" class="mb-2 font-bold" />
                            <input type="file" id="csf_file"
                                @change="e => form.csf_file = e.target.files[0]"
                                class="block w-full text-sm text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer"
                                required accept=".pdf,.png,.jpg,.jpeg" />
                            <InputError class="mt-2" :message="form.errors.csf_file" />
                        </div>
                    </div>
                    <div class="mt-8 flex justify-end border-t pt-6">
                        <button @click="proceedToContract" type="button"
                            class="bg-[#264ab3] hover:bg-[#193074] text-white px-8 py-3 rounded-lg font-bold shadow-md transition">
                            Leer Contrato y Continuar &rarr;
                        </button>
                    </div>
                </div>

                <!-- Step 2: Contract preview & sign -->
                <div v-show="isReadingContract" class="space-y-8">
                    <div class="bg-blue-50 p-4 border border-blue-200 rounded-lg text-sm text-blue-800 flex justify-between items-center">
                        <span><strong>Paso 2 de 2:</strong> Revisa el contrato generado con tu información y acepta para firmarlo digitalmente.</span>
                        <button @click="isReadingContract = false" class="text-blue-600 underline font-bold text-xs hover:text-blue-800 ml-4 shrink-0">&larr; Editar datos</button>
                    </div>

                    <!-- Contract preview -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <div class="bg-[#264ab3] text-white px-8 py-5 flex items-start justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-widest opacity-70">Contrato de Prestación de Servicios</p>
                                <p class="font-bold text-lg mt-0.5">{{ cd.contractNumber }}</p>
                                <p class="text-sm opacity-80 mt-0.5">Fecha: {{ cd.date }}</p>
                            </div>
                            <div class="text-right text-xs opacity-70 mt-1">
                                <p>{{ cd.providerName }}</p>
                                <p>{{ cd.providerSite }}</p>
                            </div>
                        </div>
                        <div class="p-8 font-serif text-sm text-gray-800 leading-relaxed max-h-[600px] overflow-y-auto space-y-5">
                            <ContractBody :cd="cd" />
                        </div>
                    </div>

                    <!-- Signature form -->
                    <div class="border-t pt-8">
                        <form @submit.prevent="submit" class="bg-gray-50 border border-gray-200 p-6 rounded-xl">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Firma Digital</h3>
                            <label class="flex items-start gap-3">
                                <input type="checkbox" v-model="form.accept_terms"
                                    class="mt-1 bg-white border-gray-300 rounded shadow-sm text-green-600 focus:ring-green-500" />
                                <span class="text-sm text-gray-700">
                                    He leído y entendido todos los apartados de este contrato.
                                    Al marcar esta casilla, <strong>{{ form.legal_representative || form.legal_name }}</strong>
                                    {{ form.legal_representative ? 'en nombre de ' + form.legal_name : '' }}
                                    acepta que esta aceptación electrónica surte los mismos efectos jurídicos que una firma autógrafa,
                                    conforme a la legislación aplicable en materia de comercio electrónico.
                                </span>
                            </label>
                            <InputError class="mt-2" :message="form.errors.accept_terms" />
                            <div class="flex justify-end mt-6">
                                <button type="submit"
                                    class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg font-bold shadow-md transition"
                                    :class="{ 'opacity-50': form.processing }"
                                    :disabled="form.processing || !form.accept_terms">
                                    Firma y Aceptación de Contrato &rarr;
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Cancelled / unknown -->
            <div v-else class="p-6 bg-gray-50 border border-gray-200 rounded-xl text-center text-gray-500">
                Este contrato no está disponible.
            </div>

        </div>
    </div>
</template>
