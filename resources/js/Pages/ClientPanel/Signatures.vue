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
    ArrowDownTrayIcon,
} from '@heroicons/vue/24/outline';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
    templates: Array,
    assignedTemplate: { type: Object, default: null },
    signatureDefaults: { type: Object, default: () => ({}) },
});

const page = usePage();
const authUser = page.props.auth.user;
const client = authUser.client || {};

// Si hay plantilla asignada, úsala. Si no, permite seleccionar de todas.
const hasAssigned = computed(() => !!props.assignedTemplate);

const formData = ref({
    name:            authUser.name || '',
    position:        '',
    email:           authUser.email || '',
    phone:           '',
    website:         props.signatureDefaults?.website || client.website || '',
    logo:            props.signatureDefaults?.logo || '/logo.svg',
    photo:           'https://ui-avatars.com/api/?name=' + encodeURIComponent(authUser.name) + '&background=264ab3&color=fff',
    facebook:        '',
    instagram:       '',
    linkedin:        '',
    twitter:         '',
    primary_color:   props.signatureDefaults?.primary_color   || '#264ab3',
    secondary_color: props.signatureDefaults?.secondary_color || '#666666',
    template_id:     props.assignedTemplate?.id ?? (props.templates.length > 0 ? props.templates[0].id : null),
});

const selectedTemplate = computed(() => {
    return props.templates.find(t => t.id === formData.value.template_id) || props.templates[0] || null;
});

// Campos activos según la plantilla seleccionada. Si fields es null → todos.
const ALL_FIELD_KEYS = ['name', 'position', 'email', 'phone', 'website', 'logo', 'photo', 'primary_color', 'secondary_color', 'social_links'];
const activeFields = computed(() => {
    const f = selectedTemplate.value?.fields;
    return f && f.length ? f : ALL_FIELD_KEYS;
});

const has = (field) => activeFields.value.includes(field);

const renderedSignature = computed(() => {
    if (!selectedTemplate.value) return '';

    let html = selectedTemplate.value.html_content;
    const d = formData.value;

    html = html.replace(/\{\{name\}\}/g, d.name || 'Nombre del Empleado');
    html = html.replace(/\{\{position\}\}/g, d.position || 'Puesto / Cargo');
    html = html.replace(/\{\{email\}\}/g, d.email || 'correo@empresa.com');
    html = html.replace(/\{\{phone\}\}/g, d.phone || '123 456 7890');
    html = html.replace(/\{\{website\}\}/g, d.website || 'www.tuempresa.com');
    html = html.replace(/\{\{logo\}\}/g, d.logo);
    html = html.replace(/\{\{photo\}\}/g, d.photo);
    html = html.replace(/\{\{primary_color\}\}/g, d.primary_color);
    html = html.replace(/\{\{secondary_color\}\}/g, d.secondary_color);

    let socialHtml = '';
    if (has('social_links') && (d.facebook || d.instagram || d.linkedin || d.twitter)) {
        socialHtml = '<div style="margin-top: 10px;">';
        if (d.facebook)  socialHtml += `<a href="${d.facebook}" style="margin-right: 8px;"><img src="https://cdn-icons-png.flaticon.com/32/733/733547.png" width="20" style="display:inline-block"></a>`;
        if (d.instagram) socialHtml += `<a href="${d.instagram}" style="margin-right: 8px;"><img src="https://cdn-icons-png.flaticon.com/32/2111/2111463.png" width="20" style="display:inline-block"></a>`;
        if (d.linkedin)  socialHtml += `<a href="${d.linkedin}" style="margin-right: 8px;"><img src="https://cdn-icons-png.flaticon.com/32/3536/3536505.png" width="20" style="display:inline-block"></a>`;
        if (d.twitter)   socialHtml += `<a href="${d.twitter}" style="margin-right: 8px;"><img src="https://cdn-icons-png.flaticon.com/32/3256/3256609.png" width="20" style="display:inline-block"></a>`;
        socialHtml += '</div>';
    }
    html = html.replace(/\{\{social_links\}\}/g, socialHtml);

    return html;
});

