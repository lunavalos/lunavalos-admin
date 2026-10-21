<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import {
    PhotoIcon, ArrowUpTrayIcon, ClipboardIcon, CheckIcon, CodeBracketIcon, ArrowPathIcon, TrashIcon,
} from '@heroicons/vue/24/outline';

/**
 * Imágenes de la plantilla servidas desde nuestro dominio.
 *
 * Funciona por JSON y no por Inertia: una recarga de página tiraría el HTML
 * que se está editando y todavía no se guarda.
 */
const props = defineProps({
    templateId: { type: Number, default: null },
    draftKey:   { type: String, default: null },
});

const emit = defineEmits(['insert']);

const media = ref([]);
const uploading = ref(false);
const error = ref('');
const copiedId = ref(null);
const uploadInput = ref(null);
const replaceInput = ref(null);
const replacing = ref(null);

const scope = () => (props.templateId ? { template_id: props.templateId } : { draft_key: props.draftKey });

const errorMessage = (e) => e.response?.data?.message
    || Object.values(e.response?.data?.errors ?? {})[0]?.[0]
    || 'No se pudo completar la acción.';

const load = async () => {
    const { data } = await axios.get(route('signature-media.index'), { params: scope() });
    media.value = data.media;
};

onMounted(load);

const upload = async (event) => {
    const files = [...event.target.files];
    if (!files.length) return;

    const body = new FormData();
    Object.entries(scope()).forEach(([k, v]) => body.append(k, v));
    files.forEach(f => body.append('files[]', f));

    uploading.value = true;
    error.value = '';
    try {
        const { data } = await axios.post(route('signature-media.store'), body);
        media.value = [...data.media, ...media.value];
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        uploading.value = false;
        event.target.value = '';
    }
};

const startReplace = (item) => {
    replacing.value = item;
    replaceInput.value.click();
};

const replace = async (event) => {
    const file = event.target.files[0];
    const item = replacing.value;
    event.target.value = '';
    if (!file || !item) return;

    const body = new FormData();
    body.append('file', file);

    error.value = '';
    try {
        const { data } = await axios.post(route('signature-media.replace', item.id), body);
        // Misma URL: se le agrega un parámetro solo aquí para que la miniatura no salga de caché.
        Object.assign(item, data.media, { bust: Date.now() });
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        replacing.value = null;
    }
};

const remove = async (item) => {
    if (!confirm(`¿Eliminar ${item.name}?`)) return;

    error.value = '';
    try {
        await axios.delete(route('signature-media.destroy', item.id));
        media.value = media.value.filter(m => m.id !== item.id);
    } catch (e) {
        error.value = errorMessage(e);
    }
};

const copyUrl = async (item) => {
    try {
        await navigator.clipboard.writeText(item.url);
        copiedId.value = item.id;
        setTimeout(() => copiedId.value = null, 1500);
    } catch (e) {
        window.prompt('Copia la URL:', item.url);
    }
};

const imgTag = (item) => {
    const size = item.width && item.height ? ` width="${item.width}" height="${item.height}"` : '';
    return `<img src="${item.url}"${size} alt="" style="display:block; border:0;">`;
};

const thumb = (item) => (item.bust ? `${item.url}?v=${item.bust}` : item.url);

const kb = (bytes) => (bytes ? `${Math.round(bytes / 1024)} KB` : '');
</script>

<template>
    <div class="border border-gray-200 dark:border-zinc-800 rounded-xl p-4">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2">
                <PhotoIcon class="h-5 w-5 text-[#264ab3]" />
                <span class="font-bold text-sm text-gray-800 dark:text-gray-100">Imágenes de la plantilla</span>
            </div>
            <button type="button" @click="uploadInput.click()" :disabled="uploading"
                class="bg-[#264ab3] text-white px-3 py-1.5 rounded-md font-bold text-xs flex items-center gap-1 disabled:opacity-50">
                <ArrowUpTrayIcon class="h-4 w-4" />
                {{ uploading ? 'Subiendo…' : 'Subir imágenes' }}
            </button>
            <input ref="uploadInput" type="file" multiple accept="image/png,image/jpeg,image/gif" class="hidden" @change="upload" />
            <input ref="replaceInput" type="file" accept="image/png,image/jpeg,image/gif" class="hidden" @change="replace" />
        </div>
        <p class="text-xs text-gray-500 dark:text-zinc-400 mb-3">
            Íconos, logos, QR y separadores se sirven desde tu dominio. Usa PNG o JPG (Outlook no muestra SVG ni WebP).
            Coloca el cursor en el editor y da clic en <b>Insertar</b>. <b>Reemplazar</b> conserva la URL: las firmas que ya la usan muestran la nueva imagen.
        </p>

        <p v-if="error" class="mb-3 text-sm text-red-600 dark:text-rose-400">{{ error }}</p>

        <div v-if="media.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div v-for="item in media" :key="item.id" class="border border-gray-200 dark:border-zinc-700 rounded-lg p-2 flex gap-3">
                <div class="h-16 w-16 flex-shrink-0 rounded bg-[repeating-conic-gradient(#f3f4f6_0_25%,#fff_0_50%)] [background-size:12px_12px] flex items-center justify-center overflow-hidden">
                    <img :src="thumb(item)" class="max-h-16 max-w-16 object-contain" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-semibold text-gray-800 dark:text-gray-100 truncate" :title="item.name">{{ item.name }}</div>
                    <div class="text-[11px] text-gray-500 dark:text-zinc-400">
                        <span v-if="item.width">{{ item.width }}×{{ item.height }} px · </span>{{ kb(item.size) }}
                    </div>
                    <input :value="item.url" readonly @focus="$event.target.select()"
                        class="mt-1 w-full text-[11px] px-1.5 py-0.5 rounded border border-gray-200 dark:border-zinc-700 dark:bg-zinc-900 text-gray-600 dark:text-zinc-300" />
                    <div class="mt-1.5 flex gap-3 text-xs">
                        <button type="button" @click="emit('insert', imgTag(item))" class="text-[#264ab3] dark:text-blue-400 font-semibold flex items-center gap-0.5" title="Insertar <img> en el cursor">
                            <CodeBracketIcon class="h-4 w-4" /> Insertar
                        </button>
                        <button type="button" @click="copyUrl(item)" class="text-gray-600 dark:text-zinc-300 flex items-center gap-0.5" title="Copiar URL">
                            <CheckIcon v-if="copiedId === item.id" class="h-4 w-4" />
                            <ClipboardIcon v-else class="h-4 w-4" />
                            URL
                        </button>
                        <button type="button" @click="startReplace(item)" class="text-gray-600 dark:text-zinc-300" title="Reemplazar conservando la URL">
                            <ArrowPathIcon class="h-4 w-4" />
                        </button>
                        <button type="button" @click="remove(item)" class="text-red-600 dark:text-rose-400" title="Eliminar">
                            <TrashIcon class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <p v-else class="text-sm text-gray-500 dark:text-zinc-400">Aún no hay imágenes para esta plantilla.</p>
    </div>
</template>
