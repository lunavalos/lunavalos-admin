<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PostPreview from './PostPreview.vue';
import SocialAvatar from '@/Components/SocialAvatar.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    MegaphoneIcon, PhotoIcon, XMarkIcon, ExclamationTriangleIcon,
    ChevronDownIcon, ChevronUpIcon, LinkIcon, CheckCircleIcon,
    ArrowPathIcon, DocumentDuplicateIcon, ArrowTopRightOnSquareIcon, EyeIcon,
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
    instagram_type: 'feed',
    tiktok_privacy: 'PUBLIC_TO_EVERYONE',
    tiktok_type: 'video',
    tiktok_disable_comment: false,
    tiktok_disable_duet: false,
    tiktok_disable_stitch: false,
    linkedin_type: 'text',
    linkedin_alt_text: '',
    linkedin_article_url: '',
    // Segundo del video que se usa de carátula cuando no se sube una portada
    // (TikTok solo acepta esto). El fotograma 0 suele salir negro.
    cover_timestamp_ms: 1000,
});

const form = useForm({
    title: props.post?.title || '',
    body: props.post?.body || '',
    scheduled_at: props.post?.scheduled_at ? props.post.scheduled_at.slice(0, 16) : '',
    account_ids: props.post?.targets?.map(t => t.social_account_id) || [],
    media: [],
    cover: null,
    remove_cover: false,
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

// La portada viaja dentro de `media` con role=cover, pero no es contenido:
// no cuenta para "¿hay video?" ni para "¿hay imagen?".
const mediaExistente = computed(() => (props.post?.media || []).filter(m => m.role !== 'cover'));

const hasNewMedia = computed(() => filePreviews.value.length > 0);
const hasExistingMedia = computed(() => mediaExistente.value.length > 0);
const hasAnyMedia = computed(() => hasNewMedia.value || hasExistingMedia.value);

const hasVideo = computed(() => {
    if (filePreviews.value.some(p => p.type?.startsWith('video/'))) return true;
    return mediaExistente.value.some(m => (m.mime || '').startsWith('video/'));
});
const hasImage = computed(() => {
    if (filePreviews.value.some(p => p.type?.startsWith('image/'))) return true;
    return mediaExistente.value.some(m => (m.mime || '').startsWith('image/'));
});

// ── Portada del video (reels, shorts, videos) ──────────────────────────────
const coverPreview = ref(null);
const coverConservada = ref(props.post?.cover_url || null);

function onCoverChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    form.cover = file;
    form.remove_cover = false;
    coverPreview.value = { name: file.name, url: URL.createObjectURL(file) };
}
function quitarPortada() {
    form.cover = null;
    // El backend conserva la portada anterior si no llega uno nuevo: sin esta
    // bandera, quitarla en pantalla no la quitaba del post.
    form.remove_cover = true;
    coverPreview.value = null;
    coverConservada.value = null;
}

const portadaUrl = computed(() => coverPreview.value?.url || coverConservada.value);

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
    if (selectedProviders.value.has('linkedin')
        && form.options.linkedin_type === 'article' && !form.options.linkedin_article_url?.trim()) {
        errs.push('LinkedIn Artículo requiere la URL que se va a compartir.');
    }
    if (selectedProviders.value.has('youtube') && !form.title?.trim()) {
        errs.push('YouTube requiere un título.');
    }
    return errs;
});

const canPublish = computed(() => validationErrors.value.length === 0 && !form.processing);

// ── Cuánto texto acepta cada red ───────────────────────────────────────────
// El tope real de cada plataforma, no un "2200 aprox" para todas: pasarse en
// Instagram o LinkedIn es que la API rechace el post, y en TikTok que el pie
// salga cortado a la mitad.
const limitesDeTexto = {
    instagram: 2200,
    facebook: 63206,
    linkedin: 3000,
    // Lo que el publisher manda de verdad: recorta ahí antes de llamar a la API.
    tiktok: 150,
    youtube: 5000,
};

const redesElegidas = computed(() => allProviders.filter(p => selectedProviders.value.has(p)));

