<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import draggable from 'vuedraggable';
import {
    PlusIcon, TrashIcon, CheckCircleIcon, XCircleIcon, ClockIcon,
    ChatBubbleLeftEllipsisIcon, PlayCircleIcon, DocumentIcon,
    PhotoIcon, ChevronLeftIcon, ChevronRightIcon, XMarkIcon,
    LinkIcon, ArrowsPointingOutIcon, Bars3Icon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    ticketId: { type: Number, required: true },
    items:    { type: Array, default: () => [] },
    canEdit:  { type: Boolean, default: true },
    canApprove: { type: Boolean, default: false }, // cliente puede aprobar
});

const localItems = ref([...props.items]);

// Sync when parent re-renders with new items
import { watch } from 'vue';
watch(() => props.items, (v) => { localItems.value = [...v]; });

const showUploader = ref(false);
const stackParentId = ref(null); // si está set, el upload va al stack de ese frame
const uploadForm = useForm({
    caption: '',
    url: '',
    file: null,
    parent_id: null,
});

function openUploader(parentId = null) {
    stackParentId.value = parentId;
    uploadForm.reset();
    uploadForm.parent_id = parentId;
    showUploader.value = true;
}

function submitUpload() {
    uploadForm.parent_id = stackParentId.value;
    uploadForm.post(route('tickets.canvas.store', props.ticketId), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset();
            showUploader.value = false;
            stackParentId.value = null;
        },
    });
}

function destroyItem(item) {
    if (!confirm('¿Eliminar este frame?')) return;
    router.delete(route('tickets.canvas.destroy', item.id), { preserveScroll: true });
}

function onReorder() {
    router.post(route('tickets.canvas.reorder', props.ticketId), {
        order: localItems.value.map(i => i.id),
    }, { preserveScroll: true, preserveState: true });
}

function updateApproval(item, status) {
    router.post(route('tickets.canvas.update', item.id), {
        approval_status: status,
    }, { preserveScroll: true });
}

// ── Pin mode ───────────────────────────────────────────────
const pinningItem = ref(null);      // parent item (card-level activation)
const pinningPiece = ref(null);     // pieza específica donde se hizo click
const pendingPin = ref(null);       // {x, y}
const pinComment = ref('');

function startPinMode(item) {
    pinningItem.value = item;
    pinningPiece.value = null;
    pendingPin.value = null;
    pinComment.value = '';
}
function exitPinMode() {
    pinningItem.value = null;
    pinningPiece.value = null;
    pendingPin.value = null;
    pinComment.value = '';
}

function onCanvasClick(evt, piece) {
    if (!pinningItem.value) return;
    // Acepta click en el padre o en cualquier hijo del card activo
    const cardId = pinningItem.value.id;
    if (piece.id !== cardId && piece.parent_id !== cardId) return;
    // Sólo imagenes admiten pin (videos/pdf/url no)
    if (piece.type !== 'image') return;
    const rect = evt.currentTarget.getBoundingClientRect();
    const x = ((evt.clientX - rect.left) / rect.width) * 100;
    const y = ((evt.clientY - rect.top) / rect.height) * 100;
    pinningPiece.value = piece;
    pendingPin.value = { x: Math.max(0, Math.min(100, x)), y: Math.max(0, Math.min(100, y)) };
}

function submitPin() {
    if (!pendingPin.value || !pinComment.value.trim() || !pinningPiece.value) return;
    router.post(route('tickets.canvas.pins.store', pinningPiece.value.id), {
        x_pct: pendingPin.value.x,
        y_pct: pendingPin.value.y,
        comment: pinComment.value,
    }, {
        preserveScroll: true,
        onSuccess: () => exitPinMode(),
    });
}

function togglePin(pin) {
    router.post(route('tickets.canvas.pins.toggle', pin.id), {}, { preserveScroll: true });
}
function destroyPin(pin) {
    router.delete(route('tickets.canvas.pins.destroy', pin.id), { preserveScroll: true });
}

