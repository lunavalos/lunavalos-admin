<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    PlusIcon, PencilIcon, TrashIcon, IdentificationIcon, LinkIcon, CheckIcon, PhotoIcon,
} from '@heroicons/vue/24/outline';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    signatures:    Array,
    clients:       Array,
    filters:       Object,
    assets:        Array,
    usage:         { type: Array, default: () => [] },
    templates:     { type: Array, default: () => [] },
});

const clientId = ref(props.filters.client_id ?? '');
const search = ref(props.filters.search ?? '');

const applyFilters = () => {
    router.get(route('email-signatures.index'), {
        client_id: clientId.value || undefined,
        search:    search.value || undefined,
    }, { preserveState: true, replace: true });
};

const selectedClient = computed(() => props.clients.find(c => c.id === Number(clientId.value)) || null);
const clientsWithSignatures = computed(() => props.clients.filter(c => c.signatures > 0));

const copiedId = ref(null);
const copyLink = async (signature) => {
    try {
        await navigator.clipboard.writeText(signature.public_url);
        copiedId.value = signature.id;
        setTimeout(() => copiedId.value = null, 2000);
    } catch (e) {
        window.prompt('Copia el link:', signature.public_url);
    }
};

const deleteSignature = (id) => {
    if (confirm('¿Eliminar esta firma? Su link público dejará de funcionar.')) {
        router.delete(route('email-signatures.destroy', id));
    }
};

// --- Logos y banners de la empresa seleccionada
const assetForm = useForm({
    client_id: null,
    signature_template_id: '',
    slot:      'logo',
    label:     '',
    file:      null,
    link_url:  '',
    starts_at: '',
    ends_at:   '',
    repeats_yearly: false,
});
const fileInput = ref(null);

const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const diaMes = (date) => {
    const [, m, d] = date.split('-');
    return `${Number(d)} ${MESES[Number(m) - 1]}`;
};

const vigencia = (asset) => {
    if (!asset.starts_at && !asset.ends_at) return 'Siempre';
    if (asset.repeats_yearly) return `Cada año: ${diaMes(asset.starts_at)} → ${diaMes(asset.ends_at)}`;
    return `${asset.starts_at || '…'} → ${asset.ends_at || '…'}`;
};

// null = subiendo una nueva; si no, la que se está editando.
const editingAsset = ref(null);
const assetFormEl = ref(null);

const resetAssetForm = () => {
    editingAsset.value = null;
    assetForm.reset();
    assetForm.clearErrors();
    if (fileInput.value) fileInput.value.value = '';
};

const editAsset = (asset) => {
    editingAsset.value = asset;
    assetForm.clearErrors();
    Object.assign(assetForm, {
        signature_template_id: asset.signature_template_id ?? '',
        slot:           asset.slot,
        label:          asset.label ?? '',
        file:           null,
        link_url:       asset.link_url ?? '',
        starts_at:      asset.starts_at ?? '',
        ends_at:        asset.ends_at ?? '',
        repeats_yearly: !!asset.repeats_yearly,
    });
    if (fileInput.value) fileInput.value.value = '';
    assetFormEl.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

const uploadAsset = () => {
    assetForm.client_id = Number(clientId.value);

    const url = editingAsset.value
        ? route('signature-assets.update', editingAsset.value.id)
        : route('signature-assets.store');

    // POST + _method para que la imagen (opcional al editar) viaje en multipart.
    assetForm
        .transform(data => (editingAsset.value ? { ...data, _method: 'put' } : data))
        .post(url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: resetAssetForm,
        });
};

const deleteAsset = (id) => {
    if (confirm('¿Eliminar esta imagen?')) {
        router.delete(route('signature-assets.destroy', id), { preserveScroll: true });
    }
};

const isCurrent = (asset) => asset.is_current;

// Plantillas que ya usa la empresa primero; el resto después.
const templateOptions = computed(() => {
    const used = new Set(props.usage.map(u => u.template_id).filter(Boolean));
    return [...props.templates].sort((a, b) => used.has(b.id) - used.has(a.id));
});

const missingLogo = computed(() => props.usage.filter(u => u.template_id && !u.has_logo));
</script>

