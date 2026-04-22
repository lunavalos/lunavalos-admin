<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

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

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(value);
};

const processedContract = computed(() => {
    let template = props.settings.contract_template || '';
    
    // Derived values
    const serviceList = props.contract.quote.items
        .map(item => `• ${item.concept}${item.description ? ': ' + item.description : ''}`)
        .join('\n');
        
    const initialInvestment = props.contract.quote.items
        .filter(i => i.billing_type === 'unique')
        .reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
        
    const monthlyInvestment = props.contract.quote.items
        .filter(i => i.billing_type === 'monthly')
        .reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
        
    const replacements = {
        'mi_razon_social': props.settings.company_legal_name || 'LUN AVALOS',
        'mi_rfc': props.settings.company_rfc || '',
        'mi_direccion': props.settings.company_address || '',
        'mi_email': props.settings.company_email || '',
        'mi_telefono': props.settings.company_phone || '',
        'razon_social': form.legal_name || '[Razón Social]',
        'representante': form.legal_representative || form.legal_name || '[Representante]',
        'direccion': form.fiscal_address || '[Dirección]',
        'rfc': form.tax_id || '[RFC]',
        'codigo_postal': form.postal_code || '[C.P.]',
        'fecha': new Date().toLocaleDateString('es-MX'),
        'lista_servicios': serviceList,
        'inversion_inicial': initialInvestment > 0 ? `Inversión Inicial: ${formatCurrency(initialInvestment)} MXN` : '',
        'inversion_mensual': monthlyInvestment > 0 ? `Inversión Mensual: ${formatCurrency(monthlyInvestment)} MXN` : '',
        'duracion': props.contract.quote.duration || 'Por definir'
    };

    for (let key in replacements) {
        const regex = new RegExp('\\[' + key + '\\]', 'g');
        template = template.replace(regex, replacements[key] || '');
    }

    // Basic Markdown-ish to HTML for preview
    // 1. Headers
    template = template.replace(/^# (.*$)/gm, '<h1 class="text-xl font-bold mb-6 uppercase text-center">$1</h1>');
    template = template.replace(/^### (.*$)/gm, '<h3 class="text-lg font-bold mt-6 mb-2">$1</h3>');
    // 2. Bold
    template = template.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    // 3. Line breaks
    template = template.replace(/\n/g, '<br/>');

    return template;
});

const proceedToContract = () => {
    // Basic validation before showing contract
    // legal_representative is optional if RFC (tax_id) has 13 chars (Persona Física)
    const isPersonaMoral = form.tax_id.length === 12;
    const isMissingData = !form.legal_name || !form.tax_id || !form.fiscal_address || !form.postal_code || !form.csf_file || (isPersonaMoral && !form.legal_representative);

    if (isMissingData) {
        alert("Por favor completa todos tus datos fiscales (incluyendo representante legal si eres persona moral) y adjunta tu CSF antes de proceder.");
        return;
    }
    isReadingContract.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const submit = () => {
    if (!form.accept_terms) {
        alert("Debes aceptar los términos y condiciones para continuar.");
        return;
    }
    form.post(route('contracts.sign', props.contract.token));
};

</script>

<template>
    <Head title="Contrato de Servicio" />

    <div class="min-h-screen bg-gray-100 flex flex-col justify-center items-center pt-6 sm:pt-0 pb-12">
        <div class="w-full max-w-4xl mt-6 px-6 py-8 bg-white shadow-xl overflow-hidden sm:rounded-xl">
            <!-- Header -->
            <div class="flex flex-col items-center mb-8 border-b pb-6">
                <ApplicationLogo class="w-auto h-20 fill-current text-gray-800 mb-4" />
                <h2 class="text-2xl font-bold text-gray-800 text-center">Formalización de Proyecto</h2>
                <p class="text-gray-500 mt-2 text-center" v-if="contract.status === 'pending'">
                    Estás a un paso de iniciar trabajar con LUN AVALOS.
                </p>
                <div v-else class="mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg text-center font-bold">
                    ¡Gracias! Este contrato ya ha sido firmado exitosamente.
                </div>
            </div>

            <div v-if="contract.status === 'pending'">
                <!-- Step 1: Fiscal Data -->
                <div v-show="!isReadingContract" class="space-y-6">
                    <div class="bg-blue-50 p-4 border border-blue-200 rounded-lg text-sm text-blue-800 mb-6">
                        <strong>Paso 1:</strong> Para poder generar tu contrato legal y emitir facturas, por favor ayúdanos a completar tus datos fiscales.
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <InputLabel for="legal_name" value="Razón Social o Nombre Legal" />
                            <TextInput
                                id="legal_name"
                                type="text"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                v-model="form.legal_name"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.legal_name" />
                        </div>

                        <div>
                            <InputLabel for="tax_id" value="RFC" />
                            <TextInput
                                id="tax_id"
                                type="text"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                v-model="form.tax_id"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.tax_id" />
                        </div>

                        <div class="md:col-span-2">
                            <InputLabel for="fiscal_address" value="Dirección Fiscal Completa" />
                            <TextInput
                                id="fiscal_address"
                                type="text"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                v-model="form.fiscal_address"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.fiscal_address" />
                        </div>

                        <div>
                            <InputLabel for="postal_code" value="Código Postal" />
                            <TextInput
                                id="postal_code"
                                type="text"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                v-model="form.postal_code"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.postal_code" />
                        </div>

                        <div>
                            <InputLabel for="legal_representative" value="Nombre del Representante Legal" />
                            <TextInput
                                id="legal_representative"
                                type="text"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                v-model="form.legal_representative"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.legal_representative" />
                        </div>

                        <div class="md:col-span-2 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                            <InputLabel for="csf_file" value="Sube tu Constancia de Situación Fiscal (PDF o Imagen)" class="mb-2 font-bold" />
                            <input 
                                type="file" 
                                id="csf_file"
                                @change="e => form.csf_file = e.target.files[0]" 
                                class="block w-full text-sm text-zinc-500
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-full file:border-0
                                file:text-sm file:font-semibold
                                file:bg-indigo-50 file:text-indigo-700
                                hover:file:bg-indigo-100 cursor-pointer"
                                required
                                accept=".pdf,.png,.jpg,.jpeg"
                            />
                            <InputError class="mt-2" :message="form.errors.csf_file" />
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end border-t pt-6">
                        <button 
                            @click="proceedToContract" 
                            type="button"
                            class="bg-[#264ab3] hover:bg-[#193074] text-white px-8 py-3 rounded-lg font-bold shadow-md transition"
                        >
                            Leer Contrato y Continuar &rarr;
                        </button>
                    </div>
                </div>

                <!-- Step 2: Contract Preview & Sign -->
                <div v-show="isReadingContract" class="space-y-8">
                    <div class="bg-blue-50 p-4 border border-blue-200 rounded-lg text-sm text-blue-800 mb-6 flex justify-between items-center">
                        <span><strong>Paso 2:</strong> Revisa el contrato generado con tu información.</span>
                        <button @click="isReadingContract = false" class="text-blue-600 underline font-bold text-xs hover:text-blue-800">&larr; Volver a editar datos</button>
                    </div>

                    <!-- Dynamic Contract Visualization -->
                    <div class="bg-gray-50 p-8 border border-gray-300 rounded shadow-inner text-sm text-gray-800 leading-relaxed font-serif text-justify h-[600px] overflow-y-auto" v-html="processedContract">
                    </div>

                    <!-- Signature Area -->
                    <div class="border-t pt-8 mt-8">
                        <form @submit.prevent="submit" class="bg-gray-50 border border-gray-200 p-6 rounded-lg">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Firma Digital</h3>
                            <div class="mb-4">
                                <label class="flex items-start">
                                    <input type="checkbox" v-model="form.accept_terms" class="mt-1 bg-white border-gray-300 rounded shadow-sm text-green-600 focus:ring-green-500" />
                                    <span class="ml-3 text-sm text-gray-700">
                                        He leído y entendido todos los apartados de este contrato de servicios.
                                        Al marcar esta casilla, <strong>{{ form.legal_representative || form.legal_name }}</strong> acepta 
                                        {{ form.legal_representative ? 'en nombre de ' + form.legal_name : '' }} que este proceso de aceptación electrónica surte los mismos efectos 
                                        jurídicos que la firma autógrafa, de conformidad con la legislación aplicable en materia de comercio electrónico.
                                    </span>
                                </label>
                                <InputError class="mt-2" :message="form.errors.accept_terms" />
                            </div>

                            <div class="flex justify-end mt-6">
                                <button
                                    type="submit"
                                    class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg font-bold shadow-md transition"
                                    :class="{ 'opacity-50': form.processing }"
                                    :disabled="form.processing || !form.accept_terms"
                                >
                                    Firma y Aceptación de Contrato &rarr;
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

            <!-- Validation Errors or Success Msg -->
            <div v-if="$page.props.flash?.success" class="mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded mb-4">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded mb-4">
                {{ $page.props.flash.error }}
            </div>

        </div>
    </div>
</template>
