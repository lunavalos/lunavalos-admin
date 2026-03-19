<script setup>
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    settings: Object,
});

const form = useForm({
    contract_template: props.settings.contract_template || '',
    company_legal_name: props.settings.company_legal_name || '',
    company_commercial_name: props.settings.company_commercial_name || '',
    company_rfc: props.settings.company_rfc || '',
    company_address: props.settings.company_address || '',
    company_commercial_address: props.settings.company_commercial_address || '',
    company_work_days: props.settings.company_work_days || '',
    company_work_start: props.settings.company_work_start || '',
    company_work_end: props.settings.company_work_end || '',
    company_email: props.settings.company_email || '',
    company_phone: props.settings.company_phone || '',
    company_logo: null,
    company_fb: props.settings.company_fb || '',
    company_x: props.settings.company_x || '',
    company_linkedin: props.settings.company_linkedin || '',
    company_ig: props.settings.company_ig || '',
    company_tiktok: props.settings.company_tiktok || '',
    company_yt: props.settings.company_yt || '',
});

const logoPreview = ref(props.settings.company_logo ? `/storage/${props.settings.company_logo}` : null);

const onLogoChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.company_logo = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            logoPreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const page = usePage();

const submit = () => {
    form.post(route('settings.update'), {
        preserveScroll: true,
    });
};
const isTab = (tabName) => {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab') || 'empresa';
    return tab === tabName;
};
</script>

