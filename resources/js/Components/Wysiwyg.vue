<script setup>
import { onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: String,
    placeholder: String,
});

const emit = defineEmits(['update:modelValue']);

const editor = ref(null);
const quill = ref(null);

onMounted(() => {
    if (typeof Quill === 'undefined') {
        const script = document.createElement('script');
        script.src = "https://cdn.quilljs.com/1.3.6/quill.js";
        script.onload = initQuill;
        document.head.appendChild(script);

        const link = document.createElement('link');
        link.rel = "stylesheet";
        link.href = "https://cdn.quilljs.com/1.3.6/quill.snow.css";
        document.head.appendChild(link);
    } else {
        initQuill();
    }
});

function initQuill() {
    if (!editor.value) return;
    
    try {
        quill.value = new Quill(editor.value, {
            theme: 'snow',
            placeholder: props.placeholder || 'Escribe aquí...',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link', 'clean']
                ]
            }
        });

        if (props.modelValue) {
            quill.value.root.innerHTML = props.modelValue;
        }

        quill.value.on('text-change', () => {
            emit('update:modelValue', quill.value.root.innerHTML);
        });
    } catch (e) {
        console.error('Quill fail:', e);
    }
}

watch(() => props.modelValue, (newVal) => {
    if (quill.value && newVal !== quill.value.root.innerHTML) {
        quill.value.root.innerHTML = newVal || '';
    }
});

</script>

<template>
    <div class="wysiwyg-container bg-white rounded-xl overflow-hidden border border-gray-300 focus-within:border-[#264ab3] transition-all">
        <div ref="editor" class="min-h-[150px] text-gray-700"></div>
    </div>
</template>

<style>
.ql-toolbar.ql-snow {
    border: none !important;
    border-bottom: 1px solid #f3f4f6 !important;
    background-color: #f9fafb !important;
    padding: 8px !important;
}
.ql-container.ql-snow {
    border: none !important;
    font-family: inherit !important;
    font-size: 0.875rem !important;
}
.ql-editor {
    min-height: 150px !important;
    padding: 12px !important;
}
.ql-editor.ql-blank::before {
    color: #9ca3af !important;
    font-style: normal !important;
}
</style>
