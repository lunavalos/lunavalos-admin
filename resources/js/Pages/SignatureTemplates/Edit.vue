<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import CodeEditor from '@/Components/CodeEditor.vue';
import { SwatchIcon, ClipboardIcon, CheckIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    template: Object
});

const ALL_FIELDS = [
    { key: 'name',            label: 'Nombre completo' },
    { key: 'position',        label: 'Puesto / Cargo' },
    { key: 'email',           label: 'Correo electrónico' },
    { key: 'phone',           label: 'Teléfono' },
    { key: 'website',         label: 'Sitio web' },
    { key: 'logo',            label: 'Logo empresa' },
    { key: 'photo',           label: 'Foto de perfil' },
    { key: 'primary_color',   label: 'Color principal' },
    { key: 'secondary_color', label: 'Color secundario' },
    { key: 'social_links',    label: 'Redes sociales' },
];

const form = useForm({
    name: props.template.name,
    slug: props.template.slug,
    html_content: props.template.html_content,
    css_content: props.template.css_content || '',
    fields: props.template.fields ?? ALL_FIELDS.map(f => f.key),
    is_active: !!props.template.is_active,
    is_private: !!props.template.is_private,
});

const submit = () => {
    form.put(route('signature-templates.update', props.template.id));
};

const updateSlug = () => {
    form.slug = form.name.toLowerCase().replace(/ /g, '-').replace(/[^\w-]+/g, '');
};

const mockData = {
    name: 'Hugo Luna',
    position: 'Director Ejecutivo',
    email: 'hugo@lunavalos.mx',
    phone: '+52 55 1234 5678',
    website: 'www.lunavalos.mx',
    logo: 'https://placehold.co/150x50/333/eee?text=LOGO',
    photo: 'https://i.pravatar.cc/100?u=hugo',
    primary_color: '#264ab3',
    secondary_color: '#f43f5e',
    social_links: '<span style="color:#264ab3">FB</span> | <span style="color:#264ab3">IG</span> | <span style="color:#264ab3">LI</span>',
};

const parsedHtml = computed(() => {
    let html = form.html_content || '';
    Object.keys(mockData).forEach(key => {
        const regex = new RegExp(`{{${key}}}`, 'g');
        html = html.replace(regex, mockData[key]);
    });
    return html;
});

const copied = ref(false);