<template>
    <Head title="Ajustes" />

    <SettingsLayout>
        <template #title>
            Datos de la Empresa y Contrato
        </template>

        <div class="py-12">
            <div class="container mx-auto">


                <!-- Tab: Empresa -->
                <div v-if="isTab('empresa')" class="card bg-white shadow-sm sm:rounded-lg mb-6">
                    <div class="border-b border-gray-200 pb-4 mb-6">
                        <h3 class="text-lg font-bold text-[#264ab3]">Plantilla del Contrato Legal</h3>
                        <p class="text-sm text-gray-500 mt-1">Configura el texto y la estructura legal para cuando un cliente acepte una cotización.</p>
                    </div>

                    <!-- Usage variables infobox (moved to Contrato tab) -->
                    <form @submit.prevent="submit" class="space-y-6 lg:p-6 p-4">
                        <!-- Company Data -->
                        <div>
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                                <!-- Columna Izquierda: Logo y Básicos -->
                                <div class="lg:col-span-1 border-r pr-6 space-y-6">
                                    <h4 class="text-md font-bold mb-4 text-gray-800 border-b pb-2">Identidad y Logo</h4>
                                    
                                    <div class="flex flex-col items-center">
                                        <div class="w-full h-40 bg-gray-50 border-2 border-dashed border-gray-200 rounded-xl flex items-center justify-center overflow-hidden mb-4 group relative">
                                            <img v-if="logoPreview" :src="logoPreview" class="max-w-full max-h-full object-contain p-2" />
                                            <div v-else class="text-gray-400 text-sm p-4 text-center">Sin logotipo seleccionado</div>
                                            
                                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                <label class="cursor-pointer text-white font-bold text-xs bg-white/20 px-4 py-2 rounded-lg backdrop-blur-md">
                                                    Cambiar Logo
                                                    <input type="file" @change="onLogoChange" class="hidden" accept="image/*" />
                                                </label>
                                            </div>
                                        </div>
                                        <p class="text-[10px] text-gray-400 text-center">Sube el logotipo de tu empresa (recomendado PNG transparente 512x512)</p>
                                    </div>

                                    <div>
                                        <InputLabel for="company_commercial_name" value="Nombre Comercial" />
                                        <input type="text" id="company_commercial_name" v-model="form.company_commercial_name" class="mt-1 block w-full border-gray-300 focus:border-[#264ab3] rounded-md shadow-sm text-sm" placeholder="Ej: LunAvalos Digital House">
                                        <InputError :message="form.errors.company_commercial_name" />
                                    </div>

                                    <div class="space-y-4">
                                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Horario Laboral</h4>
                                        <div>
                                            <InputLabel for="company_work_days" value="Días de Trabajo" />
                                            <input type="text" id="company_work_days" v-model="form.company_work_days" class="mt-1 block w-full border-gray-300 focus:border-[#264ab3] rounded-md shadow-sm text-sm" placeholder="Ej: Lunes a Viernes">
                                            <InputError :message="form.errors.company_work_days" />
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <InputLabel for="company_work_start" value="Hora Entrada" />
                                                <input type="time" id="company_work_start" v-model="form.company_work_start" class="mt-1 block w-full border-gray-300 focus:border-[#264ab3] rounded-md shadow-sm text-sm">
                                                <InputError :message="form.errors.company_work_start" />
                                            </div>
                                            <div>
                                                <InputLabel for="company_work_end" value="Hora Salida" />
                                                <input type="time" id="company_work_end" v-model="form.company_work_end" class="mt-1 block w-full border-gray-300 focus:border-[#264ab3] rounded-md shadow-sm text-sm">
                                                <InputError :message="form.errors.company_work_end" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Columna Central: Datos Fiscales y Contacto -->
                                <div class="lg:col-span-2 space-y-6">
                                    <h4 class="text-md font-bold mb-4 text-gray-800 border-b pb-2">Datos Legales y Contacto</h4>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <InputLabel for="company_legal_name" value="Razón Social Legal (Para Contratos)" />
                                            <input type="text" id="company_legal_name" v-model="form.company_legal_name" class="mt-1 block w-full border-gray-300 focus:border-[#264ab3] rounded-md shadow-sm text-sm">
                                            <InputError :message="form.errors.company_legal_name" />
                                        </div>
                                        <div>
                                            <InputLabel for="company_rfc" value="RFC" />
                                            <input type="text" id="company_rfc" v-model="form.company_rfc" class="mt-1 block w-full border-gray-300 focus:border-[#264ab3] rounded-md shadow-sm text-sm text-center font-mono uppercase">
                                            <InputError :message="form.errors.company_rfc" />
                                        </div>
                                        <div class="md:col-span-2">
                                            <InputLabel for="company_address" value="Dirección Legal / Fiscal" />
                                            <input type="text" id="company_address" v-model="form.company_address" class="mt-1 block w-full border-gray-300 focus:border-[#264ab3] rounded-md shadow-sm text-sm">
                                            <InputError :message="form.errors.company_address" />
                                        </div>
                                        <div class="md:col-span-2">
                                            <InputLabel for="company_commercial_address" value="Dirección Comercial (Física / Oficinas)" />
                                            <input type="text" id="company_commercial_address" v-model="form.company_commercial_address" class="mt-1 block w-full border-gray-300 focus:border-[#264ab3] rounded-md shadow-sm text-sm">
                                            <InputError :message="form.errors.company_commercial_address" />
                                        </div>
                                        <div>
                                            <InputLabel for="company_email" value="Email Principal" />
                                            <input type="email" id="company_email" v-model="form.company_email" class="mt-1 block w-full border-gray-300 focus:border-[#264ab3] rounded-md shadow-sm text-sm">
                                            <InputError :message="form.errors.company_email" />
                                        </div>
                                        <div>
                                            <InputLabel for="company_phone" value="Teléfono Principal" />
                                            <input type="text" id="company_phone" v-model="form.company_phone" class="mt-1 block w-full border-gray-300 focus:border-[#264ab3] rounded-md shadow-sm text-sm">
                                            <InputError :message="form.errors.company_phone" />
                                        </div>
                                    </div>

                                    <h4 class="text-md font-bold mb-4 text-gray-800 border-b pb-2 pt-4">Redes Sociales</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <InputLabel value="Facebook" />
                                            <div class="mt-1 flex rounded-md shadow-sm">
                                                <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-xs">fb.com/</span>
                                                <input type="text" v-model="form.company_fb" class="flex-1 block w-full border-gray-300 focus:border-[#264ab3] rounded-none rounded-r-md text-sm">
                                            </div>
                                        </div>
                                        <div>
                                            <InputLabel value="Instagram" />
                                            <div class="mt-1 flex rounded-md shadow-sm">
                                                <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-xs text-[10px]">@</span>
                                                <input type="text" v-model="form.company_ig" class="flex-1 block w-full border-gray-300 focus:border-[#264ab3] rounded-none rounded-r-md text-sm">
                                            </div>
                                        </div>
                                        <div>
                                            <InputLabel value="LinkedIn" />
                                            <input type="text" v-model="form.company_linkedin" class="mt-1 block w-full border-gray-300 focus:border-[#264ab3] rounded-md shadow-sm text-sm" placeholder="URL completa">
                                        </div>
                                        <div>
                                            <InputLabel value="Tik Tok" />
                                            <input type="text" v-model="form.company_tiktok" class="mt-1 block w-full border-gray-300 focus:border-[#264ab3] rounded-md shadow-sm text-sm" placeholder="@usuario">
                                        </div>
                                        <div>
                                            <InputLabel value="YouTube" />
                                            <input type="text" v-model="form.company_yt" class="mt-1 block w-full border-gray-300 focus:border-[#264ab3] rounded-md shadow-sm text-sm" placeholder="Canal">
                                        </div>
                                        <div>
                                            <InputLabel value="X (Twitter)" />
                                            <input type="text" v-model="form.company_x" class="mt-1 block w-full border-gray-300 focus:border-[#264ab3] rounded-md shadow-sm text-sm" placeholder="@usuario">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4 border-t mt-6">
                            <button
                                type="submit"
                                class="bg-[#264ab3] hover:bg-[#193074] text-white px-8 py-3 rounded-lg font-bold shadow-md transition"
                                :class="{'opacity-50': form.processing}"
                                :disabled="form.processing"
                            >
                                Guardar Datos de Empresa
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tab: Contrato -->
                <div v-if="isTab('contrato')" class="card bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b border-gray-200 pb-4 mb-6">
                        <h3 class="text-lg font-bold text-[#264ab3]">Plantilla del Contrato Legal</h3>
                        <p class="text-sm text-gray-500 mt-1">Configura el texto y la estructura legal para cuando un cliente acepte una cotización.</p>
                    </div>

                    <!-- Usage variables infobox -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-sm text-blue-800">
                        <p class="font-bold mb-2">Variables Especiales Mágicas:</p>
                        <p class="mb-1">Cualquier palabra dentro de corchetes <code>[ ]</code> será reemplazada automáticamente con los datos del cliente:</p>
                        <ul class="list-disc pl-5 mt-2 font-mono text-xs space-y-1">
                            <li>[fecha] = Fecha en que se firma el contrato.</li>
                            <li>[razon_social] = Razón social / Nombre legal del cliente.</li>
                            <li>[representante] = Representante legal.</li>
                            <li>[direccion] = Dirección fiscal y C.P.</li>
                            <li>[rfc] = Registro Federal de Contribuyentes.</li>
                            <li>[duracion] = Tiempo de compromiso (Ej: 6 meses).</li>
                            <li>[lista_servicios] = Genera el listado completo de servicios de la cotización.</li>
                            <li>[inversion_inicial] = Genera el texto con el total del pago único.</li>
                            <li>[inversion_mensual] = Genera el texto con el total de iguala mensual.</li>
                        </ul>
                        <p class="font-bold mb-2 mt-4 text-purple-800">Variables de tu Empresa:</p>
                        <ul class="list-disc pl-5 font-mono text-xs space-y-1 text-purple-800">
                            <li>[mi_razon_social] = Tu razón social.</li>
                            <li>[mi_rfc] = Tu RFC.</li>
                            <li>[mi_direccion] = Tu dirección.</li>
                            <li>[mi_email] = Tu correo electrónico.</li>
                            <li>[mi_telefono] = Tu teléfono.</li>
                        </ul>
                        <p class="mt-4 text-xs italic">*Nota: Los asteriscos dobles <code>**texto**</code> se transformarán en <strong>negritas</strong> en el contrato final.</p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-6 lg:p-6 p-4">
                        <div class="mt-8 pt-4 border-t">
                            <InputLabel for="contract_template" value="Cuerpo del Contrato" class="font-bold text-gray-700 block mb-2" />
                            <textarea
                                id="contract_template"
                                v-model="form.contract_template"
                                rows="25"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm font-serif text-sm leading-relaxed"
                                placeholder="..."
                            ></textarea>
                            <InputError class="mt-2" :message="form.errors.contract_template" />
                        </div>

                        <div class="flex justify-end pt-4 border-t">
                            <button
                                type="submit"
                                class="bg-[#264ab3] hover:bg-[#193074] text-white px-8 py-3 rounded-lg font-bold shadow-md transition"
                                :class="{'opacity-50': form.processing}"
                                :disabled="form.processing"
                            >
                                Guardar Plantilla
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </SettingsLayout>
</template>
