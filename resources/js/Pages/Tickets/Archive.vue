<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    ArchiveBoxIcon,
    ArrowUturnLeftIcon,
    BuildingOfficeIcon,
    UserIcon,
    ArrowLeftIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    archivedTickets: Array,
});

const formatDate = (dateString) => {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const priorityColors = {
    'Baja': 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400',
    'Media': 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    'Alta': 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
    'Urgente': 'bg-red-100 text-red-700 dark:bg-rose-900/40 dark:text-rose-300',
};

const unarchiveTicket = (id) => {
    router.post(route('tickets.toggleArchive', id), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Tickets Archivados" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('tickets.index')"
                        class="flex items-center gap-1.5 text-gray-400 dark:text-zinc-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors text-sm font-medium"
                    >
                        <ArrowLeftIcon class="h-4 w-4" />
                        Volver
                    </Link>
                    <div class="h-5 w-px bg-gray-200 dark:bg-zinc-700"></div>
                    <div>
                        <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100 flex items-center">
                            <ArchiveBoxIcon class="h-8 w-8 mr-2 text-[#264ab3] dark:text-blue-400" />
                            Tickets Archivados
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-zinc-400 mt-1">
                            {{ archivedTickets.length }} ticket{{ archivedTickets.length !== 1 ? 's' : '' }} archivado{{ archivedTickets.length !== 1 ? 's' : '' }}.
                            Aquí se encuentran las solicitudes completadas y retiradas del tablero.
                        </p>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-8">
            <!-- Empty state -->
            <div
                v-if="archivedTickets.length === 0"
                class="flex flex-col items-center justify-center py-24 text-center"
            >
                <div class="w-20 h-20 rounded-2xl bg-gray-100 dark:bg-zinc-800 flex items-center justify-center mb-5">
                    <ArchiveBoxIcon class="h-10 w-10 text-gray-300 dark:text-zinc-600" />
                </div>
                <h3 class="text-lg font-bold text-gray-500 dark:text-zinc-400">El archivo está vacío</h3>
                <p class="text-sm text-gray-400 dark:text-zinc-600 mt-1">No hay tickets archivados por el momento.</p>
                <Link
                    :href="route('tickets.index')"
                    class="mt-6 inline-flex items-center gap-2 bg-[#264ab3] text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-[#193074] transition-colors"
                >
                    <ArrowLeftIcon class="h-4 w-4" />
                    Ir al tablero
                </Link>
            </div>

            <!-- Tickets list -->
            <div v-else class="max-w-5xl mx-auto space-y-3">
                <div
                    v-for="ticket in archivedTickets"
                    :key="ticket.id"
                    class="group flex items-center gap-4 bg-white dark:bg-zinc-900 border border-gray-100 dark:border-zinc-800/60 rounded-2xl px-5 py-4 hover:border-gray-200 dark:hover:border-zinc-700 transition-all shadow-sm hover:shadow-md"
                >
                    <!-- Priority dot -->
                    <div
                        class="w-2.5 h-2.5 rounded-full shrink-0"
                        :class="{
                            'bg-gray-400': ticket.priority === 'Baja',
                            'bg-blue-500': ticket.priority === 'Media',
                            'bg-orange-500': ticket.priority === 'Alta',
                            'bg-red-500': ticket.priority === 'Urgente',
                        }"
                    ></div>

                    <!-- Main info -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <span class="text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full" :class="priorityColors[ticket.priority]">
                                {{ ticket.priority }}
                            </span>
                            <span class="text-xs font-bold text-gray-300 dark:text-zinc-700">#{{ ticket.id }}</span>
                            <span class="text-xs font-semibold text-gray-400 dark:text-zinc-500 bg-gray-50 dark:bg-zinc-800 px-2 py-0.5 rounded-lg">{{ ticket.status }}</span>
                        </div>
                        <Link :href="route('tickets.show', ticket.id)" class="text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-[#264ab3] dark:hover:text-blue-400 transition-colors truncate block">
                            {{ ticket.title }}
                        </Link>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1.5">
                            <div v-if="ticket.client || ticket.creator?.client" class="flex items-center gap-1 text-xs text-[#264ab3] dark:text-blue-400 font-semibold">
                                <BuildingOfficeIcon class="h-3.5 w-3.5" />
                                {{ ticket.client?.business_name || ticket.creator?.client?.business_name }}
                            </div>
                            <div v-if="ticket.creator" class="flex items-center gap-1 text-xs text-gray-400 dark:text-zinc-500">
                                <UserIcon class="h-3.5 w-3.5" />
                                {{ ticket.creator.name }}
                            </div>
                            <div class="flex items-center gap-1 text-xs text-gray-400 dark:text-zinc-500 font-medium">
                                <ArchiveBoxIcon class="h-3.5 w-3.5" />
                                Modificado: {{ formatDate(ticket.updated_at) }}
                            </div>
                        </div>
                    </div>

                    <!-- Unarchive button -->
                    <button
                        @click="unarchiveTicket(ticket.id)"
                        class="flex items-center gap-1.5 shrink-0 text-xs font-bold bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 border border-blue-200 dark:border-blue-800/50 text-blue-700 dark:text-blue-400 px-4 py-2 rounded-xl transition-all opacity-0 group-hover:opacity-100"
                        title="Desarchivar ticket"
                    >
                        <ArrowUturnLeftIcon class="h-4 w-4" />
                        Desarchivar
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
