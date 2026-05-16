<script setup>
import { ref, computed, defineComponent } from 'vue';
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

const fmt = (n) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n) || 0);
const fmtDate = (d) => d ? new Date(d + 'T00:00:00').toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' }) : '—';
const fmtDt = (d) => d ? new Date(d).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

// Contract display data: uses form values in preview mode, contract values when signed.
const cd = computed(() => {
    const signed = props.contract.status === 'signed';
    return {
        providerName:   props.settings?.company_legal_name || props.settings?.company_commercial_name || 'Luna Avalos',
        providerSite:   props.settings?.company_website || 'lunavalos.com',
        contractNumber: props.contract.contract_number || ('#' + props.contract.id),
        date:           fmtDate(props.contract.start_date || props.contract.created_at),
        quoteId:        props.contract.quote?.id || '—',
        clientName:     signed ? (props.contract.legal_name || '—')         : (form.legal_name || '[Razón Social]'),
        taxId:          signed ? (props.contract.tax_id || '—')             : (form.tax_id || '[RFC]'),
        fiscalAddress:  signed ? (props.contract.fiscal_address || '—')     : (form.fiscal_address || '[Dirección fiscal]'),
        postalCode:     signed ? (props.contract.postal_code || '')          : (form.postal_code || ''),
        legalRep:       signed ? (props.contract.legal_representative || '') : (form.legal_representative || ''),
        subtotal:       fmt(props.contract.subtotal),
        iva:            fmt(props.contract.iva_amount),
        total:          fmt(props.contract.total_amount),
        anticipo:       fmt(props.contract.anticipo_amount),
        monthly:        fmt(props.contract.monthly_amount),
        months:         props.contract.payment_plan_months || 12,
        items:          props.contract.quote?.items || [],
        signedAt:       props.contract.signed_at ? fmtDt(props.contract.signed_at) : null,
        signatureIp:    props.contract.signature_ip,
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

const ContractBody = defineComponent({
    name: 'ContractBody',
    props: { cd: Object },
    template: `
<div class="space-y-5 text-justify">
    <!-- Parties -->
    <div class="bg-gray-50 border-l-4 border-[#264ab3] p-4 rounded-r-lg space-y-1">
        <p>Entre:</p>
        <p><strong>{{ cd.providerName }} / {{ cd.providerSite }}</strong> <em>("EL PRESTADOR")</em></p>
        <p>y</p>
        <p><strong>{{ cd.clientName }}</strong> <em>("EL CLIENTE")</em></p>
        <p class="mt-2">Se celebra el presente Contrato de Prestación de Servicios bajo las siguientes cláusulas:</p>
    </div>

    <!-- PRIMERA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Primera. Objeto del Contrato</p>
        <p class="mb-2">EL PRESTADOR se compromete a desarrollar y/o prestar los servicios solicitados por EL CLIENTE conforme a la cotización aceptada No. <strong>{{ cd.quoteId }}</strong>, la cual forma parte integral del presente contrato.</p>
        <p class="font-semibold mb-1">Servicios contratados:</p>
        <ul class="list-none space-y-0.5 pl-3">
            <li v-for="item in cd.items" :key="item.id" class="flex gap-2">
                <span class="text-[#264ab3]">•</span>
                <span>{{ item.concept }}<span v-if="item.description" class="text-gray-400 font-sans"> — {{ item.description }}</span></span>
            </li>
        </ul>
    </div>

    <!-- SEGUNDA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Segunda. Monto y Forma de Pago</p>
        <p class="mb-2">El monto total de los servicios es:</p>
        <ul class="list-none pl-3 space-y-0.5 mb-3">
            <li><span class="text-[#264ab3]">•</span> Subtotal: <strong>{{ cd.subtotal }} MXN</strong></li>
            <li><span class="text-[#264ab3]">•</span> IVA: <strong>{{ cd.iva }} MXN</strong></li>
            <li><span class="text-[#264ab3]">•</span> Total: <strong>{{ cd.total }} MXN</strong></li>
        </ul>
        <p class="font-semibold mb-1">Forma de pago:</p>
        <ul class="list-none pl-3 space-y-0.5 mb-3">
            <li><span class="text-[#264ab3]">•</span> Anticipo: <strong>{{ cd.anticipo }}</strong></li>
            <li><span class="text-[#264ab3]">•</span> Mensualidades ({{ cd.months }} meses): <strong>{{ cd.monthly }}/mes</strong></li>
        </ul>
        <p>Los trabajos iniciarán una vez confirmado el anticipo correspondiente.</p>
    </div>

    <!-- TERCERA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Tercera. Tiempos de Entrega</p>
        <p class="mb-2">El tiempo estimado para la entrega será de <strong>15 días hábiles</strong>, contados a partir de:</p>
        <ul class="list-none pl-3 space-y-0.5 mb-3">
            <li><span class="text-[#264ab3]">•</span> Recepción del anticipo</li>
            <li><span class="text-[#264ab3]">•</span> Recepción de información y materiales necesarios por parte del cliente</li>
        </ul>
        <p class="mb-1">Los tiempos podrán modificarse si:</p>
        <ul class="list-none pl-3 space-y-0.5">
            <li><span class="text-[#264ab3]">•</span> EL CLIENTE retrasa entrega de contenido</li>
            <li><span class="text-[#264ab3]">•</span> Se solicitan cambios fuera del alcance inicial</li>
            <li><span class="text-[#264ab3]">•</span> Existen causas de fuerza mayor</li>
        </ul>
    </div>

    <!-- CUARTA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Cuarta. Alcance del Servicio</p>
        <p class="mb-2">El servicio incluye únicamente los conceptos establecidos en la cotización aceptada.</p>
        <p>Cualquier funcionalidad, modificación, integración o servicio adicional no contemplado será considerado trabajo extra y podrá generar una nueva cotización.</p>
    </div>

    <!-- QUINTA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Quinta. Cambios y Revisiones</p>
        <p class="mb-2">EL CLIENTE tendrá derecho a <strong>2 rondas de ajustes menores</strong>, siempre que estén relacionados con el alcance contratado.</p>
        <p>Cambios que alteren estructura, funcionalidades o nuevos requerimientos podrán generar costos adicionales.</p>
    </div>

    <!-- SEXTA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Sexta. Propiedad Intelectual</p>
        <p class="mb-2">Los derechos del trabajo desarrollado serán transferidos a EL CLIENTE una vez liquidado el monto total del contrato.</p>
        <p>EL PRESTADOR podrá mostrar el proyecto en su portafolio, redes sociales o material promocional, salvo solicitud escrita en contrario.</p>
    </div>

    <!-- SÉPTIMA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Séptima. Dominios, Hosting y Servicios de Terceros</p>
        <p class="mb-2">Los costos relacionados con dominio, hosting, servicios de correo, APIs, licencias, plataformas externas y publicidad pagada podrán requerir pagos adicionales y/o renovaciones periódicas.</p>
        <p>EL PRESTADOR no será responsable por fallas atribuibles a terceros.</p>
    </div>

    <!-- OCTAVA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Octava. Cancelación</p>
        <p class="mb-2">En caso de cancelación por parte de EL CLIENTE:</p>
        <ul class="list-none pl-3 space-y-0.5">
            <li><span class="text-[#264ab3]">•</span> El anticipo no será reembolsable.</li>
            <li><span class="text-[#264ab3]">•</span> Los trabajos ya realizados deberán ser cubiertos proporcionalmente.</li>
        </ul>
    </div>

    <!-- NOVENA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Novena. Soporte y Garantía</p>
        <p class="mb-2">EL PRESTADOR brindará soporte técnico por <strong>30 días</strong> posteriores a la entrega final para corrección de errores atribuibles al desarrollo original.</p>
        <p class="mb-1">No incluye:</p>
        <ul class="list-none pl-3 space-y-0.5">
            <li><span class="text-[#264ab3]">•</span> Nuevas funcionalidades</li>
            <li><span class="text-[#264ab3]">•</span> Modificaciones posteriores</li>
            <li><span class="text-[#264ab3]">•</span> Errores causados por terceros</li>
        </ul>
    </div>

    <!-- DÉCIMA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Décima. Aceptación</p>
        <p>Las partes aceptan el presente contrato y reconocen que la firma electrónica o aceptación digital tendrá validez legal equivalente a firma autógrafa, de conformidad con la legislación aplicable en materia de comercio electrónico.</p>
    </div>

    <!-- Signature block -->
    <div class="grid grid-cols-2 gap-8 mt-6 pt-6 border-t border-gray-200">
        <div class="space-y-1">
            <p class="font-bold uppercase text-[#264ab3] text-xs tracking-wider mb-2">El Prestador</p>
            <p>Nombre: <strong>Luna Avalos</strong></p>
            <p>Sitio web: {{ cd.providerSite }}</p>
            <p class="mt-8 pt-2 border-t border-gray-300 text-xs text-gray-500">Firma autógrafa</p>
        </div>
        <div class="space-y-1">
            <p class="font-bold uppercase text-[#264ab3] text-xs tracking-wider mb-2">El Cliente</p>
            <p>Nombre: <strong>{{ cd.clientName }}</strong></p>
            <p v-if="cd.legalRep">Representante: {{ cd.legalRep }}</p>
            <p>RFC: {{ cd.taxId }}</p>
            <template v-if="cd.signedAt">
                <p class="mt-4 pt-2 border-t border-gray-300 text-xs text-emerald-700 font-medium">Firmado electrónicamente</p>
                <p class="text-xs text-gray-400">{{ cd.signedAt }}</p>
                <p class="text-xs text-gray-400">IP: {{ cd.signatureIp }}</p>
            </template>
            <p v-else class="mt-8 pt-2 border-t border-gray-300 text-xs text-gray-500">Firma electrónica pendiente</p>
        </div>
    </div>
</div>`
});
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

<!-- ContractBody is defined inline in <script setup> above -->
<!--
<div class="space-y-5 text-justify">
    <!-- Parties -->
    <div class="bg-gray-50 border-l-4 border-[#264ab3] p-4 rounded-r-lg space-y-1">
        <p>Entre:</p>
        <p><strong>{{ cd.providerName }} / {{ cd.providerSite }}</strong> <em>("EL PRESTADOR")</em></p>
        <p>y</p>
        <p><strong>{{ cd.clientName }}</strong> <em>("EL CLIENTE")</em></p>
        <p class="mt-2">Se celebra el presente Contrato de Prestación de Servicios bajo las siguientes cláusulas:</p>
    </div>

    <!-- PRIMERA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Primera. Objeto del Contrato</p>
        <p class="mb-2">EL PRESTADOR se compromete a desarrollar y/o prestar los servicios solicitados por EL CLIENTE conforme a la cotización aceptada No. <strong>{{ cd.quoteId }}</strong>, la cual forma parte integral del presente contrato.</p>
        <p class="font-semibold mb-1">Servicios contratados:</p>
        <ul class="list-none space-y-0.5 pl-3">
            <li v-for="item in cd.items" :key="item.id" class="flex gap-2">
                <span class="text-[#264ab3]">•</span>
                <span>{{ item.concept }}<span v-if="item.description" class="text-gray-400 font-sans"> — {{ item.description }}</span></span>
            </li>
        </ul>
    </div>

    <!-- SEGUNDA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Segunda. Monto y Forma de Pago</p>
        <p class="mb-2">El monto total de los servicios es:</p>
        <ul class="list-none pl-3 space-y-0.5 mb-3">
            <li><span class="text-[#264ab3]">•</span> Subtotal: <strong>{{ cd.subtotal }} MXN</strong></li>
            <li><span class="text-[#264ab3]">•</span> IVA: <strong>{{ cd.iva }} MXN</strong></li>
            <li><span class="text-[#264ab3]">•</span> Total: <strong>{{ cd.total }} MXN</strong></li>
        </ul>
        <p class="font-semibold mb-1">Forma de pago:</p>
        <ul class="list-none pl-3 space-y-0.5 mb-3">
            <li><span class="text-[#264ab3]">•</span> Anticipo: <strong>{{ cd.anticipo }}</strong></li>
            <li><span class="text-[#264ab3]">•</span> Mensualidades ({{ cd.months }} meses): <strong>{{ cd.monthly }}/mes</strong></li>
        </ul>
        <p>Los trabajos iniciarán una vez confirmado el anticipo correspondiente.</p>
    </div>

    <!-- TERCERA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Tercera. Tiempos de Entrega</p>
        <p class="mb-2">El tiempo estimado para la entrega será de <strong>15 días hábiles</strong>, contados a partir de:</p>
        <ul class="list-none pl-3 space-y-0.5 mb-3">
            <li><span class="text-[#264ab3]">•</span> Recepción del anticipo</li>
            <li><span class="text-[#264ab3]">•</span> Recepción de información y materiales necesarios por parte del cliente</li>
        </ul>
        <p class="mb-1">Los tiempos podrán modificarse si:</p>
        <ul class="list-none pl-3 space-y-0.5">
            <li><span class="text-[#264ab3]">•</span> EL CLIENTE retrasa entrega de contenido</li>
            <li><span class="text-[#264ab3]">•</span> Se solicitan cambios fuera del alcance inicial</li>
            <li><span class="text-[#264ab3]">•</span> Existen causas de fuerza mayor</li>
        </ul>
    </div>

    <!-- CUARTA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Cuarta. Alcance del Servicio</p>
        <p class="mb-2">El servicio incluye únicamente los conceptos establecidos en la cotización aceptada.</p>
        <p>Cualquier funcionalidad, modificación, integración o servicio adicional no contemplado será considerado trabajo extra y podrá generar una nueva cotización.</p>
    </div>

    <!-- QUINTA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Quinta. Cambios y Revisiones</p>
        <p class="mb-2">EL CLIENTE tendrá derecho a <strong>2 rondas de ajustes menores</strong>, siempre que estén relacionados con el alcance contratado.</p>
        <p>Cambios que alteren estructura, funcionalidades o nuevos requerimientos podrán generar costos adicionales.</p>
    </div>

    <!-- SEXTA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Sexta. Propiedad Intelectual</p>
        <p class="mb-2">Los derechos del trabajo desarrollado serán transferidos a EL CLIENTE una vez liquidado el monto total del contrato.</p>
        <p>EL PRESTADOR podrá mostrar el proyecto en su portafolio, redes sociales o material promocional, salvo solicitud escrita en contrario.</p>
    </div>

    <!-- SÉPTIMA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Séptima. Dominios, Hosting y Servicios de Terceros</p>
        <p class="mb-2">Los costos relacionados con dominio, hosting, servicios de correo, APIs, licencias, plataformas externas y publicidad pagada podrán requerir pagos adicionales y/o renovaciones periódicas.</p>
        <p>EL PRESTADOR no será responsable por fallas atribuibles a terceros.</p>
    </div>

    <!-- OCTAVA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Octava. Cancelación</p>
        <p class="mb-2">En caso de cancelación por parte de EL CLIENTE:</p>
        <ul class="list-none pl-3 space-y-0.5">
            <li><span class="text-[#264ab3]">•</span> El anticipo no será reembolsable.</li>
            <li><span class="text-[#264ab3]">•</span> Los trabajos ya realizados deberán ser cubiertos proporcionalmente.</li>
        </ul>
    </div>

    <!-- NOVENA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Novena. Soporte y Garantía</p>
        <p class="mb-2">EL PRESTADOR brindará soporte técnico por <strong>30 días</strong> posteriores a la entrega final para corrección de errores atribuibles al desarrollo original.</p>
        <p class="mb-1">No incluye:</p>
        <ul class="list-none pl-3 space-y-0.5">
            <li><span class="text-[#264ab3]">•</span> Nuevas funcionalidades</li>
            <li><span class="text-[#264ab3]">•</span> Modificaciones posteriores</li>
            <li><span class="text-[#264ab3]">•</span> Errores causados por terceros</li>
        </ul>
    </div>

    <!-- DÉCIMA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Décima. Aceptación</p>
        <p>Las partes aceptan el presente contrato y reconocen que la firma electrónica o aceptación digital tendrá validez legal equivalente a firma autógrafa, de conformidad con la legislación aplicable en materia de comercio electrónico.</p>
    </div>

    <!-- Signature block -->
    <div class="grid grid-cols-2 gap-8 mt-6 pt-6 border-t border-gray-200">
        <div class="space-y-1">
            <p class="font-bold uppercase text-[#264ab3] text-xs tracking-wider mb-2">El Prestador</p>
            <p>Nombre: <strong>Luna Avalos</strong></p>
            <p>Sitio web: {{ cd.providerSite }}</p>
            <p class="mt-8 pt-2 border-t border-gray-300 text-xs text-gray-500">Firma autógrafa</p>
        </div>
        <div class="space-y-1">
            <p class="font-bold uppercase text-[#264ab3] text-xs tracking-wider mb-2">El Cliente</p>
            <p>Nombre: <strong>{{ cd.clientName }}</strong></p>
            <p v-if="cd.legalRep">Representante: {{ cd.legalRep }}</p>
            <p>RFC: {{ cd.taxId }}</p>
            <template v-if="cd.signedAt">
                <p class="mt-4 pt-2 border-t border-gray-300 text-xs text-emerald-700 font-medium">Firmado electrónicamente</p>
                <p class="text-xs text-gray-400">{{ cd.signedAt }}</p>
                <p class="text-xs text-gray-400">IP: {{ cd.signatureIp }}</p>
            </template>
            <p v-else class="mt-8 pt-2 border-t border-gray-300 text-xs text-gray-500">Firma electrónica pendiente</p>
        </div>
    </div>
</div>`
});
</script>
