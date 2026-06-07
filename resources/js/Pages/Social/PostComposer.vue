<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    MegaphoneIcon, PhotoIcon, XMarkIcon, ExclamationTriangleIcon,
    ChevronDownIcon, ChevronUpIcon, LinkIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    client: { type: Object, required: true },
    accounts: { type: Array, default: () => [] },
    post: { type: Object, default: null },
});

const providerLabels = {
    facebook: 'Facebook', instagram: 'Instagram', linkedin: 'LinkedIn',
    tiktok: 'TikTok', youtube: 'YouTube',
};
const providerColors = {
    facebook: 'bg-blue-600',
    instagram: 'bg-gradient-to-tr from-fuchsia-500 to-amber-500',
    linkedin: 'bg-sky-700',
    tiktok: 'bg-zinc-900',
    youtube: 'bg-red-600',
};
const allProviders = ['facebook', 'instagram', 'linkedin', 'tiktok', 'youtube'];

// Categorías de YouTube comunes (subset de la lista oficial v3).
const youtubeCategories = [
    { id: '1',  label: 'Cine y animación' },
    { id: '2',  label: 'Vehículos' },
    { id: '10', label: 'Música' },
    { id: '15', label: 'Animales' },
    { id: '17', label: 'Deportes' },
    { id: '19', label: 'Viajes y eventos' },
    { id: '20', label: 'Gaming' },
    { id: '22', label: 'Gente y blogs' },
    { id: '23', label: 'Comedia' },
    { id: '24', label: 'Entretenimiento' },
    { id: '25', label: 'Noticias y política' },
    { id: '26', label: 'Estilo de vida' },
    { id: '27', label: 'Educación' },
    { id: '28', label: 'Ciencia y tecnología' },
    { id: '29', label: 'ONG y activismo' },
];

const defaultOptions = () => ({
    youtube_type: 'video',
    youtube_privacy: 'public',
    youtube_category_id: '22',
    facebook_type: 'post',
    facebook_audience: 'PUBLIC',
    instagram_type: 'feed',
    tiktok_privacy: 'PUBLIC_TO_EVERYONE',
    tiktok_type: 'video',
    tiktok_disable_comment: false,
    tiktok_disable_duet: false,
    tiktok_disable_stitch: false,
    linkedin_type: 'text',
    linkedin_alt_text: '',
});

const form = useForm({
    title: props.post?.title || '',
    body: props.post?.body || '',
    scheduled_at: props.post?.scheduled_at ? props.post.scheduled_at.slice(0, 16) : '',
    account_ids: props.post?.targets?.map(t => t.social_account_id) || [],
    media: [],
    action: 'save_draft',
    options: { ...defaultOptions(), ...(props.post?.options || {}) },
});

// ── Media preview / state ──────────────────────────────────────────────────
const filePreviews = ref([]);
function onFileChange(e) {
    const files = Array.from(e.target.files);
    form.media = files;
    filePreviews.value = files.map(f => ({
        name: f.name,
        url: URL.createObjectURL(f),
        type: f.type,
        size: f.size,
    }));
}
function removeFile(i) {
    form.media.splice(i, 1);
    filePreviews.value.splice(i, 1);
}

const hasNewMedia = computed(() => filePreviews.value.length > 0);
const hasExistingMedia = computed(() => Array.isArray(props.post?.media) && props.post.media.length > 0);
const hasAnyMedia = computed(() => hasNewMedia.value || hasExistingMedia.value);

const hasVideo = computed(() => {
    if (filePreviews.value.some(p => p.type?.startsWith('video/'))) return true;
    return (props.post?.media || []).some(m => (m.mime || '').startsWith('video/'));
});
const hasImage = computed(() => {
    if (filePreviews.value.some(p => p.type?.startsWith('image/'))) return true;
    return (props.post?.media || []).some(m => (m.mime || '').startsWith('image/'));
});

// ── Accounts grouped by provider ───────────────────────────────────────────
const accountsByProvider = computed(() => {
    const out = {};
    for (const p of allProviders) out[p] = [];
    for (const a of props.accounts) {
        if (out[a.provider]) out[a.provider].push(a);
    }
    return out;
});

function toggleAccount(id) {
    const idx = form.account_ids.indexOf(id);
    if (idx >= 0) form.account_ids.splice(idx, 1);
    else form.account_ids.push(id);
}

const selectedProviders = computed(() => {
    const ids = new Set(form.account_ids);
    const set = new Set();
    for (const a of props.accounts) {
        if (ids.has(a.id)) set.add(a.provider);
    }
    return set;
});

