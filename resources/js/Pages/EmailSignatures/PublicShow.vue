<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import SignaturePreviewFrame from '@/Components/SignaturePreviewFrame.vue';
import { ClipboardIcon, CheckIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    name:    String,
    company: String,
    html:    String,
});

const wrapped = () => `<div style="text-align:left;">${props.html}</div>`;

const copied = ref(false);
const copySignature = async () => {
    const html = wrapped();
    const text = html.replace(/<[^>]+>/g, '');

    try {
        if (navigator.clipboard && window.ClipboardItem) {
            await navigator.clipboard.write([new ClipboardItem({
                'text/html':  new Blob([html], { type: 'text/html' }),
                'text/plain': new Blob([text], { type: 'text/plain' }),
            })]);
        } else {
            const listener = (e) => {
                e.preventDefault();
                e.clipboardData.setData('text/html', html);
                e.clipboardData.setData('text/plain', text);
            };
            document.addEventListener('copy', listener);
            document.execCommand('copy');
            document.removeEventListener('copy', listener);
        }
        copied.value = true;
        setTimeout(() => copied.value = false, 2500);
    } catch (err) {
        console.error('Cant copy', err);
    }
};

// Outlook exige un documento completo con charset declarado; si no, rompe los acentos.
const downloadOutlookFile = () => {
    const doc = `<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>${(props.name || 'Firma').replace(/[<>&]/g, '')}</title>
</head>
<body style="margin:0;padding:0;">
${wrapped()}
</body>
</html>`;

    const blob = new Blob(['﻿', doc], { type: 'text/html;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const safeName = (props.name || 'firma').trim().replace(/[^a-z0-9]+/gi, '-').toLowerCase();

    link.href = url;
    link.download = `${safeName || 'firma'}.htm`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
};

const tab = ref('outlook-web');
const TABS = [
    { key: 'outlook-web', label: 'Outlook 365 / Web' },
    { key: 'outlook-desktop', label: 'Outlook escritorio' },
    { key: 'gmail', label: 'Gmail' },
    { key: 'apple', label: 'Apple Mail' },
];
</script>

<template>
    <Head :title="`Firma de ${name}`">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div class="min-h-screen bg-gray-50 py-10 px-4">
        <div class="max-w-3xl mx-auto space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Tu firma de correo</h1>
                <p class="text-gray-600">{{ name }}<span v-if="company"> · {{ company }}</span></p>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <SignaturePreviewFrame :html="html" />
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <button @click="copySignature" class="flex-1 bg-[#264ab3] text-white px-4 py-3 rounded-md font-bold flex items-center justify-center gap-2">
                    <CheckIcon v-if="copied" class="h-5 w-5" />
                    <ClipboardIcon v-else class="h-5 w-5" />
                    {{ copied ? '¡Firma copiada!' : 'Copiar firma' }}
                </button>
                <button @click="downloadOutlookFile" class="flex-1 border border-gray-300 bg-white text-gray-700 px-4 py-3 rounded-md font-bold flex items-center justify-center gap-2">
                    <ArrowDownTrayIcon class="h-5 w-5" />
                    Descargar para Outlook escritorio (.htm)
                </button>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="flex flex-wrap border-b border-gray-200">
                    <button v-for="t in TABS" :key="t.key" @click="tab = t.key"
                        :class="tab === t.key ? 'border-[#264ab3] text-[#264ab3]' : 'border-transparent text-gray-500'"
                        class="px-4 py-3 text-sm font-semibold border-b-2">
                        {{ t.label }}
                    </button>
                </div>
                <div class="p-6 text-sm text-gray-700 space-y-2">
                    <ol v-if="tab === 'outlook-web'" class="list-decimal ml-5 space-y-1">
                        <li>Da clic en <b>Copiar firma</b>.</li>
                        <li>En Outlook abre <b>Configuración (⚙) → Cuentas → Firmas</b>.</li>
                        <li>Crea una firma nueva y pega con <b>Ctrl+V</b> (o <b>⌘+V</b>).</li>
                        <li>Elígela como predeterminada para mensajes nuevos y respuestas, y guarda.</li>
                    </ol>
                    <ol v-else-if="tab === 'outlook-desktop'" class="list-decimal ml-5 space-y-1">
                        <li>Descarga el archivo <b>.htm</b>.</li>
                        <li>Presiona <b>Win+R</b>, escribe <code>%appdata%\Microsoft\Signatures</code> y pega ahí el archivo.</li>
                        <li>En Outlook ve a <b>Archivo → Opciones → Correo → Firmas</b> y selecciónala como predeterminada.</li>
                        <li>Si prefieres, también puedes usar <b>Copiar firma</b> y pegarla en el editor de firmas.</li>
                    </ol>
                    <ol v-else-if="tab === 'gmail'" class="list-decimal ml-5 space-y-1">
                        <li>Da clic en <b>Copiar firma</b>.</li>
                        <li>En Gmail abre <b>⚙ → Ver toda la configuración → General → Firma</b>.</li>
                        <li>Crea una firma nueva y pega con <b>Ctrl+V</b>.</li>
                        <li>Selecciónala en "Valores predeterminados de firma" y da clic en <b>Guardar cambios</b> al final de la página.</li>
                    </ol>
                    <ol v-else class="list-decimal ml-5 space-y-1">
                        <li>Da clic en <b>Copiar firma</b>.</li>
                        <li>En Mail abre <b>Ajustes → Firmas</b> y crea una nueva.</li>
                        <li>Desmarca "Usar siempre mi tipo de letra predeterminado" y pega con <b>⌘+V</b>.</li>
                    </ol>
                    <p class="text-xs text-gray-500 pt-2">
                        El logo y las imágenes de tu firma se actualizan solos. Si te piden actualizar tus datos, vuelve a este link y copia la firma otra vez.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