const copied = ref(false);
const copySignature = async () => {
    const html = `<div style="text-align:left;">${renderedSignature.value}</div>`;

    const copySuccess = async () => {
        copied.value = true;
        setTimeout(() => copied.value = false, 2000);
    };

    try {
        if (navigator.clipboard && navigator.clipboard.write) {
            const blobInput = new ClipboardItem({
                'text/html': new Blob([html], { type: 'text/html' }),
                'text/plain': new Blob([html.replace(/<[^>]+>/g, '')], { type: 'text/plain' }),
            });
            await navigator.clipboard.write([blobInput]);
            await copySuccess();
            return;
        }

        const listener = (e) => {
            e.preventDefault();
            e.clipboardData.setData('text/html', html);
            e.clipboardData.setData('text/plain', html.replace(/<[^>]+>/g, ''));
        };
        document.addEventListener('copy', listener);
        document.execCommand('copy');
        document.removeEventListener('copy', listener);
        await copySuccess();
    } catch (err) {
        console.error('Cant copy', err);
    }
};

// Outlook exige un documento completo con charset declarado; si no, rompe los acentos.
const downloadOutlookFile = () => {
    const doc = `<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>${(formData.value.name || 'Firma').replace(/[<>&]/g, '')}</title>
</head>
<body style="margin:0;padding:0;">
<div style="text-align:left;">${renderedSignature.value}</div>
</body>
</html>`;

    const blob = new Blob(['\ufeff', doc], { type: 'text/html;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const safeName = (formData.value.name || 'firma').trim().replace(/[^a-z0-9]+/gi, '-').toLowerCase();

    link.href = url;
    link.download = `${safeName || 'firma'}.htm`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
};

const handleFileUpload = (event, type) => {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => { formData.value[type] = e.target.result; };
        reader.readAsDataURL(file);
    }
};
</script>

<template>
    <Head title="Generador de Firma" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100 flex items-center gap-2">
                <SwatchIcon class="h-6 w-6 text-gray-500 dark:text-zinc-400" />
                Generador de Firma de Correo
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

                    <!-- Left Column: Form -->
                    <div class="lg:col-span-5 space-y-6">
                        <div class="bg-white dark:bg-zinc-900 p-6 shadow-sm sm:rounded-lg border border-gray-200 dark:border-zinc-800">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-6 flex items-center">
                                <SwatchIcon class="h-5 w-5 mr-2 text-[#264ab3]" />
                                Tus Datos
                            </h3>

                            <div class="space-y-4">
                                <!-- Selector de plantilla: incluye la asignada (si existe) y todas las públicas -->
                                <div v-if="templates.length > 1">
                                    <InputLabel value="Selecciona una Plantilla" class="dark:text-zinc-300" />
                                    <select v-model="formData.template_id" class="mt-1 block w-full border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 focus:border-indigo-500 rounded-md shadow-sm">
                                        <option v-for="t in templates" :key="t.id" :value="t.id">
                                            {{ t.name }}{{ assignedTemplate && t.id === assignedTemplate.id ? ' (Asignada)' : '' }}
                                        </option>
                                    </select>
                                    <p v-if="hasAssigned" class="text-xs text-gray-400 dark:text-zinc-500 mt-1">
                                        Tu empresa te asignó la plantilla <span class="font-semibold">{{ assignedTemplate.name }}</span>, pero también puedes explorar las demás disponibles.
                                    </p>
                                </div>
                                <div v-else-if="hasAssigned" class="flex items-center gap-2 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg px-3 py-2 text-xs text-indigo-700 dark:text-indigo-300 font-medium">
                                    <SwatchIcon class="h-4 w-4 flex-shrink-0" />
                                    Plantilla: <span class="font-bold">{{ assignedTemplate.name }}</span>
                                </div>

                                <!-- Nombre -->
                                <div v-if="has('name')">
                                    <InputLabel for="name" value="Nombre Completo" class="dark:text-zinc-300" />
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <UserIcon class="h-4 w-4 text-gray-400 dark:text-zinc-500" />
                                        </div>
                                        <TextInput id="name" type="text" v-model="formData.name" class="block w-full pl-10 sm:text-sm dark:bg-zinc-800 dark:border-zinc-700 dark:text-gray-100" />
                                    </div>
                                </div>

                                <!-- Puesto -->
                                <div v-if="has('position')">
                                    <InputLabel for="position" value="Puesto o Cargo" class="dark:text-zinc-300" />
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <BriefcaseIcon class="h-4 w-4 text-gray-400 dark:text-zinc-500" />
                                        </div>
                                        <TextInput id="position" type="text" v-model="formData.position" placeholder="Ej. Gerente de Ventas" class="block w-full pl-10 sm:text-sm dark:bg-zinc-800 dark:border-zinc-700 dark:text-gray-100" />
                                    </div>
                                </div>

                                <!-- Email + Teléfono -->
                                <div class="grid gap-4" :class="has('email') && has('phone') ? 'grid-cols-2' : 'grid-cols-1'">
                                    <div v-if="has('email')">
                                        <InputLabel for="email" value="Correo Electrónico" class="dark:text-zinc-300" />
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <AtSymbolIcon class="h-4 w-4 text-gray-400 dark:text-zinc-500" />
                                            </div>
                                            <TextInput id="email" type="email" v-model="formData.email" class="block w-full pl-10 sm:text-sm dark:bg-zinc-800 dark:border-zinc-700 dark:text-gray-100" />
                                        </div>
                                    </div>
                                    <div v-if="has('phone')">
                                        <InputLabel for="phone" value="Teléfono" class="dark:text-zinc-300" />
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <PhoneIcon class="h-4 w-4 text-gray-400 dark:text-zinc-500" />
                                            </div>
                                            <TextInput id="phone" type="text" v-model="formData.phone" placeholder="123 456 7890" class="block w-full pl-10 sm:text-sm dark:bg-zinc-800 dark:border-zinc-700 dark:text-gray-100" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Sitio web -->
                                <div v-if="has('website')">
                                    <InputLabel for="website" value="Sitio Web" class="dark:text-zinc-300" />
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <GlobeAltIcon class="h-4 w-4 text-gray-400 dark:text-zinc-500" />
                                        </div>
                                        <TextInput id="website" type="text" v-model="formData.website" placeholder="www.tuempresa.com" class="block w-full pl-10 sm:text-sm dark:bg-zinc-800 dark:border-zinc-700 dark:text-gray-100" />
                                    </div>
                                </div>

                                <!-- Logo + Foto -->
                                <div v-if="has('logo') || has('photo')" class="grid grid-cols-2 gap-4">
                                    <div v-if="has('logo')">
                                        <InputLabel value="Logo Empresa" class="dark:text-zinc-300" />
                                        <div class="mt-1 flex items-center space-x-2">
                                            <label class="cursor-pointer bg-gray-50 dark:bg-zinc-800 border border-gray-300 dark:border-zinc-700 rounded-md px-3 py-2 text-xs flex items-center hover:bg-gray-100 dark:hover:bg-zinc-700 dark:text-zinc-300">
                                                <PhotoIcon class="h-4 w-4 mr-1 text-gray-500 dark:text-zinc-400" />
                                                Subir
                                                <input type="file" @change="handleFileUpload($event, 'logo')" class="hidden" accept="image/*" />
                                            </label>
                                            <div class="h-8 w-8 bg-gray-100 dark:bg-zinc-800 rounded border border-gray-200 dark:border-zinc-700 p-1">
                                                <img :src="formData.logo" class="h-full w-full object-contain" />
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="has('photo')">
                                        <InputLabel value="Foto de Perfil" class="dark:text-zinc-300" />
                                        <div class="mt-1 flex items-center space-x-2">
                                            <label class="cursor-pointer bg-gray-50 dark:bg-zinc-800 border border-gray-300 dark:border-zinc-700 rounded-md px-3 py-2 text-xs flex items-center hover:bg-gray-100 dark:hover:bg-zinc-700 dark:text-zinc-300">
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

                                <!-- Colores -->
                                <div v-if="has('primary_color') || has('secondary_color')" class="border-t dark:border-zinc-700 pt-4 mt-4">
                                    <h4 class="text-sm font-bold text-gray-700 dark:text-zinc-300 mb-3">Colores de Marca</h4>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div v-if="has('primary_color')">
                                            <InputLabel value="Color Principal" class="dark:text-zinc-400" />
                                            <div class="mt-1 flex items-center">
                                                <input type="color" v-model="formData.primary_color" class="h-10 w-full rounded border border-gray-300 dark:border-zinc-700 p-1 bg-white dark:bg-zinc-800 cursor-pointer" />
                                            </div>
                                        </div>
                                        <div v-if="has('secondary_color')">
                                            <InputLabel value="Color Secundario" class="dark:text-zinc-400" />
                                            <div class="mt-1 flex items-center">
                                                <input type="color" v-model="formData.secondary_color" class="h-10 w-full rounded border border-gray-300 dark:border-zinc-700 p-1 bg-white dark:bg-zinc-800 cursor-pointer" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Redes sociales -->
                                <div v-if="has('social_links')" class="border-t dark:border-zinc-700 pt-4 mt-4">
                                    <h4 class="text-sm font-bold text-gray-700 dark:text-zinc-300 mb-3">Redes Sociales (URLs opcionales)</h4>
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
                        <div class="bg-white dark:bg-zinc-900 p-6 shadow-sm sm:rounded-lg border border-gray-200 dark:border-zinc-800 h-full">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">Vista Previa</h3>
                                <div class="flex items-center gap-2">
                                    <button @click="copySignature" class="bg-[#264ab3] text-white px-6 py-2 rounded-full font-bold text-sm flex items-center hover:bg-blue-800 transition-colors shadow-lg">
                                        <CheckIcon v-if="copied" class="h-4 w-4 mr-2" />
                                        <ClipboardIcon v-else class="h-4 w-4 mr-2" />
                                        {{ copied ? '¡Copiado!' : 'Copiar Firma' }}
                                    </button>
                                    <button @click="downloadOutlookFile" class="bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-zinc-200 border border-gray-300 dark:border-zinc-700 px-5 py-2 rounded-full font-bold text-sm flex items-center hover:bg-gray-200 dark:hover:bg-zinc-700 transition-colors">
                                        <ArrowDownTrayIcon class="h-4 w-4 mr-2" />
                                        Archivo Outlook
                                    </button>
                                </div>
                            </div>

                            <div class="bg-gray-50 rounded-xl p-8 border border-dashed border-gray-300 min-h-[300px] flex items-center justify-center relative overflow-auto">
                                <div id="signature-preview-container" v-html="renderedSignature" class="bg-white p-4 shadow-sm inline-block"></div>
                            </div>

                            <div class="mt-6">
                                <h4 class="text-xs font-bold text-gray-500 dark:text-zinc-400 uppercase mb-3">Instrucciones:</h4>
                                <ul class="text-xs text-gray-600 dark:text-zinc-400 space-y-2 list-disc pl-4">
                                    <li>Llene los datos en el formulario de la izquierda.</li>
                                    <li>Revise que la vista previa sea correcta.</li>
                                    <li>Haga clic en <b>"Copiar Firma"</b>.</li>
                                    <li>Vaya a su cliente de correo (Outlook, Gmail, Apple Mail).</li>
                                    <li>En la configuración de "Firma", simplemente <b>Pegue (Ctrl+V o Cmd+V)</b>.</li>
                                </ul>

                                <h4 class="text-xs font-bold text-gray-500 dark:text-zinc-400 uppercase mb-3 mt-6">Outlook de escritorio (Windows):</h4>
                                <ul class="text-xs text-gray-600 dark:text-zinc-400 space-y-2 list-disc pl-4">
                                    <li>Haga clic en <b>"Archivo Outlook"</b> para descargar el archivo <b>.htm</b>.</li>
                                    <li>Abra el Explorador y vaya a <b>%APPDATA%\Microsoft\Signatures</b>.</li>
                                    <li>Copie ahí el archivo descargado.</li>
                                    <li>Reinicie Outlook y seleccione la firma en <b>Archivo → Opciones → Correo → Firmas</b>.</li>
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
