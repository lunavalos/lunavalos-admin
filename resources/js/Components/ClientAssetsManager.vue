<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import {
    PlusIcon, TrashIcon, PencilSquareIcon, DocumentIcon, LinkIcon,
    SwatchIcon, PaintBrushIcon, PhotoIcon, ArrowTopRightOnSquareIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    clientId: { type: Number, required: true },
    assets:   { type: Array, default: () => [] },
    compact:  { type: Boolean, default: false },
    canEdit:  { type: Boolean, default: true },
});

const KINDS = [
    { value: 'logo',          label: 'Logo',           icon: PhotoIcon,   needs: 'file' },
    { value: 'branding',      label: 'Manual de marca',icon: PaintBrushIcon, needs: 'file' },
    { value: 'typography',    label: 'Tipografía',     icon: PencilSquareIcon, needs: 'value' },
    { value: 'color_palette', label: 'Paleta de colores', icon: SwatchIcon, needs: 'value' },
    { value: 'document',      label: 'Documento',      icon: DocumentIcon, needs: 'file' },
    { value: 'url',           label: 'URL / Link',     icon: LinkIcon,     needs: 'url' },
    { value: 'note',          label: 'Nota',           icon: PencilSquareIcon, needs: 'value' },
];

const kindMeta = (kind) => KINDS.find(k => k.value === kind) || KINDS[0];

const showForm = ref(false);
const editing = ref(null);

const form = useForm({
    kind:  'document',
    label: '',
    file:  null,
    url:   '',
    // typography: { family, weight }
    // color_palette: { colors: ['#...'] }
    // note: { text: '...' }
    value: null,
    typography_family: '',
    typography_weight: '',
    note_text: '',
    palette_colors: ['#264ab3', '#F39200'],
});

function resetForm() {
    form.reset();
    form.palette_colors = ['#264ab3', '#F39200'];
    editing.value = null;
    showForm.value = false;
}

function openCreate(kind = 'document') {
    resetForm();
    form.kind = kind;
    showForm.value = true;
}

function openEdit(asset) {
    resetForm();
    editing.value = asset;
    form.kind = asset.kind;
    form.label = asset.label;
    form.url = asset.url || '';
    const v = asset.value || {};
    form.typography_family = v.family || '';
    form.typography_weight = v.weight || '';
    form.note_text = v.text || '';
    form.palette_colors = Array.isArray(v.colors) && v.colors.length ? [...v.colors] : ['#264ab3'];
    showForm.value = true;
}

function buildValuePayload() {
    if (form.kind === 'typography') {
        return { family: form.typography_family, weight: form.typography_weight };
    }
    if (form.kind === 'color_palette') {
        return { colors: form.palette_colors.filter(Boolean) };
    }
    if (form.kind === 'note') {
        return { text: form.note_text };
    }
    return null;
}

function submit() {
    form.value = buildValuePayload();

    const url = editing.value
        ? route('clients.assets.update', editing.value.id)
        : route('clients.assets.store', props.clientId);

    form.post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => resetForm(),
    });
}

function destroy(asset) {
    if (!confirm(`¿Eliminar "${asset.label}"?`)) return;
    router.delete(route('clients.assets.destroy', asset.id), { preserveScroll: true });
}

function addPaletteColor() {
    form.palette_colors.push('#ffffff');
}
function removePaletteColor(i) {
    form.palette_colors.splice(i, 1);
}

const grouped = computed(() => {
    const map = {};
    KINDS.forEach(k => map[k.value] = []);
    for (const a of props.assets) {
        (map[a.kind] = map[a.kind] || []).push(a);
    }
    return map;
});
</script>