<template>
    <Head title="Firmas de Empleados" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100 flex items-center gap-2">
                    <IdentificationIcon class="h-6 w-6 text-[#264ab3] dark:text-blue-400" />
                    Firmas de Empleados
                </h2>
                <Link
                    :href="route('email-signatures.create', clientId ? { client_id: clientId } : {})"
                    class="bg-[#264ab3] text-white px-4 py-2 rounded-md font-bold text-sm flex items-center">
                    <PlusIcon class="h-4 w-4 mr-1 text-white" />
                    Nueva Firma
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Conteo por empresa -->
                <div v-if="clientsWithSignatures.length" class="flex flex-wrap gap-2">
                    <button
                        v-for="c in clientsWithSignatures" :key="c.id"
                        @click="clientId = c.id; applyFilters()"
                        :class="Number(clientId) === c.id ? 'bg-[#264ab3] text-white border-[#264ab3]' : 'bg-white dark:bg-zinc-900 text-gray-700 dark:text-zinc-300 border-gray-200 dark:border-zinc-700'"
                        class="px-3 py-1.5 rounded-full border text-sm font-medium">
                        {{ c.business_name }} · <span class="font-bold">{{ c.signatures }}</span>
                    </button>
                </div>

                <!-- Filtros -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <select v-model="clientId" @change="applyFilters"
                        class="rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-gray-100 text-sm sm:w-72">
                        <option value="">Todas las empresas</option>
                        <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.business_name }} ({{ c.signatures }})</option>
                    </select>
                    <TextInput v-model="search" @keyup.enter="applyFilters" placeholder="Buscar por nombre o correo…" class="sm:w-72" />
                </div>

                <!-- Logos y banners de la empresa -->
                <div v-if="selectedClient" class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg border border-gray-200 dark:border-zinc-800 p-6">
                    <h3 class="font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2 mb-1">
                        <PhotoIcon class="h-5 w-5 text-[#264ab3]" />
                        Logos y banners de {{ selectedClient.business_name }}
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-zinc-400 mb-4">
                        Todas las firmas de esta empresa muestran la imagen vigente, incluso las que ya están instaladas.
                        Sin fechas = la de siempre; con fechas = de temporada (gana mientras esté vigente).
                        Si la cuenta maneja varias marcas, sube el logo de cada una a su plantilla; lo que subas para "Todas" se usa cuando una plantilla no tiene uno propio.
                        Usa PNG o JPG con las mismas medidas que la anterior para que la firma no se descuadre.
                    </p>

                    <div v-if="missingLogo.length" class="mb-4 p-3 rounded-md bg-amber-50 border border-amber-200 text-sm text-amber-800">
                        Sin logo vigente: <b>{{ missingLogo.map(u => u.name).join(', ') }}</b>. Sus firmas se ven sin logo; súbelo aquí.
                    </div>

                    <div v-if="editingAsset" class="mb-3 p-3 rounded-md bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-sm text-blue-800 dark:text-blue-300 flex items-center gap-3">
                        <img :src="editingAsset.url" class="h-8 w-14 object-contain bg-white rounded" />
                        <span class="flex-1">Editando <b>{{ editingAsset.label || (editingAsset.slot === 'logo' ? 'Logo' : 'Banner') }}</b>. Sube una imagen solo si quieres reemplazarla.</span>
                        <button type="button" @click="resetAssetForm" class="font-bold underline">Cancelar</button>
                    </div>

                    <form ref="assetFormEl" @submit.prevent="uploadAsset" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end mb-6">
                        <div>
                            <InputLabel value="Tipo" />
                            <select v-model="assetForm.slot" class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-gray-100 text-sm">
                                <option value="logo">Logo</option>
                                <option value="banner">Banner</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel :value="editingAsset ? 'Reemplazar imagen (opcional)' : 'Imagen (PNG/JPG)'" />
                            <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/gif"
                                @change="assetForm.file = $event.target.files[0]"
                                class="mt-1 block w-full text-sm text-gray-700 dark:text-zinc-300" />
                            <InputError :message="assetForm.errors.file" />
                        </div>
                        <div>
                            <InputLabel value="Desde" />
                            <TextInput v-model="assetForm.starts_at" type="date" class="mt-1 w-full" />
                            <InputError :message="assetForm.errors.starts_at" />
                        </div>
                        <div>
                            <InputLabel value="Hasta" />
                            <TextInput v-model="assetForm.ends_at" type="date" class="mt-1 w-full" />
                            <InputError :message="assetForm.errors.ends_at" />
                        </div>
                        <div class="md:col-span-1">
                            <button type="submit" :disabled="assetForm.processing || (!editingAsset && !assetForm.file)"
                                class="w-full bg-[#264ab3] text-white px-4 py-2 rounded-md font-bold text-sm disabled:opacity-50">
                                {{ editingAsset ? 'Guardar' : 'Subir' }}
                            </button>
                        </div>
                        <label class="md:col-span-6 flex items-start gap-2 text-sm text-gray-700 dark:text-zinc-300">
                            <input type="checkbox" v-model="assetForm.repeats_yearly" class="mt-0.5 rounded border-gray-300 text-[#264ab3]" />
                            <span>
                                <b>Repetir cada año</b> en estas fechas (ej. Halloween del 1 al 31 de octubre). Solo cuentan el día y el mes; puede cruzar de año, como del 15 de diciembre al 6 de enero.
                            </span>
                        </label>
                        <div class="md:col-span-2">
                            <InputLabel value="Aplica a" />
                            <select v-model="assetForm.signature_template_id" class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-gray-100 text-sm">
                                <option value="">Todas las plantillas</option>
                                <option v-for="t in templateOptions" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                            <InputError :message="assetForm.errors.signature_template_id" />
                        </div>
                        <div :class="assetForm.slot === 'banner' ? 'md:col-span-2' : 'md:col-span-4'">
                            <InputLabel value="Nombre (opcional)" />
                            <TextInput v-model="assetForm.label" placeholder="Ej. Logo Navidad 2026" class="mt-1 w-full" />
                        </div>
                        <div v-if="assetForm.slot === 'banner'" class="md:col-span-2">
                            <InputLabel value="Link al hacer clic (opcional)" />
                            <TextInput v-model="assetForm.link_url" placeholder="https://…" class="mt-1 w-full" />
                            <InputError :message="assetForm.errors.link_url" />
                        </div>
                    </form>

                    <div v-if="assets.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="asset in assets" :key="asset.id"
                            class="border rounded-lg p-3 flex gap-3 items-start"
                            :class="[
                                isCurrent(asset) ? 'border-green-400 dark:border-green-600' : 'border-gray-200 dark:border-zinc-700',
                                editingAsset?.id === asset.id ? 'ring-2 ring-[#264ab3]' : '',
                            ]">
                            <img :src="asset.url" class="h-14 w-24 object-contain bg-gray-50 dark:bg-zinc-800 rounded" />
                            <div class="flex-1 min-w-0 text-sm">
                                <div class="font-semibold text-gray-800 dark:text-gray-100 truncate">
                                    {{ asset.label || (asset.slot === 'logo' ? 'Logo' : 'Banner') }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-zinc-400"><span class="capitalize">{{ asset.slot }}</span> · {{ asset.template || 'Todas las plantillas' }}</div>
                                <div class="text-xs text-gray-500 dark:text-zinc-400">
                                    {{ vigencia(asset) }}
                                </div>
                                <span v-if="isCurrent(asset)" class="inline-block mt-1 px-2 py-0.5 text-xs rounded-full font-bold bg-green-100 text-green-800">Vigente hoy</span>
                            </div>
                            <div class="flex flex-col gap-2">
                                <button type="button" @click="editAsset(asset)" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900" title="Editar fechas y datos">
                                    <PencilIcon class="h-5 w-5" />
                                </button>
                                <button type="button" @click="deleteAsset(asset.id)" class="text-red-600 dark:text-rose-400 hover:text-red-900" title="Eliminar">
                                    <TrashIcon class="h-5 w-5" />
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500 dark:text-zinc-400">
                        Aún no hay imágenes. Mientras tanto se usa el logo configurado en el expediente del cliente.
                    </p>
                </div>

                <!-- Firmas -->
                <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-zinc-800">
                    <div class="p-6 text-gray-900 dark:text-gray-100 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 dark:bg-zinc-950 text-left text-xs font-bold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Empleado</th>
                                    <th class="px-6 py-3 bg-gray-50 dark:bg-zinc-950 text-left text-xs font-bold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Empresa</th>
                                    <th class="px-6 py-3 bg-gray-50 dark:bg-zinc-950 text-left text-xs font-bold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Plantilla</th>
                                    <th class="px-6 py-3 bg-gray-50 dark:bg-zinc-950 text-left text-xs font-bold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Estado</th>
                                    <th class="px-6 py-3 bg-gray-50 dark:bg-zinc-950 text-left text-xs font-bold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Último acceso al link</th>
                                    <th class="px-6 py-3 bg-gray-50 dark:bg-zinc-950"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-zinc-900 divide-y divide-gray-200 dark:divide-zinc-800">
                                <tr v-for="s in signatures" :key="s.id" class="hover:bg-gray-50 dark:hover:bg-zinc-800 transition">
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex items-center gap-3">
                                            <img v-if="s.photo_url" :src="s.photo_url" :alt="s.name" class="h-10 w-10 rounded-full object-cover flex-shrink-0" />
                                            <div v-else class="h-10 w-10 rounded-full bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-zinc-400 flex items-center justify-center font-bold flex-shrink-0">
                                                {{ s.name.charAt(0).toUpperCase() }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ s.name }}</div>
                                                <div class="text-gray-500 dark:text-zinc-400">{{ s.position }} <span v-if="s.email">· {{ s.email }}</span></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-zinc-400">{{ s.client }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span v-if="s.template" class="text-gray-800 dark:text-gray-100">{{ s.template }}</span>
                                        <span v-else class="text-amber-700">Sin plantilla</span>
                                        <div class="text-xs text-gray-500 dark:text-zinc-400">{{ s.template_own ? 'Asignada a este empleado' : 'La de la empresa' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="s.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-2 py-1 text-xs rounded-full font-bold">
                                            {{ s.is_active ? 'Activa' : 'Revocada' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-zinc-400">{{ s.last_viewed_at || 'Nunca' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                                        <button @click="copyLink(s)" class="text-[#264ab3] dark:text-blue-400 inline-flex items-center gap-1" title="Copiar link público">
                                            <CheckIcon v-if="copiedId === s.id" class="h-5 w-5" />
                                            <LinkIcon v-else class="h-5 w-5" />
                                        </button>
                                        <Link :href="route('email-signatures.edit', s.id)" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 inline-block">
                                            <PencilIcon class="h-5 w-5" />
                                        </Link>
                                        <button @click="deleteSignature(s.id)" class="text-red-600 dark:text-rose-400 hover:text-red-900 inline-block">
                                            <TrashIcon class="h-5 w-5" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="signatures.length === 0">
                                    <td colspan="6" class="px-6 py-10 text-center text-gray-500">No hay firmas registradas.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