// ── Validation per provider ────────────────────────────────────────────────
const validationErrors = computed(() => {
    const errs = [];
    if (form.account_ids.length === 0) {
        errs.push('Selecciona al menos una cuenta para publicar.');
    }
    if (selectedProviders.value.has('youtube') && !hasVideo.value) {
        errs.push('YouTube requiere un archivo de video.');
    }
    if (selectedProviders.value.has('tiktok') && !hasVideo.value) {
        errs.push('TikTok requiere un archivo de video.');
    }
    if (selectedProviders.value.has('instagram')
        && form.options.instagram_type === 'reel' && !hasVideo.value) {
        errs.push('Instagram Reel requiere un archivo de video.');
    }
    if (selectedProviders.value.has('instagram')
        && form.options.instagram_type === 'feed' && !hasAnyMedia.value) {
        errs.push('Instagram Feed requiere al menos una imagen o video.');
    }
    if (selectedProviders.value.has('instagram')
        && form.options.instagram_type === 'story' && !hasAnyMedia.value) {
        errs.push('Instagram Story requiere una imagen o video corto.');
    }
    if (selectedProviders.value.has('facebook')
        && form.options.facebook_type === 'reel' && !hasVideo.value) {
        errs.push('Facebook Reel requiere un video vertical.');
    }
    if (selectedProviders.value.has('facebook')
        && form.options.facebook_type === 'photo' && !hasImage.value) {
        errs.push('Facebook Foto requiere una imagen.');
    }
    if (selectedProviders.value.has('facebook')
        && form.options.facebook_type === 'video' && !hasVideo.value) {
        errs.push('Facebook Video requiere un archivo de video.');
    }
    if (selectedProviders.value.has('linkedin')
        && form.options.linkedin_type === 'image' && !hasImage.value) {
        errs.push('LinkedIn con imagen requiere una imagen adjunta.');
    }
    if (selectedProviders.value.has('youtube') && !form.title?.trim()) {
        errs.push('YouTube requiere un título.');
    }
    return errs;
});

const canPublish = computed(() => validationErrors.value.length === 0 && !form.processing);

// ── Submit ─────────────────────────────────────────────────────────────────
function submit(action) {
    form.action = action;
    if (action === 'schedule' && !form.scheduled_at) {
        alert('Selecciona fecha de programación.');
        return;
    }
    if ((action === 'publish_now' || action === 'schedule') && !canPublish.value) {
        return; // botón está deshabilitado; doble seguro.
    }
    const url = props.post
        ? route('social.posts.update', [props.client.id, props.post.id])
        : route('social.posts.store', props.client.id);
    form.post(url, { forceFormData: true });
}

// ── Accordion state for advanced options ───────────────────────────────────
const showAdvanced = ref(true);
function connectUrl(provider) {
    return route('social.oauth.redirect', [provider, props.client.id]);
}
</script>

