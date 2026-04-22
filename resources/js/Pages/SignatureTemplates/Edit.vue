<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import CodeEditor from '@/Components/CodeEditor.vue';
import { SwatchIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    template: Object
});

const form = useForm({
    name: props.template.name,
    slug: props.template.slug,
    html_content: props.template.html_content,
    css_content: props.template.css_content || '',
    is_active: !!props.template.is_active,
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
                                </div>
                            </div>

                            <div>
                                <label class="flex items-center">
                                    <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300 dark:border-zinc-800 dark:bg-zinc-950 text-indigo-600 shadow-sm focus:ring-indigo-500 h-5 w-5" />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300 font-bold">Plantilla Activa</span>
                                </label>
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
