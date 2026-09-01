<script setup>
import { computed } from 'vue';
import { PhotoIcon, GlobeAmericasIcon, HeartIcon, ChatBubbleOvalLeftIcon,
         PaperAirplaneIcon, BookmarkIcon, PlayCircleIcon } from '@heroicons/vue/24/outline';
import SocialAvatar from '@/Components/SocialAvatar.vue';

const props = defineProps({
    provider: { type: String, required: true },
    account: { type: Object, default: null },
    title: { type: String, default: '' },
    body: { type: String, default: '' },
    media: { type: Object, default: null },   // { url, type }
    coverUrl: { type: String, default: null },
    options: { type: Object, default: () => ({}) },
});

// Caracteres que cada red muestra antes del "ver más". Son aproximados —
// dependen del ancho de pantalla—, pero sirven para ver dónde queda cortado
// el texto que importa.
const cortes = { instagram: 125, facebook: 250, linkedin: 210, tiktok: 100, youtube: 157 };

const nombre = computed(() => props.account?.name || 'Cuenta sin seleccionar');
const avatar = computed(() => props.account?.avatar_url || null);
const formato = computed(() => ({
    instagram: props.options.instagram_type || 'feed',
    facebook:  props.options.facebook_type || 'post',
    youtube:   props.options.youtube_type || 'video',
    linkedin:  props.options.linkedin_type || 'text',
    tiktok:    'video',
}[props.provider]));

const esVideo = computed(() => (props.media?.type || '').startsWith('video/'));

// Vertical de pantalla completa: reels, stories, shorts y TikTok.
const inmersivo = computed(() =>
    props.provider === 'tiktok'
    || ['reel', 'story'].includes(formato.value)
    || (props.provider === 'youtube' && formato.value === 'short'));

const proporcion = computed(() => {
    if (inmersivo.value) return 'aspect-[9/16]';
    if (props.provider === 'youtube') return 'aspect-video';
    if (props.provider === 'facebook' && formato.value === 'video') return 'aspect-video';
    return 'aspect-square';
});

// El texto que de verdad llega a la red.
const texto = computed(() => {
    // La story no lleva pie: Instagram lo ignora.
    if (props.provider === 'instagram' && formato.value === 'story') return '';
    if (props.provider === 'tiktok') return props.title || props.body || '';
    return props.body || '';
});

const recorte = computed(() => {
    const limite = cortes[props.provider] || 200;
    const t = texto.value;
    return t.length > limite
        ? { visible: t.slice(0, limite).trimEnd(), hayMas: true }
        : { visible: t, hayMas: false };
});

// Un post de solo texto no necesita marco de media; el resto sí, aunque
// todavía no se haya adjuntado el archivo.
const llevaMedia = computed(() => {
    if (props.provider === 'facebook') return formato.value !== 'post';
    if (props.provider === 'linkedin') return formato.value === 'image';
    return true;
});

const esArticulo = computed(() => props.provider === 'linkedin' && formato.value === 'article');

const dominio = computed(() => {
    try {
        return new URL(props.options.linkedin_article_url).hostname.replace(/^www\./, '');
    } catch {
        return null;
    }
});
</script>

