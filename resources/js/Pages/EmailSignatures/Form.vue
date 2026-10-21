<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { IdentificationIcon, LinkIcon, CheckIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    signature:       { type: Object, default: null },
    clients:         Array,
    templates:       Array,
    defaultClientId: { type: Number, default: null },
});

const isEdit = computed(() => !!props.signature);

const SOCIALS = [
    { key: 'facebook',  label: 'Facebook' },
    { key: 'instagram', label: 'Instagram' },
    { key: 'linkedin',  label: 'LinkedIn' },
    { key: 'twitter',   label: 'X / Twitter' },
];

const form = useForm({
    client_id:             props.signature?.client_id ?? props.defaultClientId ?? '',
    signature_template_id: props.signature?.signature_template_id ?? '',
    name:                  props.signature?.name ?? '',
    position:              props.signature?.position ?? '',
    email:                 props.signature?.email ?? '',
    phone:                 props.signature?.phone ?? '',
    social_links:          { facebook: '', instagram: '', linkedin: '', twitter: '', ...(props.signature?.social_links ?? {}) },
    is_active:             props.signature?.is_active ?? true,
    photo:                 null,
    remove_photo:          false,
});

const photoPreview = ref(props.signature?.photo_url ?? null);

const onPhoto = (event) => {
    const file = event.target.files[0];
    form.photo = file || null;
    form.remove_photo = false;
    photoPreview.value = file ? URL.createObjectURL(file) : props.signature?.photo_url ?? null;
};

const removePhoto = () => {
    form.photo = null;
    form.remove_photo = true;
    photoPreview.value = null;
};

const selectedClient = computed(() => props.clients.find(c => c.id === Number(form.client_id)) || null);

const submit = () => {
    const url = isEdit.value
        ? route('email-signatures.update', props.signature.id)
        : route('email-signatures.store');

    // POST + _method: los archivos solo viajan en multipart, y PUT no lo soporta.
    // El _method se decide al enviar y no en el estado del form: tras crear, el
    // redirect a edit reutiliza este mismo componente (Inertia preserva estado
    // en POST) y el form se quedaría con 'post'.
    form.transform(data => ({
        ...data,
        _method: isEdit.value ? 'put' : 'post',
        signature_template_id: data.signature_template_id || null,
    })).post(url, {
        forceFormData: true,
        preserveScroll: true,
        // Al crear, montar la pantalla de edición desde cero.
        preserveState: isEdit.value,
        onSuccess: () => {
            form.photo = null;
            form.remove_photo = false;
            photoPreview.value = props.signature?.photo_url ?? null;
        },
    });
};

