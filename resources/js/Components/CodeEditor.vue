<script setup>
import { onMounted, ref, watch, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue: String,
    placeholder: String,
    language: {
        type: String,
        default: 'html'
    },
    theme: {
        type: String,
        default: 'monokai'
    },
    rows: {
        type: Number,
        default: 15
    }
});

const emit = defineEmits(['update:modelValue']);

const editorContainer = ref(null);
let editor = null;

onMounted(() => {
    if (typeof ace === 'undefined') {
        const script = document.createElement('script');
        script.src = "https://cdnjs.cloudflare.com/ajax/libs/ace/1.37.5/ace.min.js";
        script.onload = initAce;
        document.head.appendChild(script);
    } else {
        initAce();
    }
});

function initAce() {
    if (!editorContainer.value) return;

    try {
        ace.config.set("basePath", "https://cdnjs.cloudflare.com/ajax/libs/ace/1.37.5/");
        
        editor = ace.edit(editorContainer.value);
        editor.setTheme(`ace/theme/${props.theme}`);
        editor.session.setMode(`ace/mode/${props.language}`);
        
        // Configuraciones de visualización
        editor.setOptions({
            fontSize: "13px",
            fontFamily: "'JetBrains Mono', 'Fira Code', 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace",
            showPrintMargin: false,
            showGutter: true,
            highlightActiveLine: true,
            wrap: true,
            useWorker: false,
            tabSize: 4,
            showLineNumbers: true,
            cursorStyle: "smooth",
            behavioursEnabled: true,
            fadeFoldWidgets: true,
            selectionStyle: "text"
        });

        // Valor inicial
        if (props.modelValue) {
            editor.setValue(props.modelValue, -1);
        }

        // Emitir cambios
        editor.on('change', () => {
            emit('update:modelValue', editor.getValue());
        });

        // Ajustar altura dinámica basada en el contenido inicial o filas
        editorContainer.value.style.minHeight = `${props.rows * 1.5}rem`;
        
    } catch (e) {
        console.error('Ace Editor fail:', e);
    }
}

watch(() => props.modelValue, (newVal) => {
    if (editor && newVal !== editor.getValue()) {
        const cursor = editor.getCursorPosition();
        editor.setValue(newVal || '', -1);
        editor.moveCursorToPosition(cursor);
    }
});

onBeforeUnmount(() => {
    if (editor) {
        editor.destroy();
        editor.container.remove();
    }
});
</script>

<template>
    <div class="code-editor-wrapper bg-[#2d2d2d] rounded-xl overflow-hidden border border-gray-300 focus-within:border-[#264ab3] transition-all shadow-lg shadow-black/10">
        <div ref="editorContainer" class="w-full"></div>
    </div>
</template>

<style scoped>
.code-editor-wrapper {
    margin-top: 0.25rem;
}
/* Asegurar que el editor tome el espacio necesario */
div[ref="editorContainer"] {
    min-height: 300px;
}
</style>
