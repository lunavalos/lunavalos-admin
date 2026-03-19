<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { 
    UserIcon, 
    BriefcaseIcon, 
    AtSymbolIcon, 
    PhoneIcon, 
    GlobeAltIcon,
    PhotoIcon,
    ClipboardIcon,
    CheckIcon,
    SwatchIcon,
    PencilSquareIcon
} from '@heroicons/vue/24/outline';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
    templates: Array
});

const page = usePage();
const client = page.props.auth.user.client || {};

const formData = ref({
    name: page.props.auth.user.name || '',
    position: '',
    email: page.props.auth.user.email || '',
    phone: '',
    website: '',
    logo: '/logo.svg', // Default project logo
    photo: 'https://ui-avatars.com/api/?name=' + encodeURIComponent(page.props.auth.user.name) + '&background=264ab3&color=fff',
    facebook: '',
    instagram: '',
    linkedin: '',
    twitter: '',
    primary_color: '#264ab3',
    secondary_color: '#666666',
    template_id: props.templates.length > 0 ? props.templates[0].id : null
});

const selectedTemplate = computed(() => {
    return props.templates.find(t => t.id === formData.value.template_id) || props.templates[0];
});

const renderedSignature = computed(() => {
    if (!selectedTemplate.value) return '';
    
    let html = selectedTemplate.value.html_content;
    const data = formData.value;
    
    html = html.replace(/\{\{name\}\}/g, data.name || 'Nombre del Empleado');
    html = html.replace(/\{\{position\}\}/g, data.position || 'Puesto / Cargo');
    html = html.replace(/\{\{email\}\}/g, data.email || 'correo@empresa.com');
    html = html.replace(/\{\{phone\}\}/g, data.phone || '123 456 7890');
    html = html.replace(/\{\{website\}\}/g, data.website || 'www.tuempresa.com');
    html = html.replace(/\{\{logo\}\}/g, data.logo);
    html = html.replace(/\{\{photo\}\}/g, data.photo);
    html = html.replace(/\{\{primary_color\}\}/g, data.primary_color);
    html = html.replace(/\{\{secondary_color\}\}/g, data.secondary_color);

    // Social Links
    let socialHtml = '<div style="margin-top: 10px;">';
    if (data.facebook) socialHtml += `<a href="${data.facebook}" style="margin-right: 8px;"><img src="https://cdn-icons-png.flaticon.com/32/733/733547.png" width="20" style="display:inline-block"></a>`;
    if (data.instagram) socialHtml += `<a href="${data.instagram}" style="margin-right: 8px;"><img src="https://cdn-icons-png.flaticon.com/32/2111/2111463.png" width="20" style="display:inline-block"></a>`;
    if (data.linkedin) socialHtml += `<a href="${data.linkedin}" style="margin-right: 8px;"><img src="https://cdn-icons-png.flaticon.com/32/3536/3536505.png" width="20" style="display:inline-block"></a>`;
    if (data.twitter) socialHtml += `<a href="${data.twitter}" style="margin-right: 8px;"><img src="https://cdn-icons-png.flaticon.com/32/3256/3256609.png" width="20" style="display:inline-block"></a>`;
    socialHtml += '</div>';

    if (!data.facebook && !data.instagram && !data.linkedin && !data.twitter) {
        socialHtml = '';
    }

    html = html.replace(/\{\{social_links\}\}/g, socialHtml);
    
    return html;
});

const copied = ref(false);
const copySignature = () => {
    const el = document.getElementById('signature-preview-container');
    const range = document.createRange();
    range.selectNode(el);
    window.getSelection().removeAllRanges();
    window.getSelection().addRange(range);
    
    try {
        document.execCommand('copy');
        copied.value = true;
        setTimeout(() => copied.value = false, 2000);
    } catch (err) {
        console.error('Cant copy', err);
    }
    
    window.getSelection().removeAllRanges();
};