const copied = ref(false);
const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(props.signature.public_url);
        copied.value = true;
        setTimeout(() => copied.value = false, 2000);
    } catch (e) {
        window.prompt('Copia el link:', props.signature.public_url);
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Editar Firma' : 'Nueva Firma'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100 flex items-center gap-2">
                <IdentificationIcon class="h-6 w-6 text-[#264ab3] dark:text-blue-400" />
                {{ isEdit ? `Firma de ${signature.name}` : 'Nueva Firma de Empleado' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
                <form @submit.prevent="submit" class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg border border-gray-200 dark:border-zinc-800 p-6 space-y-4">
                    <div>
                        <InputLabel value="Empresa" />
                        <select v-model="form.client_id" class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-gray-100 text-sm">
                            <option value="" disabled>Selecciona…</option>
                            <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.business_name }}</option>
                        </select>
                        <InputError :message="form.errors.client_id" />
                    </div>

                    <div>
                        <InputLabel value="Plantilla" />
                        <select v-model="form.signature_template_id" class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-gray-100 text-sm">
                            <option value="">
                                La de la empresa{{ selectedClient?.template_name ? ` (${selectedClient.template_name})` : ' (no tiene asignada)' }}
                            </option>
                            <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                        <InputError :message="form.errors.signature_template_id" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Nombre" />
                            <TextInput v-model="form.name" class="mt-1 w-full" required />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel value="Puesto" />
                            <TextInput v-model="form.position" class="mt-1 w-full" />
                            <InputError :message="form.errors.position" />
                        </div>
                        <div>
                            <InputLabel value="Correo" />
                            <TextInput v-model="form.email" type="email" class="mt-1 w-full" />
                            <InputError :message="form.errors.email" />
                        </div>
                        <div>
                            <InputLabel value="Teléfono" />
                            <TextInput v-model="form.phone" class="mt-1 w-full" />
                            <InputError :message="form.errors.phone" />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Foto" />
                        <div class="mt-1 flex items-center gap-4">
                            <img v-if="photoPreview" :src="photoPreview" class="h-16 w-16 rounded-full object-cover" />
                            <input type="file" accept="image/png,image/jpeg,image/gif,image/webp" @change="onPhoto"
                                class="block text-sm text-gray-700 dark:text-zinc-300" />
                            <button v-if="photoPreview" type="button" @click="removePhoto" class="text-sm text-red-600 dark:text-rose-400">Quitar</button>
                        </div>
                        <InputError :message="form.errors.photo" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div v-for="s in SOCIALS" :key="s.key">
                            <InputLabel :value="s.label" />
                            <TextInput v-model="form.social_links[s.key]" placeholder="https://…" class="mt-1 w-full" />
                            <InputError :message="form.errors[`social_links.${s.key}`]" />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-zinc-300">
                        <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300 text-[#264ab3]" />
                        Activa (si la desactivas, el link público y sus imágenes dejan de funcionar)
                    </label>

                    <div class="flex justify-between items-center pt-2">
                        <Link :href="route('email-signatures.index', form.client_id ? { client_id: form.client_id } : {})" class="text-sm text-gray-600 dark:text-zinc-400">
                            Volver
                        </Link>
                        <PrimaryButton :disabled="form.processing">{{ isEdit ? 'Guardar cambios' : 'Crear firma' }}</PrimaryButton>
                    </div>
                </form>

                <div class="space-y-6">
                    <div v-if="isEdit" class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg border border-gray-200 dark:border-zinc-800 p-6">
                        <h3 class="font-bold text-gray-800 dark:text-gray-100 mb-2">Link para el empleado</h3>
                        <div class="flex gap-2">
                            <TextInput :model-value="signature.public_url" readonly class="w-full text-sm" />
                            <button type="button" @click="copyLink" class="bg-[#264ab3] text-white px-3 rounded-md">
                                <CheckIcon v-if="copied" class="h-5 w-5" />
                                <LinkIcon v-else class="h-5 w-5" />
                            </button>
                            <a :href="signature.public_url" target="_blank" class="border border-gray-300 dark:border-zinc-700 px-3 rounded-md flex items-center text-gray-600 dark:text-zinc-300">
                                <ArrowTopRightOnSquareIcon class="h-5 w-5" />
                            </a>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-zinc-400 mt-2">
                            Último acceso: {{ signature.last_viewed_at || 'nunca' }}.
                            Si cambias nombre, puesto o teléfono, el empleado debe volver a copiar su firma desde este link.
                            Los cambios de logo, foto y banner se actualizan solos.
                        </p>
                    </div>

                    <div v-if="isEdit" class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg border border-gray-200 dark:border-zinc-800 p-6">
                        <h3 class="font-bold text-gray-800 dark:text-gray-100 mb-4">Vista previa</h3>
                        <div v-if="!signature.has_logo" class="mb-4 p-3 rounded-md bg-amber-50 border border-amber-200 text-sm text-amber-800">
                            Esta empresa no tiene logo, por eso no aparece en la firma.
                            <Link :href="route('email-signatures.index', { client_id: signature.client_id })" class="font-bold underline">Súbelo en Logos y banners</Link>
                            y se mostrará solo, también en las firmas ya instaladas.
                        </div>
                        <div v-if="signature.has_template" class="bg-white p-4 rounded border border-gray-100 overflow-x-auto" v-html="signature.preview_html"></div>
                        <p v-else class="text-sm text-amber-700">
                            Esta firma no tiene plantilla: elige una aquí o asígnala a la empresa en su expediente.
                        </p>
                    </div>

                    <div v-else class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg border border-gray-200 dark:border-zinc-800 p-6 text-sm text-gray-500 dark:text-zinc-400">
                        Al guardar verás la vista previa y el link público para compartir con el empleado.
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