const conteoPorRed = computed(() => redesElegidas.value.map(provider => {
    if (provider === 'instagram' && form.options.instagram_type === 'story') {
        return { provider, nota: 'la story no lleva texto' };
    }

    // TikTok publica el título si lo hay, y si no el contenido.
    const texto = provider === 'tiktok' ? (form.title || form.body || '') : (form.body || '');
    const limite = limitesDeTexto[provider];

    return { provider, usado: texto.length, limite, excedido: texto.length > limite };
}));

const hashtags = computed(() => ((form.body || '').match(/#[\p{L}\p{N}_]+/gu) || []).length);

// ── Vista previa ───────────────────────────────────────────────────────────
const pestanaPreview = ref(null);
const previewActivo = computed(() => redesElegidas.value.includes(pestanaPreview.value)
    ? pestanaPreview.value
    : redesElegidas.value[0]);

// La cuenta con la que se va a ver el post: la primera elegida de esa red.
const cuentaDelPreview = computed(() =>
    props.accounts.find(a => a.provider === previewActivo.value && form.account_ids.includes(a.id)) || null);

// El adjunto que se está por publicar: el archivo nuevo si lo hay, y si no el
// que ya tenía el post.
const mediaDelPreview = computed(() => {
    const nuevo = filePreviews.value[0];
    if (nuevo) return { url: nuevo.url, type: nuevo.type };
    if (props.post?.media_url) return { url: props.post.media_url, type: props.post.media_mime };
    return null;
});

// ── Portada: a qué redes les sirve ─────────────────────────────────────────
// El campo aparece en cuanto el post es de video, aunque todavía no se haya
// adjuntado el archivo: así se ve antes de armar el reel, no después.
const formatoDeVideo = computed(() =>
    selectedProviders.value.has('youtube')
    || selectedProviders.value.has('tiktok')
    || (selectedProviders.value.has('instagram') && form.options.instagram_type === 'reel')
    || (selectedProviders.value.has('facebook') && ['reel', 'video'].includes(form.options.facebook_type)));

const mostrarPortada = computed(() => hasVideo.value || formatoDeVideo.value);

// Redes que aceptan la imagen que se sube aquí.
const redesConPortada = computed(() => {
    const redes = [];
    // Un video en el feed de Instagram también se publica como reel: la API no
    // tiene otro formato de video.
    if (selectedProviders.value.has('instagram')
        && (form.options.instagram_type === 'reel'
            || (form.options.instagram_type === 'feed' && hasVideo.value))) redes.push('Instagram');
    if (selectedProviders.value.has('facebook') && ['reel', 'video'].includes(form.options.facebook_type)) redes.push('Facebook');
    if (selectedProviders.value.has('youtube')) redes.push('YouTube');
    return redes;
});

// TikTok no acepta imagen de portada, solo un segundo del video.
const tiktokSeleccionado = computed(() => selectedProviders.value.has('tiktok'));

// Sugerencias: no bloquean la publicación, solo evitan que salga un reel con
// el primer fotograma en negro de carátula.
const sugerencias = computed(() => {
    const avisos = [];
    if (redesConPortada.value.length && !portadaUrl.value) {
        avisos.push(`Sube una portada: es lo primero que se ve del video en ${redesConPortada.value.join(', ')}.`);
    }
    if (selectedProviders.value.has('instagram') && hashtags.value > 30) {
        avisos.push(`Instagram admite 30 hashtags por publicación y llevas ${hashtags.value}: los rechaza todos, no solo los de más.`);
    }
    if (selectedProviders.value.has('youtube') && portadaUrl.value) {
        avisos.push('YouTube usa la portada como miniatura (ideal 1280×720) y solo la acepta en canales verificados.');
    }
    return avisos;
});

// ── Submit ─────────────────────────────────────────────────────────────────
// ── Modo resumen ────────────────────────────────────────────────────────────
// Un post ya publicado no se puede editar: `updatePost` responde 422. Mostrar
// el formulario de siempre hacía pensar que nunca salió a las redes, cuando en
// realidad ya estaba publicado.
//
// `failed` sí conserva el formulario: ahí lo útil es corregir y reintentar.
const esResumen = computed(() => ['published', 'partial', 'publishing'].includes(props.post?.status));
const publicando = computed(() => props.post?.status === 'publishing');

const targets = computed(() => props.post?.targets || []);
const targetsFallidos = computed(() => targets.value.filter(t => t.status === 'failed'));

const targetStatusLabels = {
    pending: 'pendiente', publishing: 'publicando', published: 'publicado', failed: 'falló',
};
const targetStatusStyles = {
    pending:    'bg-gray-50 text-gray-600 border-gray-200',
    publishing: 'bg-blue-50 text-blue-700 border-blue-200',
    published:  'bg-emerald-50 text-emerald-700 border-emerald-200',
    failed:     'bg-rose-50 text-rose-700 border-rose-300',
};

function fecha(valor) {
    if (!valor) return null;
    return new Date(valor).toLocaleString('es-MX', {
        day: 'numeric', month: 'long', year: 'numeric', hour: 'numeric', minute: '2-digit',
    });
}

function reintentar() {
    router.post(route('social.posts.publishNow', [props.client.id, props.post.id]));
}
function duplicar() {
    router.post(route('social.posts.duplicate', [props.client.id, props.post.id]));
}

// Mientras se publica, el estado cambia en segundo plano: refrescamos solo
// esta pantalla para no obligar a recargar a mano.
let sondeo = null;
onMounted(() => {
    if (!publicando.value) return;
    sondeo = setInterval(() => router.reload({ only: ['post'] }), 5000);
});
onBeforeUnmount(() => sondeo && clearInterval(sondeo));

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
    <Head :title="esResumen ? 'Publicación' : (post ? 'Editar post' : 'Nuevo post')" />
    <AuthenticatedLayout>
        <div class="p-6 max-w-4xl mx-auto space-y-6">
            <div>
                <Link :href="route('social.clients.show', client.id)" class="text-xs text-primary hover:underline">← Volver</Link>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2 mt-1">
                    <MegaphoneIcon class="w-6 h-6 text-primary" />
                    {{ esResumen ? 'Publicación' : (post ? 'Editar post' : 'Nuevo post') }} — {{ client.business_name }}
                </h1>
            </div>

            <!-- ══ Modo resumen: el post ya salió (o está saliendo) ══ -->
            <div v-if="esResumen" class="space-y-6">
                <div :class="['rounded-xl border p-4 flex items-start gap-3',
                              publicando ? 'bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800'
                                         : 'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800']">
                    <ArrowPathIcon v-if="publicando" class="w-6 h-6 text-blue-600 animate-spin flex-shrink-0" />
                    <CheckCircleIcon v-else class="w-6 h-6 text-emerald-600 flex-shrink-0" />
                    <div>
                        <p :class="['font-semibold', publicando ? 'text-blue-800 dark:text-blue-200' : 'text-emerald-800 dark:text-emerald-200']">
                            <template v-if="publicando">Publicación en proceso…</template>
                            <template v-else-if="post.status === 'partial'">Publicado con errores en algunas redes</template>
                            <template v-else>Publicado</template>
                        </p>
                        <p class="text-sm mt-0.5" :class="publicando ? 'text-blue-700 dark:text-blue-300' : 'text-emerald-700 dark:text-emerald-300'">
                            <template v-if="publicando">Esta pantalla se actualiza sola.</template>
                            <template v-else-if="fecha(post.published_at)">{{ fecha(post.published_at) }}</template>
                            <template v-else>Ya está en las redes seleccionadas.</template>
                        </p>
                    </div>
                </div>

                <!-- Estado por red, con el enlace a la publicación real -->
                <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 divide-y divide-gray-100 dark:divide-zinc-800">
                    <div v-for="t in targets" :key="t.id" class="p-4 flex items-start gap-3">
                        <span :class="['w-2.5 h-2.5 rounded-full mt-1.5 flex-shrink-0', providerColors[t.provider]]"></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-gray-800 dark:text-gray-100">
                                {{ providerLabels[t.provider] || t.provider }}
                                <span v-if="t.account" class="text-gray-500 font-normal">· {{ t.account.name }}</span>
                            </p>
                            <p v-if="t.error_message" class="mt-1 text-xs text-rose-700 dark:text-rose-400 break-words">
                                {{ t.error_message }}
                            </p>
                            <p v-else-if="t.published_at" class="mt-0.5 text-xs text-gray-500">{{ fecha(t.published_at) }}</p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span :class="['text-[11px] px-2 py-0.5 rounded-full border font-medium', targetStatusStyles[t.status]]">
                                {{ targetStatusLabels[t.status] || t.status }}
                            </span>
                            <a v-if="t.platform_url" :href="t.platform_url" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1 text-xs text-primary hover:underline">
                                Ver publicación <ArrowTopRightOnSquareIcon class="w-3.5 h-3.5" />
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Contenido tal como se publicó -->
                <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-6 space-y-4">
                    <div v-if="post.title">
                        <p class="text-xs uppercase tracking-wide text-gray-400 mb-1">Título</p>
                        <p class="text-gray-800 dark:text-gray-100 font-medium">{{ post.title }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-400 mb-1">Contenido</p>
                        <p class="text-gray-800 dark:text-gray-100 whitespace-pre-wrap">{{ post.body || '—' }}</p>
                    </div>
                    <div v-if="post.cover_url">
                        <p class="text-xs uppercase tracking-wide text-gray-400 mb-2">Portada</p>
                        <img :src="post.cover_url" alt="Portada"
                            class="w-24 h-40 object-cover rounded-md border border-gray-200 dark:border-zinc-700" />
                    </div>
                    <div v-if="mediaExistente.length">
                        <p class="text-xs uppercase tracking-wide text-gray-400 mb-2">Media</p>
                        <ul class="flex flex-wrap gap-2">
                            <li v-for="(m, i) in mediaExistente" :key="i"
                                class="text-xs px-2 py-1 rounded-md bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-zinc-300">
                                {{ m.name || m.path }}
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <button v-if="targetsFallidos.length" type="button" @click="reintentar"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium">
                        <ArrowPathIcon class="w-4 h-4" />
                        Reintentar {{ targetsFallidos.length }} que fallaron
                    </button>
                    <button type="button" @click="duplicar"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md border border-gray-300 dark:border-zinc-600 text-sm text-gray-700 dark:text-zinc-200 hover:bg-gray-50 dark:hover:bg-zinc-800">
                        <DocumentDuplicateIcon class="w-4 h-4" />
                        Duplicar como borrador
                    </button>
                </div>
            </div>

            <form v-else @submit.prevent="submit('save_draft')" class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-6 space-y-5">

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
                                    <SocialAvatar :src="a.avatar_url" :name="a.name" :provider="provider"
                                        size="w-6 h-6" text="text-[10px]" />
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
                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 text-sm" />
                    <p v-if="selectedProviders.has('youtube')" class="text-xs mt-1"
                        :class="(form.title || '').length > 100 ? 'text-rose-600 font-medium' : 'text-gray-400'">
                        Título del video en YouTube: {{ (form.title || '').length }} / 100
                        <template v-if="(form.title || '').length > 100"> — se recorta al publicar.</template>
                    </p>
                </div>

                <!-- ── Body ─────────────────────────────────────────────── -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-200 mb-1">Contenido</label>
                    <textarea v-model="form.body" rows="6"
                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 text-sm"></textarea>
                    <div v-if="conteoPorRed.length" class="flex flex-wrap gap-1.5 mt-2">
                        <span v-for="c in conteoPorRed" :key="c.provider"
                            :class="['inline-flex items-center gap-1.5 text-[11px] px-2 py-0.5 rounded-full border',
                                     c.excedido
                                       ? 'bg-rose-50 text-rose-700 border-rose-300 dark:bg-rose-900/20 dark:border-rose-800'
                                       : 'bg-gray-50 text-gray-600 border-gray-200 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700']">
                            <span :class="['w-1.5 h-1.5 rounded-full', providerColors[c.provider]]"></span>
                            {{ providerLabels[c.provider] }}
                            <template v-if="c.nota">· {{ c.nota }}</template>
                            <template v-else>{{ c.usado }} / {{ c.limite.toLocaleString('es-MX') }}</template>
                        </span>
                    </div>
                    <p v-else class="text-xs text-gray-400 mt-1">
                        {{ (form.body || '').length }} caracteres. Elige las cuentas para ver el tope de cada red.
                    </p>
                    <p v-if="conteoPorRed.some(c => c.excedido)" class="text-xs text-rose-600 mt-1.5">
                        El texto se pasa del tope de alguna red: ahí se recorta o la publicación se rechaza.
                    </p>
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
                        Hay {{ mediaExistente.length }} archivo(s) adjuntos previamente. Sube archivos nuevos para reemplazarlos.
                    </p>
                </div>

                <!-- ── Portada del video (reel / short / video) ─────────── -->
                <div v-if="mostrarPortada"
                    class="rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-zinc-200">
                                Portada del video
                                <span class="text-gray-400 text-xs font-normal">(recomendada)</span>
                            </label>
                            <p class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">
                                La imagen con la que se ve el video en el feed y en la cuadrícula del perfil.
                                Vertical 1080×1920 (9:16) para reels.
                            </p>
                        </div>
                        <span v-if="redesConPortada.length"
                            class="text-[11px] px-2 py-0.5 rounded-full bg-primary/10 text-primary font-medium flex-shrink-0">
                            {{ redesConPortada.join(' · ') }}
                        </span>
                    </div>

                    <div class="flex flex-wrap items-start gap-4">
                        <div v-if="portadaUrl" class="relative">
                            <img :src="portadaUrl" alt="Portada"
                                class="w-24 h-40 object-cover rounded-md border border-gray-200 dark:border-zinc-700" />
                            <button type="button" @click="quitarPortada"
                                class="absolute top-1 right-1 bg-white/90 rounded-full p-0.5">
                                <XMarkIcon class="w-3 h-3" />
                            </button>
                        </div>
                        <label v-else
                            class="flex flex-col items-center justify-center w-24 h-40 border-2 border-dashed border-gray-300 dark:border-zinc-700 rounded-md cursor-pointer hover:bg-gray-50 dark:hover:bg-zinc-800/40 text-gray-500">
                            <input type="file" class="hidden" accept="image/*" @change="onCoverChange" />
                            <PhotoIcon class="w-7 h-7" />
                            <span class="text-[11px] mt-1">Subir portada</span>
                        </label>

                        <div class="flex-1 min-w-[12rem] space-y-2">
                            <label class="block">
                                <span class="block text-xs text-gray-600 dark:text-zinc-300 mb-1">
                                    Segundo del video para la carátula
                                </span>
                                <div class="flex items-center gap-2">
                                    <input type="number" min="0" step="0.5"
                                        :value="(form.options.cover_timestamp_ms || 0) / 1000"
                                        @input="form.options.cover_timestamp_ms = Math.round(($event.target.value || 0) * 1000)"
                                        class="w-24 dark:[color-scheme:dark] rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 text-sm" />
                                    <span class="text-xs text-gray-500">segundos</span>
                                </div>
                            </label>
                            <p class="text-xs text-gray-400">
                                Se usa cuando no hay imagen de portada.
                                <template v-if="tiktokSeleccionado">
                                    TikTok solo acepta este segundo, nunca una imagen.
                                </template>
                            </p>
                            <p v-if="form.errors.cover" class="text-xs text-rose-600">{{ form.errors.cover }}</p>
                        </div>
                    </div>
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
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 text-sm">
                                        <option value="video">Video normal (horizontal)</option>
                                        <option value="short">Short (vertical, &lt; 60s)</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="block text-xs text-gray-600 dark:text-zinc-300 mb-1">Privacidad</span>
                                    <select v-model="form.options.youtube_privacy"
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 text-sm">
                                        <option value="public">Público</option>
                                        <option value="unlisted">No listado</option>
                                        <option value="private">Privado</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="block text-xs text-gray-600 dark:text-zinc-300 mb-1">Categoría</span>
                                    <select v-model="form.options.youtube_category_id"
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 text-sm">
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
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 text-sm">
                                        <option value="post">Post de texto</option>
                                        <option value="photo">Foto</option>
                                        <option value="reel">Reel</option>
                                        <option value="video">Video normal</option>
                                    </select>
                                </label>
                            </div>
                            <p class="text-xs text-gray-400">
                                Se publica en la página, y lo de una página siempre es público:
                                Facebook no admite restringir la audiencia por publicación.
                            </p>
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
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 text-sm">
                                        <option value="feed">Feed post</option>
                                        <option value="reel">Reel</option>
                                        <option value="story">Story</option>
                                    </select>
                                </label>
                            </div>
                            <p v-if="form.options.instagram_type === 'story'"
                                class="text-xs text-amber-600 flex items-center gap-1">
                                <ExclamationTriangleIcon class="w-3 h-3" />
                                Story: solo imagen o video corto (&lt; 15s). Desaparece a las 24 horas y no lleva texto.
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
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 text-sm">
                                        <option value="video">Video normal</option>
                                        <option value="draft">Enviar a borradores</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="block text-xs text-gray-600 dark:text-zinc-300 mb-1">Privacidad</span>
                                    <select v-model="form.options.tiktok_privacy"
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 text-sm">
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
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 text-sm">
                                        <option value="text">Post de texto</option>
                                        <option value="image">Post con imagen</option>
                                        <option value="article">Artículo</option>
                                    </select>
                                </label>
                                <label v-if="form.options.linkedin_type === 'image'" class="block">
                                    <span class="block text-xs text-gray-600 dark:text-zinc-300 mb-1">Texto alternativo (opcional)</span>
                                    <input v-model="form.options.linkedin_alt_text" type="text" maxlength="200"
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 text-sm" />
                                </label>
                                <label v-if="form.options.linkedin_type === 'article'" class="block">
                                    <span class="block text-xs text-gray-600 dark:text-zinc-300 mb-1">
                                        URL del artículo <span class="text-rose-600">*</span>
                                    </span>
                                    <input v-model="form.options.linkedin_article_url" type="url" maxlength="2048"
                                        placeholder="https://..."
                                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 text-sm" />
                                </label>
                            </div>
                        </fieldset>
                    </div>
                </div>

                <!-- ── Vista previa por red ────────────────────────────── -->
                <div v-if="redesElegidas.length"
                    class="border border-gray-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                    <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-100 dark:border-zinc-800">
                        <EyeIcon class="w-4 h-4 text-gray-400" />
                        <span class="text-sm font-medium text-gray-700 dark:text-zinc-200">Vista previa</span>
                        <span class="text-xs text-gray-400">aproximada</span>
                    </div>

                    <div v-if="redesElegidas.length > 1" class="flex flex-wrap gap-1.5 px-4 pt-3">
                        <button v-for="p in redesElegidas" :key="p" type="button"
                            @click="pestanaPreview = p"
                            :class="['inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full border transition',
                                     previewActivo === p
                                       ? 'bg-primary/10 text-primary border-primary'
                                       : 'bg-white dark:bg-zinc-800 text-gray-600 dark:text-zinc-300 border-gray-300 dark:border-zinc-600 hover:border-gray-400']">
                            <span :class="['w-1.5 h-1.5 rounded-full', providerColors[p]]"></span>
                            {{ providerLabels[p] }}
                        </button>
                    </div>

                    <div class="p-4 bg-gray-50 dark:bg-zinc-950/40">
                        <PostPreview :provider="previewActivo" :account="cuentaDelPreview"
                            :title="form.title" :body="form.body"
                            :media="mediaDelPreview" :cover-url="portadaUrl"
                            :options="form.options" />
                    </div>
                </div>

                <!-- ── Schedule ─────────────────────────────────────────── -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-200 mb-1">Programar (opcional)</label>
                    <input v-model="form.scheduled_at" type="datetime-local"
                        class="dark:[color-scheme:dark] rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 text-sm" />
                </div>

                <!-- ── Sugerencias (no bloquean) ───────────────────────── -->
                <div v-if="sugerencias.length"
                    class="rounded-md border border-sky-200 bg-sky-50 dark:bg-sky-900/20 dark:border-sky-800 px-3 py-2">
                    <ul class="text-xs text-sky-800 dark:text-sky-200 space-y-0.5 list-disc list-inside">
                        <li v-for="(aviso, i) in sugerencias" :key="i">{{ aviso }}</li>
                    </ul>
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