const handleFileUpload = (event, type) => {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            formData.value[type] = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

onMounted(() => {
    // Try to get more info from client object if available
    if (client.business_name) {
        formData.value.website = client.website || '';
    }
});
</script>

<template>
    <Head title="Generador de Firma" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 flex items-center gap-2">
                <SwatchIcon class="h-6 w-6 text-gray-500" />
                Generador de Firma de Correo
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    
                    <!-- Left Column: Form -->
                    <div class="lg:col-span-5 space-y-6">
                        <div class="bg-white p-6 shadow-sm sm:rounded-lg border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center">
                                <SwatchIcon class="h-5 w-5 mr-2 text-[#264ab3]" />
                                Tus Datos
                            </h3>
                            
                            <div class="space-y-4">
                                <!-- Template Selection -->
                                <div v-if="templates.length > 1">
                                    <InputLabel value="Selecciona una Plantilla" />
                                    <select v-model="formData.template_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md shadow-sm">
                                        <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                                    </select>
                                </div>

                                <div>
                                    <InputLabel for="name" value="Nombre Completo" />
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <UserIcon class="h-4 w-4 text-gray-400" />
                                        </div>
                                        <TextInput id="name" type="text" v-model="formData.name" class="block w-full pl-10 sm:text-sm" />
                                    </div>
                                </div>

                                <div>
                                    <InputLabel for="position" value="Puesto o Cargo" />
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <BriefcaseIcon class="h-4 w-4 text-gray-400" />
                                        </div>
                                        <TextInput id="position" type="text" v-model="formData.position" placeholder="Ej. Gerente de Ventas" class="block w-full pl-10 sm:text-sm" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel for="email" value="Correo Electrónico" />
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <AtSymbolIcon class="h-4 w-4 text-gray-400" />
                                            </div>
                                            <TextInput id="email" type="email" v-model="formData.email" class="block w-full pl-10 sm:text-sm" />
                                        </div>
                                    </div>
                                    <div>
                                        <InputLabel for="phone" value="Teléfono" />
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <PhoneIcon class="h-4 w-4 text-gray-400" />
                                            </div>
                                            <TextInput id="phone" type="text" v-model="formData.phone" placeholder="123 456 7890" class="block w-full pl-10 sm:text-sm" />
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <InputLabel for="website" value="Sitio Web" />
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <GlobeAltIcon class="h-4 w-4 text-gray-400" />
                                        </div>
                                        <TextInput id="website" type="text" v-model="formData.website" placeholder="www.absolutegroup.com" class="block w-full pl-10 sm:text-sm" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel value="Logo Empresa" />
                                        <div class="mt-1 flex items-center space-x-2">
                                            <label class="cursor-pointer bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-xs flex items-center hover:bg-gray-100">
                                                <PhotoIcon class="h-4 w-4 mr-1 text-gray-500" />
                                                Subir
                                                <input type="file" @change="handleFileUpload($event, 'logo')" class="hidden" accept="image/*" />
                                            </label>
                                            <div class="h-8 w-8 bg-gray-100 rounded border border-gray-200 p-1">
                                                <img :src="formData.logo" class="h-full w-full object-contain" />
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <InputLabel value="Foto Perfil" />
                                        <div class="mt-1 flex items-center space-x-2">
                                            <label class="cursor-pointer bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-xs flex items-center hover:bg-gray-100">
                                                <PhotoIcon class="h-4 w-4 mr-1 text-gray-500" />
                                                Subir
                                                <input type="file" @change="handleFileUpload($event, 'photo')" class="hidden" accept="image/*" />
                                            </label>
                                            <div class="h-8 w-8 bg-gray-100 rounded-full border border-gray-200 overflow-hidden">
                                                <img :src="formData.photo" class="h-full w-full object-cover" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="border-t pt-4 mt-6">
                                    <h4 class="text-sm font-bold text-gray-700 mb-3">Colores de Marca</h4>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <InputLabel value="Color Principal" />
                                            <div class="mt-1 flex items-center">
                                                <input type="color" v-model="formData.primary_color" class="h-10 w-full rounded border border-gray-300 p-1 bg-white cursor-pointer" />
                                            </div>
                                        </div>
                                        <div>
                                            <InputLabel value="Color Secundario" />
                                            <div class="mt-1 flex items-center">
                                                <input type="color" v-model="formData.secondary_color" class="h-10 w-full rounded border border-gray-300 p-1 bg-white cursor-pointer" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="border-t pt-4 mt-6">
                                    <h4 class="text-sm font-bold text-gray-700 mb-3">Redes Sociales (URLs opcionales)</h4>
                                    <div class="grid grid-cols-2 gap-3">
                                        <TextInput v-model="formData.facebook" placeholder="Facebook URL" class="text-xs" />
                                        <TextInput v-model="formData.instagram" placeholder="Instagram URL" class="text-xs" />
                                        <TextInput v-model="formData.linkedin" placeholder="LinkedIn URL" class="text-xs" />
                                        <TextInput v-model="formData.twitter" placeholder="Twitter (X) URL" class="text-xs" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Preview & Action -->
                    <div class="lg:col-span-7 space-y-6">
                        <div class="bg-white p-6 shadow-sm sm:rounded-lg border border-gray-200 h-full">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-lg font-bold text-gray-800">Vista Previa</h3>
                                <button @click="copySignature" class="bg-[#264ab3] text-white px-6 py-2 rounded-full font-bold text-sm flex items-center hover:bg-blue-800 transition-colors shadow-lg">
                                    <CheckIcon v-if="copied" class="h-4 w-4 mr-2" />
                                    <ClipboardIcon v-else class="h-4 w-4 mr-2" />
                                    {{ copied ? '¡Copiado!' : 'Copiar Firma' }}
                                </button>
                            </div>
                            
                            <div class="bg-gray-50 rounded-xl p-8 border border-dashed border-gray-300 min-h-[300px] flex items-center justify-center relative overflow-auto">
                                <div id="signature-preview-container" v-html="renderedSignature" class="bg-white p-4 shadow-sm inline-block"></div>
                            </div>
                            
                            <div class="mt-6">
                                <h4 class="text-xs font-bold text-gray-500 uppercase mb-3">Instrucciones:</h4>
                                <ul class="text-xs text-gray-600 space-y-2 list-disc pl-4">
                                    <li>Llene los datos en el formulario de la izquierda.</li>
                                    <li>Revise que la vista previa sea correcta.</li>
                                    <li>Haga clic en <b>"Copiar Firma"</b>.</li>
                                    <li>Vaya a su cliente de correo (Outlook, Gmail, Apple Mail).</li>
                                    <li>En la configuración de "Firma", simplemente <b>Pegue (Ctrl+V o Cmd+V)</b>.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
#signature-preview-container :deep(table) {
    margin: 0 auto;
}
</style>