<template>
    <Head :title="post ? 'Editar post' : 'Nuevo post'" />
    <AuthenticatedLayout>
        <div class="p-6 max-w-4xl mx-auto space-y-6">
            <div>
                <Link :href="route('social.clients.show', client.id)" class="text-xs text-primary hover:underline">← Volver</Link>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2 mt-1">
                    <MegaphoneIcon class="w-6 h-6 text-primary" />
                    {{ post ? 'Editar post' : 'Nuevo post' }} — {{ client.business_name }}
                </h1>
            </div>

            <form @submit.prevent="submit('save_draft')" class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-6 space-y-5">

                <!-- ── Account selector grouped by provider ─────────────── -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-200 mb-2">
                        Publicar en
                        <span class="text-xs font-normal text-gray-400">
                            ({{ form.account_ids.length }} seleccionada{{ form.account_ids.length === 1 ? '' : 's' }})
                        </span>
                    </label>

                    <div class="space-y-3">
                        <div v-for="provider in allProviders" :key="provider">
                            <div class="flex items-center justify-between mb-1">
                                <div class="flex items-center gap-2">
                                    <span :class="['w-2 h-2 rounded-full', providerColors[provider]]"></span>
                                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-400">
                                        {{ providerLabels[provider] }}
                                    </span>
                                </div>
                                <a v-if="!accountsByProvider[provider].length"
                                    :href="connectUrl(provider)"
                                    class="inline-flex items-center gap-1 text-xs text-primary hover:underline">
                                    <LinkIcon class="w-3 h-3" />
                                    Conectar {{ providerLabels[provider] }}
                                </a>
                            </div>

                            <p v-if="!accountsByProvider[provider].length"
                                class="text-xs text-gray-400 dark:text-zinc-500 ml-4">
                                Sin cuentas conectadas.
                            </p>

                            <div v-else class="flex flex-wrap gap-2">
                                <button v-for="a in accountsByProvider[provider]" :key="a.id" type="button"
                                    @click="toggleAccount(a.id)"
                                    :class="['inline-flex items-center gap-2 rounded-lg pl-1 pr-3 py-1 text-xs border transition',
                                             form.account_ids.includes(a.id)
                                               ? 'bg-primary/10 text-primary border-primary ring-1 ring-primary'
                                               : 'bg-white dark:bg-zinc-800 text-gray-700 dark:text-zinc-200 border-gray-300 dark:border-zinc-600 hover:border-gray-400']">
                                    <img v-if="a.avatar_url" :src="a.avatar_url" :alt="a.name"
                                        class="w-6 h-6 rounded-full object-cover bg-gray-200" />
                                    <span v-else :class="['w-6 h-6 rounded-full flex items-center justify-center text-white text-[10px] font-bold', providerColors[provider]]">
                                        {{ a.name?.charAt(0)?.toUpperCase() }}
                                    </span>
                                    <span class="font-medium">{{ a.name }}</span>
                                    <CheckCircleIcon v-if="form.account_ids.includes(a.id)" class="w-4 h-4 text-primary" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <p v-if="form.errors.account_ids" class="text-xs text-rose-600 mt-2">{{ form.errors.account_ids }}</p>
                </div>

                <!-- ── Title ────────────────────────────────────────────── -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-200 mb-1">
                        Título
                        <span v-if="selectedProviders.has('youtube')" class="text-rose-600">*</span>
                        <span v-else class="text-gray-400 text-xs font-normal">(opcional)</span>
                    </label>
                    <input v-model="form.title" type="text" maxlength="255"
                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-sm" />
                    <p v-if="selectedProviders.has('youtube')" class="text-xs text-gray-400 mt-1">
                        Se usará como título del video en YouTube (máx. 100 caracteres efectivos).
                    </p>
                </div>

                <!-- ── Body ─────────────────────────────────────────────── -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-200 mb-1">Contenido</label>
                    <textarea v-model="form.body" rows="6"
                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-sm"></textarea>
                    <p class="text-xs text-gray-400 mt-1">{{ (form.body || '').length }} / 2200 caracteres aprox.</p>
                </div>

                <!-- ── Media ────────────────────────────────────────────── -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-200 mb-1">Media (imágenes / video)</label>
                    <label class="flex items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 dark:border-zinc-700 rounded-md cursor-pointer hover:bg-gray-50 dark:hover:bg-zinc-800/40">
                        <input type="file" multiple class="hidden" @change="onFileChange" accept="image/*,video/*" />
                        <div class="text-center text-gray-500">
                            <PhotoIcon class="w-8 h-8 mx-auto" />
                            <p class="text-xs mt-1">Click para adjuntar archivos</p>
                        </div>
                    </label>
                    <div v-if="filePreviews.length" class="grid grid-cols-3 md:grid-cols-4 gap-2 mt-3">
                        <div v-for="(p, i) in filePreviews" :key="i" class="relative border rounded-md overflow-hidden">
                            <img v-if="p.type.startsWith('image/')" :src="p.url" class="w-full h-24 object-cover" />
                            <video v-else-if="p.type.startsWith('video/')" :src="p.url"
                                class="w-full h-24 object-cover" muted />
                            <div v-else class="w-full h-24 flex items-center justify-center bg-gray-100 dark:bg-zinc-800 text-xs">
                                {{ p.name }}
                            </div>
                            <button type="button" @click="removeFile(i)" class="absolute top-1 right-1 bg-white/90 rounded-full p-0.5">
                                <XMarkIcon class="w-3 h-3" />
                            </button>
                        </div>
                    </div>
                    <p v-if="hasExistingMedia && !hasNewMedia" class="text-xs text-gray-400 mt-2">
                        Hay {{ post.media.length }} archivo(s) adjuntos previamente. Sube archivos nuevos para reemplazarlos.
                    </p>
                </div>

                <!-- ── Per-provider options (accordion) ─────────────────── -->
                <div v-if="selectedProviders.size > 0"
                    class="border border-gray-200 dark:border-zinc-700 rounded-lg">
                    <button type="button" @click="showAdvanced = !showAdvanced"
                        class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 dark:text-zinc-200">
                        <span>Opciones por red ({{ selectedProviders.size }})</span>
                        <ChevronUpIcon v-if="showAdvanced" class="w-4 h-4" />
                        <ChevronDownIcon v-else class="w-4 h-4" />
                    </button>

                    <div v-show="showAdvanced" class="px-4 pb-4 space-y-6 border-t border-gray-100 dark:border-zinc-800 pt-4">

                        <!-- YouTube -->
                        <fieldset v-if="selectedProviders.has('youtube')" class="space-y-3">
                            <legend class="text-xs font-semibold uppercase tracking-wide text-red-600 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-red-600"></span> YouTube
                            </legend>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <label class="block">
                                    <span class="block text-xs text-gray-600 dark:text-zinc-300 mb-1">Tipo</span>
                                    <select v-model="form.options.youtube_type"
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-sm">
                                        <option value="video">Video normal (horizontal)</option>
                                        <option value="short">Short (vertical, &lt; 60s)</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="block text-xs text-gray-600 dark:text-zinc-300 mb-1">Privacidad</span>
                                    <select v-model="form.options.youtube_privacy"
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-sm">
                                        <option value="public">Público</option>
                                        <option value="unlisted">No listado</option>
                                        <option value="private">Privado</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="block text-xs text-gray-600 dark:text-zinc-300 mb-1">Categoría</span>
                                    <select v-model="form.options.youtube_category_id"
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-sm">
                                        <option v-for="c in youtubeCategories" :key="c.id" :value="c.id">{{ c.label }}</option>
                                    </select>
                                </label>
                            </div>
                            <p v-if="form.options.youtube_type === 'short'"
                                class="text-xs text-amber-600 flex items-center gap-1">
                                <ExclamationTriangleIcon class="w-3 h-3" />
                                Shorts: video vertical (9:16), máx. 60 segundos.
                            </p>
                        </fieldset>

                        <!-- Facebook -->
                        <fieldset v-if="selectedProviders.has('facebook')" class="space-y-3">
                            <legend class="text-xs font-semibold uppercase tracking-wide text-blue-600 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-blue-600"></span> Facebook
                            </legend>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="block text-xs text-gray-600 dark:text-zinc-300 mb-1">Tipo de publicación</span>
                                    <select v-model="form.options.facebook_type"
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-sm">
                                        <option value="post">Post de texto</option>
                                        <option value="photo">Foto</option>
                                        <option value="reel">Reel</option>
                                        <option value="video">Video normal</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="block text-xs text-gray-600 dark:text-zinc-300 mb-1">Audiencia</span>
                                    <select v-model="form.options.facebook_audience"
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-sm">
                                        <option value="PUBLIC">Público</option>
                                        <option value="FRIENDS">Amigos</option>
                                        <option value="SELF">Solo yo</option>
                                    </select>
                                </label>
                            </div>
                            <p v-if="form.options.facebook_type === 'reel'"
                                class="text-xs text-amber-600 flex items-center gap-1">
                                <ExclamationTriangleIcon class="w-3 h-3" />
                                Facebook Reels requiere video vertical (9:16), entre 3 y 90 segundos.
                            </p>
                        </fieldset>

                        <!-- Instagram -->
                        <fieldset v-if="selectedProviders.has('instagram')" class="space-y-3">
                            <legend class="text-xs font-semibold uppercase tracking-wide text-fuchsia-600 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-fuchsia-600"></span> Instagram
                            </legend>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="block text-xs text-gray-600 dark:text-zinc-300 mb-1">Tipo</span>
                                    <select v-model="form.options.instagram_type"
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-sm">
                                        <option value="feed">Feed post</option>
                                        <option value="reel">Reel</option>
                                        <option value="story">Story</option>
                                    </select>
                                </label>
                            </div>
                            <p v-if="form.options.instagram_type === 'story'"
                                class="text-xs text-amber-600 flex items-center gap-1">
                                <ExclamationTriangleIcon class="w-3 h-3" />
                                Story: solo imagen o video corto (&lt; 15s). Desaparece a las 24 horas.
                            </p>
                            <p v-if="form.options.instagram_type === 'reel'"
                                class="text-xs text-amber-600 flex items-center gap-1">
                                <ExclamationTriangleIcon class="w-3 h-3" />
                                Reel: video vertical (9:16) recomendado, entre 3 y 90 segundos.
                            </p>
                        </fieldset>

                        <!-- TikTok -->
                        <fieldset v-if="selectedProviders.has('tiktok')" class="space-y-3">
                            <legend class="text-xs font-semibold uppercase tracking-wide text-zinc-800 dark:text-zinc-200 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-zinc-900"></span> TikTok
                            </legend>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="block text-xs text-gray-600 dark:text-zinc-300 mb-1">Tipo</span>
                                    <select v-model="form.options.tiktok_type"
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-sm">
                                        <option value="video">Video normal</option>
                                        <option value="draft">Enviar a borradores</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="block text-xs text-gray-600 dark:text-zinc-300 mb-1">Privacidad</span>
                                    <select v-model="form.options.tiktok_privacy"
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-sm">
                                        <option value="PUBLIC_TO_EVERYONE">Público</option>
                                        <option value="MUTUAL_FOLLOW_FRIENDS">Amigos</option>
                                        <option value="SELF_ONLY">Privado</option>
                                    </select>
                                </label>
                            </div>
                            <div class="flex flex-wrap gap-4 pt-1">
                                <label class="inline-flex items-center gap-2 text-xs text-gray-700 dark:text-zinc-200">
                                    <input type="checkbox" v-model="form.options.tiktok_disable_comment"
                                        class="rounded border-gray-300 dark:border-zinc-600" />
                                    Deshabilitar comentarios
                                </label>
                                <label class="inline-flex items-center gap-2 text-xs text-gray-700 dark:text-zinc-200">
                                    <input type="checkbox" v-model="form.options.tiktok_disable_duet"
                                        class="rounded border-gray-300 dark:border-zinc-600" />
                                    Deshabilitar dueto
                                </label>
                                <label class="inline-flex items-center gap-2 text-xs text-gray-700 dark:text-zinc-200">
                                    <input type="checkbox" v-model="form.options.tiktok_disable_stitch"
                                        class="rounded border-gray-300 dark:border-zinc-600" />
                                    Deshabilitar stitch
                                </label>
                            </div>
                        </fieldset>

                        <!-- LinkedIn -->
                        <fieldset v-if="selectedProviders.has('linkedin')" class="space-y-3">
                            <legend class="text-xs font-semibold uppercase tracking-wide text-sky-700 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-sky-700"></span> LinkedIn
                            </legend>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="block text-xs text-gray-600 dark:text-zinc-300 mb-1">Tipo</span>
                                    <select v-model="form.options.linkedin_type"
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-sm">
                                        <option value="text">Post de texto</option>
                                        <option value="image">Post con imagen</option>
                                        <option value="article">Artículo</option>
                                    </select>
                                </label>
                                <label v-if="form.options.linkedin_type === 'image'" class="block">
                                    <span class="block text-xs text-gray-600 dark:text-zinc-300 mb-1">Texto alternativo (opcional)</span>
                                    <input v-model="form.options.linkedin_alt_text" type="text" maxlength="200"
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-sm" />
                                </label>
                            </div>
                        </fieldset>
                    </div>
                </div>

                <!-- ── Schedule ─────────────────────────────────────────── -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-200 mb-1">Programar (opcional)</label>
                    <input v-model="form.scheduled_at" type="datetime-local"
                        class="rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-sm" />
                </div>

                <!-- ── Validation summary ──────────────────────────────── -->
                <div v-if="validationErrors.length"
                    class="rounded-md border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700 px-3 py-2">
                    <div class="flex items-start gap-2 text-xs text-amber-800 dark:text-amber-200">
                        <ExclamationTriangleIcon class="w-4 h-4 mt-0.5 flex-shrink-0" />
                        <div>
                            <p class="font-medium mb-0.5">No se puede publicar todavía:</p>
                            <ul class="list-disc list-inside space-y-0.5">
                                <li v-for="(err, i) in validationErrors" :key="i">{{ err }}</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- ── Actions ─────────────────────────────────────────── -->
                <div class="flex flex-wrap justify-end gap-2 pt-4 border-t border-gray-100 dark:border-zinc-800">
                    <button type="button" @click="submit('save_draft')" :disabled="form.processing"
                        class="px-4 py-2 rounded-md border border-gray-300 dark:border-zinc-600 text-sm text-gray-700 dark:text-zinc-200 hover:bg-gray-50 dark:hover:bg-zinc-800 disabled:opacity-50">
                        Guardar borrador
                    </button>
                    <button type="button" @click="submit('schedule')"
                        :disabled="!canPublish || !form.scheduled_at"
                        :title="!form.scheduled_at ? 'Selecciona fecha para programar' : (validationErrors[0] || '')"
                        class="px-4 py-2 rounded-md bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                        Programar
                    </button>
                    <button type="button" @click="submit('publish_now')"
                        :disabled="!canPublish"
                        :title="validationErrors[0] || ''"
                        class="px-4 py-2 rounded-md bg-primary hover:bg-secondary text-white text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                        Publicar ahora
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