<template>
    <div class="mx-auto w-full max-w-[19rem]">
        <!-- ══ Vertical inmersivo: reel, story, short, TikTok ══ -->
        <div v-if="inmersivo"
            class="relative rounded-xl overflow-hidden bg-zinc-900 shadow-sm" :class="proporcion">
            <video v-if="media && esVideo" :src="media.url" :poster="coverUrl || undefined"
                class="absolute inset-0 w-full h-full object-cover" muted playsinline />
            <img v-else-if="media" :src="media.url" class="absolute inset-0 w-full h-full object-cover" alt="" />
            <img v-else-if="coverUrl" :src="coverUrl" class="absolute inset-0 w-full h-full object-cover" alt="" />
            <div v-else class="absolute inset-0 flex flex-col items-center justify-center text-zinc-500 gap-1">
                <PhotoIcon class="w-8 h-8" />
                <span class="text-[11px]">Sin archivo adjunto</span>
            </div>

            <!-- Barra de progreso de la story -->
            <div v-if="formato === 'story'" class="absolute top-2 inset-x-2 h-0.5 rounded-full bg-white/70"></div>

            <!-- Cabecera de la story: la cuenta va arriba, no abajo -->
            <div v-if="formato === 'story'" class="absolute top-5 left-3 right-3 flex items-center gap-2">
                <SocialAvatar :src="avatar" :name="nombre" :provider="provider"
                    size="w-6 h-6" text="text-[10px]" extra="ring-2 ring-white/80" />
                <span class="text-white text-xs font-medium drop-shadow">{{ nombre }}</span>
                <span class="text-white/70 text-[11px] drop-shadow">ahora</span>
            </div>

            <!-- Pie del reel / TikTok -->
            <div v-else class="absolute inset-x-0 bottom-0 p-3 pr-12 bg-gradient-to-t from-black/80 to-transparent">
                <div class="flex items-center gap-2 mb-1.5">
                    <SocialAvatar :src="avatar" :name="nombre" :provider="provider"
                        size="w-6 h-6" text="text-[10px]" extra="ring-1 ring-white/60" />
                    <span class="text-white text-xs font-semibold drop-shadow">{{ nombre }}</span>
                </div>
                <p v-if="recorte.visible" class="text-white text-[11px] leading-snug drop-shadow whitespace-pre-wrap">
                    {{ recorte.visible }}<span v-if="recorte.hayMas" class="text-white/60"> … más</span>
                </p>
            </div>

            <!-- Riel de acciones -->
            <div v-if="formato !== 'story'" class="absolute right-2 bottom-4 flex flex-col items-center gap-3 text-white/90">
                <HeartIcon class="w-5 h-5 drop-shadow" />
                <ChatBubbleOvalLeftIcon class="w-5 h-5 drop-shadow" />
                <PaperAirplaneIcon class="w-5 h-5 drop-shadow" />
            </div>
        </div>

        <!-- ══ YouTube: miniatura + título ══ -->
        <div v-else-if="provider === 'youtube'" class="space-y-2">
            <div class="relative rounded-lg overflow-hidden bg-zinc-900 aspect-video">
                <img v-if="coverUrl" :src="coverUrl" class="absolute inset-0 w-full h-full object-cover" alt="" />
                <video v-else-if="media && esVideo" :src="media.url"
                    class="absolute inset-0 w-full h-full object-cover" muted playsinline />
                <div v-else class="absolute inset-0 flex flex-col items-center justify-center text-zinc-500 gap-1">
                    <PlayCircleIcon class="w-8 h-8" />
                    <span class="text-[11px]">Sin video</span>
                </div>
            </div>
            <div class="flex gap-2">
                <SocialAvatar :src="avatar" :name="nombre" provider="youtube" size="w-8 h-8" />
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 line-clamp-2">
                        {{ title || 'Sin título' }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">{{ nombre }} · Justo ahora</p>
                </div>
            </div>
        </div>

        <!-- ══ Instagram feed ══ -->
        <div v-else-if="provider === 'instagram'"
            class="rounded-xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
            <div class="flex items-center gap-2 p-3">
                <SocialAvatar :src="avatar" :name="nombre" provider="instagram"
                    size="w-7 h-7" text="text-[11px]" />
                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ nombre }}</span>
            </div>
            <div class="relative bg-zinc-100 dark:bg-zinc-800 aspect-square">
                <video v-if="media && esVideo" :src="media.url" :poster="coverUrl || undefined"
                    class="absolute inset-0 w-full h-full object-cover" muted playsinline />
                <img v-else-if="media" :src="media.url" class="absolute inset-0 w-full h-full object-cover" alt="" />
                <div v-else class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 gap-1">
                    <PhotoIcon class="w-8 h-8" />
                    <span class="text-[11px]">Sin archivo adjunto</span>
                </div>
            </div>
            <div class="flex items-center gap-3 px-3 pt-2.5 text-gray-800 dark:text-zinc-200">
                <HeartIcon class="w-5 h-5" />
                <ChatBubbleOvalLeftIcon class="w-5 h-5" />
                <PaperAirplaneIcon class="w-5 h-5" />
                <BookmarkIcon class="w-5 h-5 ml-auto" />
            </div>
            <p class="px-3 py-2 text-xs text-gray-800 dark:text-zinc-200 whitespace-pre-wrap break-words">
                <span class="font-semibold">{{ nombre }}</span>
                <template v-if="recorte.visible">{{ ' ' + recorte.visible }}</template>
                <span v-if="recorte.hayMas" class="text-gray-400"> … más</span>
            </p>
        </div>

        <!-- ══ Facebook / LinkedIn: tarjeta de feed ══ -->
        <div v-else
            class="rounded-xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
            <div class="flex items-center gap-2 p-3">
                <SocialAvatar :src="avatar" :name="nombre" :provider="provider" />
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{{ nombre }}</p>
                    <p class="text-[11px] text-gray-500 dark:text-zinc-400 flex items-center gap-1">
                        Ahora · <GlobeAmericasIcon class="w-3 h-3" />
                    </p>
                </div>
            </div>

            <p v-if="recorte.visible"
                class="px-3 pb-2 text-xs text-gray-800 dark:text-zinc-200 whitespace-pre-wrap break-words">
                {{ recorte.visible }}<span v-if="recorte.hayMas" class="text-gray-400"> … ver más</span>
            </p>

            <!-- Tarjeta del artículo de LinkedIn -->
            <div v-if="esArticulo" class="border-t border-gray-200 dark:border-zinc-700">
                <div class="aspect-[1.91/1] bg-gray-100 dark:bg-zinc-800 flex items-center justify-center text-gray-400">
                    <GlobeAmericasIcon class="w-8 h-8" />
                </div>
                <div class="p-3 bg-gray-50 dark:bg-zinc-800/60">
                    <p class="text-xs font-semibold text-gray-900 dark:text-gray-100 line-clamp-2">
                        {{ title || 'Sin título' }}
                    </p>
                    <p class="text-[11px] text-gray-500 mt-0.5">{{ dominio || 'Falta la URL del artículo' }}</p>
                </div>
            </div>

            <div v-else-if="llevaMedia" class="relative bg-zinc-100 dark:bg-zinc-800" :class="proporcion">
                <video v-if="media && esVideo" :src="media.url" :poster="coverUrl || undefined"
                    class="absolute inset-0 w-full h-full object-cover" muted playsinline />
                <img v-else-if="media" :src="media.url" class="absolute inset-0 w-full h-full object-cover" alt="" />
                <div v-else class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 gap-1">
                    <PhotoIcon class="w-8 h-8" />
                    <span class="text-[11px]">Sin archivo adjunto</span>
                </div>
            </div>
        </div>
    </div>
</template>