<template>
    <div>
        <!-- Header con acciones rápidas -->
        <div class="flex items-center justify-between mb-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800 dark:text-zinc-100">
                    Documentos y Archivos del Cliente
                </h3>
                <p class="text-[11px] text-gray-500 dark:text-zinc-400">
                    Logos, tipografía, paleta, links y archivos relevantes.
                </p>
            </div>
            <button v-if="canEdit" @click="openCreate('document')"
                class="inline-flex items-center gap-1 rounded-md bg-[#264ab3] px-3 py-1.5 text-xs font-bold text-white hover:bg-[#193074]">
                <PlusIcon class="h-4 w-4" /> Agregar
            </button>
        </div>

        <!-- Atajos por tipo -->
        <div v-if="canEdit" class="flex flex-wrap gap-1.5 mb-4">
            <button v-for="k in KINDS" :key="k.value" @click="openCreate(k.value)"
                class="inline-flex items-center gap-1 rounded-full border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-2.5 py-1 text-[11px] text-gray-600 dark:text-zinc-300 hover:border-[#264ab3] hover:text-[#264ab3]">
                <component :is="k.icon" class="h-3 w-3" />
                {{ k.label }}
            </button>
        </div>

        <!-- Lista -->
        <div v-if="assets.length === 0" class="rounded-lg border border-dashed border-gray-300 dark:border-zinc-700 p-6 text-center text-xs text-gray-500 dark:text-zinc-400">
            <template v-if="canEdit">Sin activos registrados aún. Empieza agregando el logo o la tipografía del cliente.</template>
            <template v-else>Aún no hay documentos compartidos en este expediente.</template>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-2">
            <div v-for="a in assets" :key="a.id"
                class="group rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-3 hover:border-[#264ab3] transition">
                <div class="flex items-start gap-3">
                    <!-- Icono / preview -->
                    <div class="shrink-0">
                        <img v-if="a.kind === 'logo' && a.file_path && a.mime && a.mime.startsWith('image/')"
                            :src="'/storage/' + a.file_path"
                            class="h-12 w-12 rounded-md object-contain bg-gray-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700" />
                        <div v-else-if="a.kind === 'color_palette' && a.value?.colors?.length"
                            class="h-12 w-12 rounded-md overflow-hidden flex flex-wrap border border-gray-100 dark:border-zinc-700">
                            <span v-for="(c, i) in a.value.colors.slice(0,4)" :key="i"
                                class="flex-1 min-w-[50%]" :style="{ background: c }"></span>
                        </div>
                        <div v-else
                            class="h-12 w-12 rounded-md bg-gray-100 dark:bg-zinc-800 flex items-center justify-center">
                            <component :is="kindMeta(a.kind).icon" class="h-5 w-5 text-gray-500 dark:text-zinc-400" />
                        </div>
                    </div>

                    <!-- Contenido -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-gray-800 dark:text-zinc-100 truncate">
                                {{ a.label }}
                            </p>
                            <span class="text-[10px] uppercase tracking-wide text-gray-400 dark:text-zinc-500 shrink-0">
                                {{ kindMeta(a.kind).label }}
                            </span>
                        </div>

                        <!-- Detalles según tipo -->
                        <div class="mt-1 text-[11px] text-gray-600 dark:text-zinc-400">
                            <div v-if="a.kind === 'typography' && a.value">
                                <span class="font-medium" :style="{ fontFamily: a.value.family }">
                                    {{ a.value.family }}
                                </span>
                                <span v-if="a.value.weight" class="text-gray-400"> · {{ a.value.weight }}</span>
                            </div>
                            <div v-else-if="a.kind === 'color_palette' && a.value?.colors" class="flex flex-wrap gap-1 mt-1">
                                <span v-for="(c, i) in a.value.colors" :key="i"
                                    class="inline-flex items-center gap-1 rounded border border-gray-200 dark:border-zinc-700 px-1.5 py-0.5 font-mono text-[10px]"
                                    :title="c">
                                    <span class="h-3 w-3 rounded" :style="{ background: c }"></span>
                                    {{ c }}
                                </span>
                            </div>
                            <div v-else-if="a.kind === 'note' && a.value?.text" class="italic line-clamp-2">
                                {{ a.value.text }}
                            </div>
                            <a v-else-if="a.url" :href="a.url" target="_blank"
                                class="inline-flex items-center gap-1 text-[#264ab3] hover:underline truncate">
                                <ArrowTopRightOnSquareIcon class="h-3 w-3" />
                                <span class="truncate">{{ a.url }}</span>
                            </a>
                            <a v-else-if="a.file_path" :href="'/storage/' + a.file_path" target="_blank"
                                class="inline-flex items-center gap-1 text-[#264ab3] hover:underline truncate">
                                <DocumentIcon class="h-3 w-3" />
                                <span class="truncate">{{ a.file_name || 'Archivo' }}</span>
                            </a>
                        </div>

                        <div v-if="canEdit" class="mt-2 flex items-center gap-2 opacity-60 group-hover:opacity-100 transition">
                            <button @click="openEdit(a)" class="text-[10px] text-gray-500 hover:text-[#264ab3] inline-flex items-center gap-0.5">
                                <PencilSquareIcon class="h-3 w-3" /> Editar
                            </button>
                            <button @click="destroy(a)" class="text-[10px] text-gray-500 hover:text-rose-600 inline-flex items-center gap-0.5">
                                <TrashIcon class="h-3 w-3" /> Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de form -->
        <div v-if="showForm"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="resetForm">
            <div class="w-full max-w-md rounded-2xl bg-white dark:bg-zinc-900 p-5 shadow-xl border border-gray-200 dark:border-zinc-700">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-bold text-gray-800 dark:text-zinc-100">
                        {{ editing ? 'Editar activo' : 'Nuevo activo' }}
                    </h3>
                    <button @click="resetForm" class="text-gray-400 hover:text-gray-700"><XMarkIcon class="h-5 w-5" /></button>
                </div>

                <form @submit.prevent="submit" class="space-y-3">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 dark:text-zinc-300 uppercase tracking-wide">Tipo</label>
                        <select v-model="form.kind"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-950 text-sm">
                            <option v-for="k in KINDS" :key="k.value" :value="k.value">{{ k.label }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 dark:text-zinc-300 uppercase tracking-wide">Nombre / Etiqueta</label>
                        <input v-model="form.label" type="text" required
                            placeholder="Ej. Logo principal, Tipografía body…"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-950 text-sm" />
                        <p v-if="form.errors.label" class="text-xs text-rose-500 mt-1">{{ form.errors.label }}</p>
                    </div>

                    <!-- Archivo -->
                    <div v-if="['logo','branding','document'].includes(form.kind)">
                        <label class="block text-[11px] font-bold text-gray-600 dark:text-zinc-300 uppercase tracking-wide">
                            Archivo {{ editing ? '(opcional para reemplazar)' : '' }}
                        </label>
                        <input type="file" @change="e => form.file = e.target.files[0]"
                            class="mt-1 w-full text-xs text-gray-700 dark:text-zinc-200" />
                        <p v-if="form.errors.file" class="text-xs text-rose-500 mt-1">{{ form.errors.file }}</p>
                    </div>

                    <!-- URL -->
                    <div v-if="form.kind === 'url'">
                        <label class="block text-[11px] font-bold text-gray-600 dark:text-zinc-300 uppercase tracking-wide">URL</label>
                        <input v-model="form.url" type="url" required
                            placeholder="https://…"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-950 text-sm" />
                    </div>

                    <!-- Tipografía -->
                    <div v-if="form.kind === 'typography'" class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-600 dark:text-zinc-300 uppercase tracking-wide">Familia</label>
                            <input v-model="form.typography_family" type="text" required placeholder="Inter, Poppins…"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-950 text-sm" />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-600 dark:text-zinc-300 uppercase tracking-wide">Peso / Variante</label>
                            <input v-model="form.typography_weight" type="text" placeholder="400, Bold…"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-950 text-sm" />
                        </div>
                    </div>

                    <!-- Paleta -->
                    <div v-if="form.kind === 'color_palette'">
                        <label class="block text-[11px] font-bold text-gray-600 dark:text-zinc-300 uppercase tracking-wide mb-1">Paleta</label>
                        <div class="space-y-1">
                            <div v-for="(c, i) in form.palette_colors" :key="i" class="flex items-center gap-2">
                                <input type="color" v-model="form.palette_colors[i]" class="h-8 w-10 rounded border border-gray-300 dark:border-zinc-700" />
                                <input type="text" v-model="form.palette_colors[i]" class="flex-1 rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-950 text-xs font-mono" />
                                <button type="button" @click="removePaletteColor(i)" class="text-rose-500 hover:text-rose-700">
                                    <TrashIcon class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <button type="button" @click="addPaletteColor"
                            class="mt-2 inline-flex items-center gap-1 text-[11px] text-[#264ab3] hover:underline">
                            <PlusIcon class="h-3 w-3" /> Agregar color
                        </button>
                    </div>

                    <!-- Nota -->
                    <div v-if="form.kind === 'note'">
                        <label class="block text-[11px] font-bold text-gray-600 dark:text-zinc-300 uppercase tracking-wide">Nota</label>
                        <textarea v-model="form.note_text" rows="3"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-950 text-sm"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="resetForm"
                            class="rounded-md border border-gray-300 dark:border-zinc-700 px-3 py-1.5 text-sm">Cancelar</button>
                        <button type="submit" :disabled="form.processing"
                            class="rounded-md bg-[#264ab3] px-3 py-1.5 text-sm font-bold text-white hover:bg-[#193074] disabled:opacity-50">
                            {{ editing ? 'Guardar cambios' : 'Crear' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