const copySignature = async () => {
    const html = `<div style="text-align:left;">${parsedHtml.value}</div>`;
    const plain = html.replace(/<[^>]+>/g, '');

    const markCopied = () => {
        copied.value = true;
        setTimeout(() => copied.value = false, 2000);
    };

    try {
        if (navigator.clipboard && navigator.clipboard.write) {
            await navigator.clipboard.write([
                new ClipboardItem({
                    'text/html': new Blob([html], { type: 'text/html' }),
                    'text/plain': new Blob([plain], { type: 'text/plain' }),
                }),
            ]);
            markCopied();
            return;
        }

        const listener = (e) => {
            e.preventDefault();
            e.clipboardData.setData('text/html', html);
            e.clipboardData.setData('text/plain', plain);
        };
        document.addEventListener('copy', listener);
        document.execCommand('copy');
        document.removeEventListener('copy', listener);
        markCopied();
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
<title>${(form.name || 'Firma').replace(/[<>&]/g, '')}</title>
</head>
<body style="margin:0;padding:0;">
<div style="text-align:left;">${parsedHtml.value}</div>
</body>
</html>`;

    const blob = new Blob(['\ufeff', doc], { type: 'text/html;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = `${form.slug || 'firma'}.htm`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
};
</script>

<template>
    <Head title="Editar Plantilla de Firma" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100 flex items-center gap-2">
                <SwatchIcon class="h-6 w-6 text-[#264ab3] dark:text-blue-400" />
                Editar Plantilla: {{ template.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-zinc-800">
                    <div class="p-6">
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="name" value="Nombre de la Plantilla" class="dark:text-gray-300" />
                                    <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required @input="updateSlug" />
                                    <InputError class="mt-2" :message="form.errors.name" />
                                </div>
                                <div>
                                    <InputLabel for="slug" value="Slug (Identificador)" class="dark:text-gray-300" />
                                    <TextInput id="slug" type="text" class="mt-1 block w-full" v-model="form.slug" required />
                                    <InputError class="mt-2" :message="form.errors.slug" />
                                </div>
                            </div>

                            <div class="grid grid-cols-12 gap-8">
                                <div class="col-span-12 lg:col-span-7 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <InputLabel for="html_content" value="Editor de Código" class="!mb-0 dark:text-gray-300" />
                                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">HTML / TEXT</span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-zinc-400">Usa variables como: {{name}}, {{position}}, {{email}}, {{phone}}, ...</p>
                                    <CodeEditor id="html_content" v-model="form.html_content" :rows="22" />
                                    <InputError class="mt-2" :message="form.errors.html_content" />
                                </div>

                                <div class="col-span-12 lg:col-span-5 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <InputLabel value="Vista Previa en Vivo" class="!mb-0 dark:text-gray-300" />
                                        <span class="text-[10px] font-bold text-blue-500 uppercase tracking-widest animate-pulse">Live</span>
                                    </div>
                                    <div class="p-8 bg-gray-50 dark:bg-zinc-950 border border-gray-200 dark:border-zinc-800 rounded-xl min-h-[400px] shadow-inner overflow-auto bg-[radial-gradient(#e5e7eb_1px,transparent_1px)] dark:bg-[radial-gradient(#1e293b_1px,transparent_1px)] [background-size:16px_16px]">
                                        <div class="signature-preview-container bg-white shadow-lg p-1 mx-auto max-w-full overflow-x-auto inline-block border border-gray-100 rounded-sm">
                                            <div v-html="parsedHtml"></div>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-gray-400 text-center italic">Visualización con datos de ejemplo para demostración.</p>

                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="copySignature" class="flex-1 bg-[#264ab3] text-white px-4 py-2 rounded-lg font-bold text-xs flex items-center justify-center hover:bg-blue-800 transition-colors">
                                            <CheckIcon v-if="copied" class="h-4 w-4 mr-2" />
                                            <ClipboardIcon v-else class="h-4 w-4 mr-2" />
                                            {{ copied ? '¡Copiado!' : 'Copiar Firma' }}
                                        </button>
                                        <button type="button" @click="downloadOutlookFile" class="flex-1 bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-zinc-200 border border-gray-300 dark:border-zinc-700 px-4 py-2 rounded-lg font-bold text-xs flex items-center justify-center hover:bg-gray-200 dark:hover:bg-zinc-700 transition-colors">
                                            <ArrowDownTrayIcon class="h-4 w-4 mr-2" />
                                            Archivo Outlook (.htm)
                                        </button>
                                    </div>
                                    <p class="text-[10px] text-gray-400 dark:text-zinc-500 text-center">
                                        El .htm va en <b>%APPDATA%\Microsoft\Signatures</b> en Outlook de escritorio.
                                    </p>
                                </div>
                            </div>

                            <!-- Campos visibles al cliente -->
                            <div class="border border-gray-200 dark:border-zinc-800 rounded-xl p-4">
                                <InputLabel value="Campos que puede editar el cliente" class="dark:text-gray-300 mb-3" />
                                <p class="text-xs text-gray-400 dark:text-zinc-500 mb-3">Selecciona solo los campos que usa esta plantilla. El cliente verá únicamente estos inputs.</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <label v-for="field in ALL_FIELDS" :key="field.key" class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                        <input type="checkbox" :value="field.key" v-model="form.fields" class="rounded border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 text-indigo-600 shadow-sm focus:ring-indigo-500 h-4 w-4" />
                                        {{ field.label }}
                                    </label>
                                </div>
                                <InputError class="mt-2" :message="form.errors.fields" />
                            </div>

                            <div class="flex items-center gap-8">
                                <label class="flex items-center">
                                    <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300 dark:border-zinc-800 dark:bg-zinc-950 text-indigo-600 shadow-sm focus:ring-indigo-500 h-5 w-5" />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300 font-bold">Plantilla Activa</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" v-model="form.is_private" class="rounded border-gray-300 dark:border-zinc-800 dark:bg-zinc-950 text-indigo-600 shadow-sm focus:ring-indigo-500 h-5 w-5" />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300 font-bold">Plantilla Privada</span>
                                </label>
                                <p class="text-xs text-gray-400 dark:text-zinc-500">
                                    Si es privada, solo el cliente al que se asigne podrá verla y usarla. Si es pública, todos los clientes podrán explorarla.
                                </p>
                            </div>

                            <div class="flex items-center justify-end">
                                <Link :href="route('signature-templates.index')" class="text-gray-600 dark:text-zinc-400 mr-4 hover:underline text-sm font-bold">Cancelar</Link>
                                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Actualizar Plantilla
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