// ── Presentation mode ─────────────────────────────────────
const presentingIndex = ref(null);
function openPresentation(i) { presentingIndex.value = i; }
function closePresentation() { presentingIndex.value = null; }
function nextSlide() {
    if (presentingIndex.value === null) return;
    presentingIndex.value = (presentingIndex.value + 1) % localItems.value.length;
}
function prevSlide() {
    if (presentingIndex.value === null) return;
    presentingIndex.value = (presentingIndex.value - 1 + localItems.value.length) % localItems.value.length;
}

const statusStyle = {
    pending:            'bg-gray-100 text-gray-700 dark:bg-zinc-800 dark:text-zinc-300',
    approved:           'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    changes_requested:  'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
};
const statusLabel = {
    pending: 'Pendiente',
    approved: 'Aprobado',
    changes_requested: 'Cambios solicitados',
};
</script>

<template>
    <div>
        <!-- Toolbar -->
        <div class="flex items-center justify-between mb-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800 dark:text-zinc-100">
                    Lienzo / Storyboard
                </h3>
                <p class="text-[11px] text-gray-500 dark:text-zinc-400">
                    Sube frames, videos o referencias. Ordena, comenta con pines y aprueba frame por frame.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button v-if="localItems.length"
                    @click="openPresentation(0)"
                    class="inline-flex items-center gap-1 rounded-md border border-gray-200 dark:border-zinc-700 px-3 py-1.5 text-xs text-gray-700 dark:text-zinc-200 hover:border-[#264ab3]">
                    <PlayCircleIcon class="h-4 w-4" /> Ver presentación
                </button>
                <button v-if="canEdit"
                    @click="showUploader = true"
                    class="inline-flex items-center gap-1 rounded-md bg-[#264ab3] px-3 py-1.5 text-xs font-bold text-white hover:bg-[#193074]">
                    <PlusIcon class="h-4 w-4" /> Subir frame
                </button>
            </div>
        </div>

        <!-- Estado vacío -->
        <div v-if="!localItems.length" class="rounded-lg border border-dashed border-gray-300 dark:border-zinc-700 p-8 text-center">
            <PhotoIcon class="h-10 w-10 text-gray-300 dark:text-zinc-600 mx-auto mb-2" />
            <p class="text-sm font-medium text-gray-700 dark:text-zinc-200">Aún no hay frames</p>
            <p class="text-[11px] text-gray-500 dark:text-zinc-400 max-w-md mx-auto">
                Sube imágenes, videos o PDFs en el orden de la secuencia para que el cliente pueda revisarlos y aprobarlos.
            </p>
        </div>

        <!-- Grid sortable -->
        <draggable v-else
            v-model="localItems"
            :item-key="i => i.id"
            handle=".drag-handle"
            :disabled="!canEdit"
            @end="onReorder"
            class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
            <template #item="{ element: item, index }">
                <div class="rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden flex flex-col"
                    :class="{ 'ring-2 ring-[#264ab3]': pinningItem?.id === item.id }">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-3 py-2 border-b border-gray-100 dark:border-zinc-700 bg-gray-50/40 dark:bg-zinc-950/50">
                        <div class="flex items-center gap-2 min-w-0">
                            <span v-if="canEdit" class="drag-handle cursor-grab text-gray-400 hover:text-gray-600">
                                <Bars3Icon class="h-4 w-4" />
                            </span>
                            <span class="text-[10px] font-mono text-gray-500">#{{ index + 1 }}</span>
                            <span class="text-xs font-medium text-gray-700 dark:text-zinc-200 truncate">
                                {{ item.caption || item.file_name || 'Sin título' }}
                            </span>
                        </div>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase tracking-wide"
                            :class="statusStyle[item.approval_status] || statusStyle.pending">
                            {{ statusLabel[item.approval_status] || 'Pendiente' }}
                        </span>
                    </div>

                    <!-- Media + pins (parent + stacked children) -->
                    <div class="flex flex-col divide-y divide-gray-100 dark:divide-zinc-800">
                        <template v-for="piece in [item, ...(item.children || [])]" :key="piece.id">
                            <div class="relative bg-gray-50 dark:bg-zinc-950 flex items-center justify-center min-h-[160px] group/piece"
                                :class="{ 'cursor-crosshair': pinningItem && (pinningItem.id === piece.id || pinningItem.id === piece.parent_id) && piece.type === 'image' }"
                                @click="onCanvasClick($event, piece)">
                                <img v-if="piece.type === 'image' && piece.file_path"
                                    :src="'/storage/' + piece.file_path"
                                    class="max-h-[260px] w-auto object-contain pointer-events-none select-none" />
                                <video v-else-if="piece.type === 'video' && piece.file_path"
                                    :src="'/storage/' + piece.file_path"
                                    autoplay muted loop playsinline controls
                                    class="max-h-[260px] w-full object-contain"
                                    @click.stop></video>
                                <a v-else-if="piece.type === 'pdf' && piece.file_path"
                                    :href="'/storage/' + piece.file_path" target="_blank"
                                    class="flex flex-col items-center gap-2 p-6 text-[#264ab3] hover:underline"
                                    @click.stop>
                                    <DocumentIcon class="h-10 w-10" />
                                    <span class="text-xs font-medium">{{ piece.file_name }}</span>
                                </a>
                                <a v-else-if="piece.url"
                                    :href="piece.url" target="_blank"
                                    class="flex flex-col items-center gap-2 p-6 text-[#264ab3] hover:underline truncate max-w-full"
                                    @click.stop>
                                    <LinkIcon class="h-10 w-10" />
                                    <span class="text-xs truncate max-w-[240px]">{{ piece.url }}</span>
                                </a>

                                <!-- Badge "stack #N" en hijos -->
                                <span v-if="piece.parent_id"
                                    class="absolute top-1 left-1 bg-black/60 text-white text-[9px] px-1.5 py-0.5 rounded font-mono">
                                    stack
                                </span>

                                <!-- Delete child -->
                                <button v-if="piece.parent_id && canEdit"
                                    @click.stop="destroyItem(piece)"
                                    class="absolute top-1 right-1 hidden group-hover/piece:flex items-center justify-center h-6 w-6 rounded-full bg-black/60 hover:bg-rose-600 text-white"
                                    title="Quitar del stack">
                                    <TrashIcon class="h-3.5 w-3.5" />
                                </button>

                                <!-- Pins existentes -->
                                <span v-for="pin in (piece.pins || [])" :key="pin.id"
                                    class="absolute -translate-x-1/2 -translate-y-1/2 group"
                                    :style="{ left: pin.x_pct + '%', top: pin.y_pct + '%' }">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-bold text-white shadow-md cursor-pointer"
                                        :class="pin.resolved ? 'bg-emerald-500' : 'bg-rose-500'"
                                        :title="pin.comment">
                                        {{ pin.resolved ? '✓' : '!' }}
                                    </span>
                                    <div class="hidden group-hover:block absolute z-10 left-7 top-0 w-56 rounded-lg bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 p-2 shadow-lg text-[11px] text-gray-700 dark:text-zinc-200">
                                        <div class="font-bold mb-1">{{ pin.user?.name || 'Usuario' }}</div>
                                        <p class="whitespace-pre-wrap">{{ pin.comment }}</p>
                                        <div class="flex gap-2 mt-2 pt-2 border-t border-gray-100 dark:border-zinc-700">
                                            <button @click.stop="togglePin(pin)" class="text-[10px] text-emerald-600 hover:underline">
                                                {{ pin.resolved ? 'Reabrir' : 'Resolver' }}
                                            </button>
                                            <button @click.stop="destroyPin(pin)" class="text-[10px] text-rose-600 hover:underline">
                                                Eliminar
                                            </button>
                                        </div>
                                    </div>
                                </span>

                                <span v-if="pinningPiece?.id === piece.id && pendingPin"
                                    class="absolute -translate-x-1/2 -translate-y-1/2 flex h-6 w-6 items-center justify-center rounded-full bg-amber-500 text-white text-[10px] font-bold animate-pulse"
                                    :style="{ left: pendingPin.x + '%', top: pendingPin.y + '%' }">●</span>
                            </div>
                        </template>

                        <!-- Botón agregar al stack -->
                        <button v-if="canEdit"
                            @click="openUploader(item.id)"
                            class="flex items-center justify-center gap-1 py-2 text-[11px] text-gray-500 hover:text-[#264ab3] hover:bg-gray-50 dark:hover:bg-zinc-950 transition">
                            <PlusIcon class="h-3.5 w-3.5" /> Agregar al stack
                        </button>
                    </div>

                    <!-- Pin compose -->
                    <div v-if="pinningItem?.id === item.id" class="px-3 py-2 border-t border-gray-100 dark:border-zinc-700 bg-amber-50 dark:bg-amber-900/20">
                        <p class="text-[11px] text-amber-700 dark:text-amber-300 mb-1">
                            {{ pendingPin ? 'Escribe tu comentario y guarda.' : 'Haz click sobre la imagen para colocar el pin.' }}
                        </p>
                        <div class="flex items-center gap-2">
                            <input v-model="pinComment" placeholder="Comentario…"
                                :disabled="!pendingPin"
                                class="flex-1 rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-950 text-xs" />
                            <button @click="submitPin" :disabled="!pendingPin || !pinComment.trim()"
                                class="rounded-md bg-[#264ab3] px-2 py-1 text-[11px] font-bold text-white hover:bg-[#193074] disabled:opacity-40">
                                Guardar
                            </button>
                            <button @click="exitPinMode"
                                class="rounded-md border border-gray-300 dark:border-zinc-700 px-2 py-1 text-[11px]">
                                Cancelar
                            </button>
                        </div>
                    </div>

                    <!-- Footer actions -->
                    <div class="flex items-center justify-between px-3 py-2 border-t border-gray-100 dark:border-zinc-700 text-[11px]">
                        <div class="flex items-center gap-2 text-gray-500 dark:text-zinc-400">
                            <button v-if="item.type === 'image' || (item.children || []).some(c => c.type === 'image')"
                                @click="startPinMode(item)"
                                class="inline-flex items-center gap-0.5 hover:text-[#264ab3]" title="Comentar con pin">
                                <ChatBubbleLeftEllipsisIcon class="h-3.5 w-3.5" />
                                {{ (item.pins || []).length + (item.children || []).reduce((a, c) => a + (c.pins?.length || 0), 0) }}
                            </button>
                            <span class="text-gray-400">{{ (item.children || []).length ? `stack · ${(item.children || []).length + 1}` : item.type }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <button v-if="canApprove && item.approval_status !== 'approved'"
                                @click="updateApproval(item, 'approved')"
                                class="inline-flex items-center gap-0.5 text-emerald-600 hover:text-emerald-700"
                                title="Aprobar">
                                <CheckCircleIcon class="h-4 w-4" />
                            </button>
                            <button v-if="canApprove && item.approval_status !== 'changes_requested'"
                                @click="updateApproval(item, 'changes_requested')"
                                class="inline-flex items-center gap-0.5 text-orange-600 hover:text-orange-700"
                                title="Solicitar cambios">
                                <XCircleIcon class="h-4 w-4" />
                            </button>
                            <button v-if="canApprove && item.approval_status !== 'pending'"
                                @click="updateApproval(item, 'pending')"
                                class="inline-flex items-center gap-0.5 text-gray-500 hover:text-gray-700"
                                title="Marcar pendiente">
                                <ClockIcon class="h-4 w-4" />
                            </button>
                            <button v-if="canEdit"
                                @click="destroyItem(item)"
                                class="inline-flex items-center gap-0.5 text-gray-400 hover:text-rose-600"
                                title="Eliminar">
                                <TrashIcon class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </draggable>

        <!-- Modal upload -->
        <div v-if="showUploader"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="showUploader = false">
            <div class="w-full max-w-md rounded-2xl bg-white dark:bg-zinc-900 p-5 shadow-xl border border-gray-200 dark:border-zinc-700">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-bold text-gray-800 dark:text-zinc-100">
                        {{ stackParentId ? 'Agregar al stack' : 'Subir frame' }}
                    </h3>
                    <button @click="showUploader = false"><XMarkIcon class="h-5 w-5 text-gray-400" /></button>
                </div>
                <p v-if="stackParentId" class="text-[11px] text-gray-500 dark:text-zinc-400 mb-3">
                    Se apilará dentro del frame actual (imagen + gif/video + imagen…) y se aprobará como una sola pieza.
                </p>
                <form @submit.prevent="submitUpload" class="space-y-3">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 dark:text-zinc-300 uppercase">Título / Caption</label>
                        <input v-model="uploadForm.caption" type="text" placeholder="Ej. Frame 03 — Hero"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-950 text-sm" />
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 dark:text-zinc-300 uppercase">Archivo (imagen, video o PDF)</label>
                        <input type="file" accept="image/*,video/*,application/pdf"
                            @change="e => uploadForm.file = e.target.files[0]"
                            class="mt-1 w-full text-xs text-gray-700 dark:text-zinc-200" />
                        <p class="text-[10px] text-gray-400 mt-1">Máx 100 MB. Acepta jpg, png, mp4, mov, pdf…</p>
                        <p v-if="uploadForm.errors.file" class="text-xs text-rose-500 mt-1">{{ uploadForm.errors.file }}</p>
                    </div>
                    <div class="text-center text-[10px] text-gray-400">— o —</div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 dark:text-zinc-300 uppercase">URL externa</label>
                        <input v-model="uploadForm.url" type="url" placeholder="https://… (Drive, XD, Figma…)"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-950 text-sm" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showUploader = false"
                            class="rounded-md border border-gray-300 dark:border-zinc-700 px-3 py-1.5 text-sm">Cancelar</button>
                        <button type="submit" :disabled="uploadForm.processing"
                            class="rounded-md bg-[#264ab3] px-3 py-1.5 text-sm font-bold text-white hover:bg-[#193074] disabled:opacity-50">
                            Subir
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Presentation mode -->
        <div v-if="presentingIndex !== null"
            class="fixed inset-0 z-50 bg-black/95 flex items-center justify-center"
            @keydown.esc="closePresentation"
            tabindex="0">
            <button @click="closePresentation"
                class="absolute top-4 right-4 text-white/70 hover:text-white">
                <XMarkIcon class="h-7 w-7" />
            </button>
            <button @click="prevSlide"
                class="absolute left-4 text-white/70 hover:text-white">
                <ChevronLeftIcon class="h-10 w-10" />
            </button>
            <button @click="nextSlide"
                class="absolute right-4 text-white/70 hover:text-white">
                <ChevronRightIcon class="h-10 w-10" />
            </button>

            <div class="max-w-5xl max-h-[85vh] w-full px-12 flex flex-col items-center">
                <div class="text-white/60 text-[11px] mb-2 font-mono">
                    {{ presentingIndex + 1 }} / {{ localItems.length }} ·
                    {{ localItems[presentingIndex]?.caption || localItems[presentingIndex]?.file_name }}
                </div>
                <div class="overflow-y-auto max-h-[80vh] w-full flex flex-col items-center gap-2 scroll-smooth">
                    <template v-for="piece in [localItems[presentingIndex], ...(localItems[presentingIndex]?.children || [])]" :key="piece.id">
                        <img v-if="piece.type === 'image'"
                            :src="'/storage/' + piece.file_path"
                            class="max-w-full object-contain rounded-lg" />
                        <video v-else-if="piece.type === 'video'"
                            :src="'/storage/' + piece.file_path"
                            autoplay muted loop playsinline controls
                            class="max-w-full rounded-lg"></video>
                        <a v-else-if="piece.type === 'pdf'"
                            :href="'/storage/' + piece.file_path" target="_blank"
                            class="text-white underline text-sm">{{ piece.file_name }}</a>
                        <a v-else-if="piece.url"
                            :href="piece.url" target="_blank"
                            class="text-white underline text-sm">{{ piece.url }}</a>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
