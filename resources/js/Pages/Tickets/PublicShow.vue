<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    ChatBubbleLeftRightIcon,
    PaperClipIcon,
    CalendarDaysIcon,
    SwatchIcon,
} from '@heroicons/vue/24/outline';
import TicketCanvas from '@/Components/TicketCanvas.vue';

const props = defineProps({
    ticket:    Object,
    expiresAt: String,
});

const activeTab = ref(props.ticket.canvas_items?.length ? 'canvas' : 'conversation');

const statusColors = {
    'Nuevos':      'bg-blue-100 text-blue-700 border-blue-200',
    'En Proceso':  'bg-yellow-100 text-yellow-700 border-yellow-200',
    'En Revisión': 'bg-purple-100 text-purple-700 border-purple-200',
    'Ajustes':     'bg-orange-100 text-orange-700 border-orange-200',
    'Completados': 'bg-green-100 text-green-700 border-green-200',
};

const canvasItems = computed(() => props.ticket?.canvas_items || []);

const messages = computed(() => {
    return (props.ticket?.messages ?? [])
        .slice()
        .sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
});

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('es-MX', {
        day: '2-digit', month: 'long', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
};

const formatExpiry = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('es-MX', {
        day: '2-digit', month: 'long', year: 'numeric',
    });
};
</script>

<template>
    <Head :title="ticket.title + ' — Vista pública'" />

    <div class="min-h-screen bg-gray-50 flex flex-col">

        <!-- Header -->
        <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="/favicon.png" alt="LunAvalos" class="h-8 w-8 object-contain" />
                <span class="font-bold text-gray-900 text-sm tracking-tight">LunAvalos</span>
            </div>
            <div class="flex items-center gap-2 text-xs text-gray-400">
                <CalendarDaysIcon class="h-4 w-4" />
                Enlace válido hasta {{ formatExpiry(expiresAt) }}
            </div>
        </header>

        <!-- Ticket header -->
        <div class="bg-white border-b border-gray-200 px-6 py-5">
            <div class="max-w-5xl mx-auto">
                <div class="flex items-start gap-3 flex-wrap">
                    <span
                        :class="statusColors[ticket.status] ?? 'bg-gray-100 text-gray-600 border-gray-200'"
                        class="text-[10px] font-bold px-2.5 py-1 rounded-full border uppercase tracking-wider shrink-0 mt-0.5"
                    >
                        {{ ticket.status }}
                    </span>
                    <h1 class="text-lg font-bold text-gray-900 leading-snug">{{ ticket.title }}</h1>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white border-b border-gray-200 px-6">
            <div class="max-w-5xl mx-auto flex gap-1">
                <button
                    @click="activeTab = 'conversation'"
                    :class="[
                        'px-4 py-3 text-xs font-bold border-b-2 transition-all inline-flex items-center gap-1.5',
                        activeTab === 'conversation'
                            ? 'border-[#264ab3] text-[#264ab3]'
                            : 'border-transparent text-gray-400 hover:text-gray-600'
                    ]"
                >
                    <ChatBubbleLeftRightIcon class="h-4 w-4" />
                    Conversación
                </button>
                <button
                    v-if="canvasItems.length"
                    @click="activeTab = 'canvas'"
                    :class="[
                        'px-4 py-3 text-xs font-bold border-b-2 transition-all inline-flex items-center gap-1.5',
                        activeTab === 'canvas'
                            ? 'border-[#264ab3] text-[#264ab3]'
                            : 'border-transparent text-gray-400 hover:text-gray-600'
                    ]"
                >
                    <SwatchIcon class="h-4 w-4" />
                    Lienzo
                    <span class="ml-1 bg-[#264ab3] text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full">
                        {{ canvasItems.length }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 max-w-5xl mx-auto w-full px-4 sm:px-6 py-6">

            <!-- Conversation tab -->
            <div v-show="activeTab === 'conversation'" class="space-y-4">

                <!-- Original description -->
                <div v-if="ticket.content" class="flex items-start gap-3">
                    <img src="/favicon.png" alt="LunAvalos" class="h-9 w-9 rounded-xl object-contain bg-white border border-gray-100 shadow-sm p-1 shrink-0" />
                    <div class="flex-1 bg-white rounded-2xl rounded-tl-none border border-gray-100 p-4 shadow-sm">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-bold text-sm text-gray-800">LunAvalos</span>
                            <span class="text-[10px] text-gray-400">{{ formatDate(ticket.created_at) }}</span>
                        </div>
                        <div class="text-sm text-gray-700 leading-relaxed wysiwyg-content" v-html="ticket.content" />
                        <!-- Attachments -->
                        <div v-if="ticket.attachments?.length" class="mt-3 flex flex-wrap gap-2 border-t border-gray-50 pt-3">
                            <a
                                v-for="file in ticket.attachments"
                                :key="file.id"
                                :href="'/storage/' + file.file_path"
                                target="_blank"
                                class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-bold bg-gray-100 text-[#264ab3] hover:bg-gray-200 transition-all"
                            >
                                <PaperClipIcon class="h-3 w-3 mr-1" />
                                {{ file.file_name }}
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <template v-for="msg in messages" :key="msg.id">
                    <!-- Regular message -->
                    <div v-if="msg.user_id !== null" class="flex items-start gap-3">
                        <img
                            v-if="msg.user?.profile_photo_url"
                            :src="msg.user.profile_photo_url"
                            :alt="msg.user.name"
                            class="h-9 w-9 rounded-xl object-cover shrink-0 shadow-sm border border-gray-100"
                        />
                        <div v-else class="h-9 w-9 rounded-xl bg-[#264ab3] text-white flex items-center justify-center text-xs font-bold shrink-0 shadow-sm">
                            {{ (msg.user?.name ?? 'L').charAt(0).toUpperCase() }}
                        </div>
                        <div class="flex-1 bg-white rounded-2xl rounded-tl-none border border-gray-100 p-4 shadow-sm">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-bold text-sm text-gray-800">{{ msg.user?.name ?? 'LunAvalos' }}</span>
                                <span class="text-[10px] text-gray-400">{{ formatDate(msg.created_at) }}</span>
                            </div>
                            <div class="text-sm text-gray-700 leading-relaxed wysiwyg-content" v-html="msg.message" />
                            <div v-if="msg.file_path" class="mt-2">
                                <a
                                    :href="'/storage/' + msg.file_path"
                                    target="_blank"
                                    class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-bold bg-gray-100 text-[#264ab3] hover:bg-gray-200 transition-all"
                                >
                                    <PaperClipIcon class="h-3 w-3 mr-1" />
                                    Adjunto
                                </a>
                            </div>
                        </div>
                    </div>
                </template>

                <div v-if="!ticket.content && !messages.length" class="text-center py-16 text-gray-400 text-sm">
                    Sin mensajes aún.
                </div>
            </div>

            <!-- Canvas tab -->
            <div v-show="activeTab === 'canvas'">
                <TicketCanvas
                    :ticketId="ticket.id"
                    :items="canvasItems"
                    :canEdit="false"
                    :canApprove="false"
                />
            </div>
        </div>

        <!-- Footer -->
        <footer class="border-t border-gray-200 bg-white py-4 text-center text-xs text-gray-400">
            LunAvalos — Acceso de solo lectura · Válido hasta {{ formatExpiry(expiresAt) }}
        </footer>
    </div>
</template>
