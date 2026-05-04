<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { EnvelopeIcon, ServerIcon, ShieldCheckIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';
import { CheckCircleIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    mail_config: Object,
});

const copyToClipboard = (text) => {
    if (!text) return;
    navigator.clipboard.writeText(text);
};
</script>

<template>
    <Head title="Configuración de Correo" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">
                Configuración de Correo
            </h2>
        </template>

        <div class="py-8 space-y-6 max-w-4xl mx-auto">

            <!-- Intro Card -->
            <div class="bg-blue-50 dark:bg-blue-950/30 border border-blue-100 dark:border-blue-900/40 rounded-2xl p-5 flex gap-4">
                <InformationCircleIcon class="h-6 w-6 text-blue-500 dark:text-blue-400 shrink-0 mt-0.5" />
                <div>
                    <p class="text-sm font-semibold text-blue-800 dark:text-blue-300">¿Para qué sirve esta información?</p>
                    <p class="text-sm text-blue-700 dark:text-blue-400 mt-1">
                        Usa estos datos para configurar tu correo empresarial en aplicaciones como Outlook, Apple Mail, Thunderbird o tu teléfono móvil. Haz clic en cualquier valor para copiarlo al portapapeles.
                    </p>
                </div>
            </div>

            <!-- IMAP Card -->
            <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-50 dark:border-zinc-800 bg-gray-50/50 dark:bg-zinc-800/30 flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                        <ServerIcon class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-800 dark:text-gray-100">Servidor Entrante (IMAP)</h3>
                        <p class="text-xs text-gray-500 dark:text-zinc-400">Recepción de correos — Recomendado</p>
                    </div>
                </div>
                <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider mb-2">Servidor IMAP</label>
                        <button
                            @click="copyToClipboard(mail_config?.imap_host)"
                            class="w-full text-left px-4 py-3 bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl text-sm font-mono text-gray-800 dark:text-gray-200 hover:border-blue-400 dark:hover:border-blue-500 hover:bg-blue-50/50 dark:hover:bg-blue-950/20 transition-all group"
                            :title="mail_config?.imap_host ? 'Clic para copiar' : ''"
                        >
                            <span v-if="mail_config?.imap_host">{{ mail_config.imap_host }}</span>
                            <span v-else class="text-gray-400 dark:text-zinc-500 not-italic font-sans">No configurado</span>
                        </button>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider mb-2">Puerto</label>
                        <button
                            @click="copyToClipboard(mail_config?.imap_port?.toString())"
                            class="w-full text-left px-4 py-3 bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl text-sm font-mono text-gray-800 dark:text-gray-200 hover:border-blue-400 dark:hover:border-blue-500 hover:bg-blue-50/50 dark:hover:bg-blue-950/20 transition-all"
                            :title="mail_config?.imap_port ? 'Clic para copiar' : ''"
                        >
                            <span v-if="mail_config?.imap_port">{{ mail_config.imap_port }}</span>
                            <span v-else class="text-gray-400 dark:text-zinc-500 not-italic font-sans">—</span>
                        </button>
                    </div>
                    <div class="sm:col-span-3">
                        <div class="flex items-center gap-2">
                            <CheckCircleIcon
                                class="h-5 w-5"
                                :class="mail_config?.imap_tls ? 'text-emerald-500' : 'text-gray-300 dark:text-zinc-600'"
                            />
                            <span class="text-sm font-medium" :class="mail_config?.imap_tls ? 'text-emerald-700 dark:text-emerald-400' : 'text-gray-400 dark:text-zinc-500'">
                                {{ mail_config?.imap_tls ? 'SSL/TLS activado' : 'SSL/TLS no activado' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SMTP Card -->
            <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-50 dark:border-zinc-800 bg-gray-50/50 dark:bg-zinc-800/30 flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center">
                        <EnvelopeIcon class="h-5 w-5 text-violet-600 dark:text-violet-400" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-800 dark:text-gray-100">Servidor Saliente (SMTP)</h3>
                        <p class="text-xs text-gray-500 dark:text-zinc-400">Envío de correos</p>
                    </div>
                </div>
                <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider mb-2">Servidor SMTP</label>
                        <button
                            @click="copyToClipboard(mail_config?.smtp_host)"
                            class="w-full text-left px-4 py-3 bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl text-sm font-mono text-gray-800 dark:text-gray-200 hover:border-violet-400 dark:hover:border-violet-500 hover:bg-violet-50/50 dark:hover:bg-violet-950/20 transition-all"
                            :title="mail_config?.smtp_host ? 'Clic para copiar' : ''"
                        >
                            <span v-if="mail_config?.smtp_host">{{ mail_config.smtp_host }}</span>
                            <span v-else class="text-gray-400 dark:text-zinc-500 not-italic font-sans">No configurado</span>
                        </button>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider mb-2">Puerto</label>
                        <button
                            @click="copyToClipboard(mail_config?.smtp_port?.toString())"
                            class="w-full text-left px-4 py-3 bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl text-sm font-mono text-gray-800 dark:text-gray-200 hover:border-violet-400 dark:hover:border-violet-500 hover:bg-violet-50/50 dark:hover:bg-violet-950/20 transition-all"
                            :title="mail_config?.smtp_port ? 'Clic para copiar' : ''"
                        >
                            <span v-if="mail_config?.smtp_port">{{ mail_config.smtp_port }}</span>
                            <span v-else class="text-gray-400 dark:text-zinc-500 not-italic font-sans">—</span>
                        </button>
                    </div>
                    <div class="sm:col-span-3">
                        <div class="flex items-center gap-2">
                            <CheckCircleIcon
                                class="h-5 w-5"
                                :class="mail_config?.smtp_tls ? 'text-emerald-500' : 'text-gray-300 dark:text-zinc-600'"
                            />
                            <span class="text-sm font-medium" :class="mail_config?.smtp_tls ? 'text-emerald-700 dark:text-emerald-400' : 'text-gray-400 dark:text-zinc-500'">
                                {{ mail_config?.smtp_tls ? 'SSL/TLS activado' : 'SSL/TLS no activado' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- POP3 Card -->
            <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-50 dark:border-zinc-800 bg-gray-50/50 dark:bg-zinc-800/30 flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                        <ServerIcon class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div class="flex items-center gap-3">
                        <div>
                            <h3 class="text-base font-bold text-gray-800 dark:text-gray-100">Servidor Entrante (POP3)</h3>
                            <p class="text-xs text-gray-500 dark:text-zinc-400">Alternativo a IMAP — No recomendado</p>
                        </div>
                        <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded-full">No recomendado</span>
                    </div>
                </div>
                <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider mb-2">Servidor POP3</label>
                        <button
                            @click="copyToClipboard(mail_config?.pop_host)"
                            class="w-full text-left px-4 py-3 bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl text-sm font-mono text-gray-800 dark:text-gray-200 hover:border-amber-400 dark:hover:border-amber-500 hover:bg-amber-50/50 dark:hover:bg-amber-950/20 transition-all"
                            :title="mail_config?.pop_host ? 'Clic para copiar' : ''"
                        >
                            <span v-if="mail_config?.pop_host">{{ mail_config.pop_host }}</span>
                            <span v-else class="text-gray-400 dark:text-zinc-500 not-italic font-sans">No configurado</span>
                        </button>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider mb-2">Puerto</label>
                        <button
                            @click="copyToClipboard(mail_config?.pop_port?.toString())"
                            class="w-full text-left px-4 py-3 bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl text-sm font-mono text-gray-800 dark:text-gray-200 hover:border-amber-400 dark:hover:border-amber-500 hover:bg-amber-50/50 dark:hover:bg-amber-950/20 transition-all"
                            :title="mail_config?.pop_port ? 'Clic para copiar' : ''"
                        >
                            <span v-if="mail_config?.pop_port">{{ mail_config.pop_port }}</span>
                            <span v-else class="text-gray-400 dark:text-zinc-500 not-italic font-sans">—</span>
                        </button>
                    </div>
                    <div class="sm:col-span-3">
                        <div class="flex items-center gap-2">
                            <CheckCircleIcon
                                class="h-5 w-5"
                                :class="mail_config?.pop_tls ? 'text-emerald-500' : 'text-gray-300 dark:text-zinc-600'"
                            />
                            <span class="text-sm font-medium" :class="mail_config?.pop_tls ? 'text-emerald-700 dark:text-emerald-400' : 'text-gray-400 dark:text-zinc-500'">
                                {{ mail_config?.pop_tls ? 'SSL/TLS activado' : 'SSL/TLS no activado' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security tip -->
            <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/30 rounded-2xl p-5 flex gap-4">
                <ShieldCheckIcon class="h-6 w-6 text-emerald-500 shrink-0 mt-0.5" />
                <div>
                    <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">Seguridad</p>
                    <p class="text-sm text-emerald-700 dark:text-emerald-400 mt-1">
                        Nunca compartas tu contraseña de correo con nadie. LunAvalos nunca te la solicitará. Si necesitas soporte técnico, contáctanos a través de un ticket.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
