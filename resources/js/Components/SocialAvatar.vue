<script setup>
import { computed, ref, watch } from 'vue';

/**
 * Avatar de una cuenta social, con respaldo cuando la imagen no carga.
 *
 * Meta firma las URLs de foto de perfil (`profile_picture_url` de Instagram,
 * la de las páginas de Facebook) y caducan a las pocas horas: la guardamos al
 * conectar la cuenta y días después devuelve 403. Sin este respaldo el
 * navegador pintaba el ícono de imagen rota en "Cuentas conectadas".
 */
const props = defineProps({
    src: { type: String, default: null },
    name: { type: String, default: '' },
    provider: { type: String, default: null },
    size: { type: String, default: 'w-9 h-9' },
    text: { type: String, default: 'text-xs' },
    extra: { type: String, default: '' },
});

const roto = ref(false);

// Si cambia la cuenta hay que volver a intentarlo: el componente se reutiliza
// entre elementos de una lista.
watch(() => props.src, () => { roto.value = false; });

const colores = {
    facebook: 'bg-blue-600',
    instagram: 'bg-gradient-to-tr from-fuchsia-500 to-amber-500',
    linkedin: 'bg-sky-700',
    tiktok: 'bg-zinc-900',
    youtube: 'bg-red-600',
};

const inicial = computed(() => (props.name || props.provider || '?').charAt(0).toUpperCase());
</script>

<template>
    <img v-if="src && !roto" :src="src" :alt="name" @error="roto = true"
        :class="[size, extra, 'rounded-full object-cover bg-gray-200 dark:bg-zinc-700 flex-shrink-0']" />
    <span v-else
        :class="[size, text, extra, colores[provider] || 'bg-gray-400 dark:bg-zinc-600',
                 'rounded-full flex items-center justify-center text-white font-bold flex-shrink-0']">
        {{ inicial }}
    </span>
</template>
