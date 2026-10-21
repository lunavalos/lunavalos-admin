<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';

/**
 * Muestra el HTML de una firma dentro de un iframe aislado.
 *
 * El HTML de las plantillas lo escribe quien tenga permiso de editarlas; con
 * `v-html` cualquier `<img onerror=…>` correría en nuestro dominio con la
 * sesión de quien lo vea. `sandbox` sin `allow-scripts` bloquea todo JS (y los
 * links abren fuera). `allow-same-origin` solo sirve para medir la altura
 * desde aquí: sin scripts dentro, no da acceso a nada.
 */
const props = defineProps({
    html: { type: String, default: '' },
    minHeight: { type: Number, default: 80 },
});

const frame = ref(null);
const height = ref(props.minHeight);
let observer = null;

const doc = () => `<!DOCTYPE html><html><head><meta charset="utf-8"><base target="_blank">
<style>html,body{margin:0;padding:0;background:#fff;}body{display:inline-block;min-width:100%;}</style>
</head><body>${props.html || ''}</body></html>`;

const measure = () => {
    const body = frame.value?.contentDocument?.body;
    if (body) height.value = Math.max(props.minHeight, body.scrollHeight);
};

const onLoad = () => {
    measure();
    observer?.disconnect();
    const body = frame.value?.contentDocument?.body;
    if (body && window.ResizeObserver) {
        observer = new ResizeObserver(measure);
        observer.observe(body);
    }
    // Las imágenes cargan después del documento.
    frame.value?.contentDocument?.querySelectorAll('img').forEach(img => img.addEventListener('load', measure));
};

const srcdoc = ref(doc());
watch(() => props.html, () => { srcdoc.value = doc(); });

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <iframe
        ref="frame"
        :srcdoc="srcdoc"
        sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
        referrerpolicy="no-referrer"
        title="Vista previa de la firma"
        class="w-full border-0 block bg-white"
        :style="{ height: `${height}px` }"
        @load="onLoad"
    ></iframe>
</template>
