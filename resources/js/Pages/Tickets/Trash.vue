<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    ArchiveBoxXMarkIcon,
    ArrowUturnLeftIcon,
    TrashIcon,
    InboxIcon,
    CalendarDaysIcon,
    BuildingOfficeIcon,
    UserIcon,
    ExclamationTriangleIcon,
    ArrowLeftIcon,
} from '@heroicons/vue/24/outline';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    trashedTickets: Array,
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

const restoreTicket = (id) => {
    router.post(route('tickets.restore', id), {}, {
        preserveScroll: true,
    });
};

// Confirm empty trash modal
const showConfirmModal = ref(false);
const confirmingEmpty = ref(false);

const openEmptyConfirm = () => {
    showConfirmModal.value = true;
};

const closeEmptyConfirm = () => {
    showConfirmModal.value = false;
};

const emptyTrash = () => {
    confirmingEmpty.value = true;
    router.delete(route('tickets.emptyTrash'), {
        onFinish: () => {
            confirmingEmpty.value = false;
            showConfirmModal.value = false;
        },
    });
};
</script>

<template>
    <Head title="Papelera de Tickets" />

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
                            <ArchiveBoxXMarkIcon class="h-8 w-8 mr-2 text-red-500 dark:text-rose-400" />
                            Papelera de Tickets
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-zinc-400 mt-1">
                            {{ trashedTickets.length }} ticket{{ trashedTickets.length !== 1 ? 's' : '' }} en la papelera.
                            Los tickets aquí se pueden restaurar o eliminar permanentemente.
                        </p>
                    </div>
                </div>

                <button
                    v-if="trashedTickets.length > 0"
                    @click="openEmptyConfirm"
                    class="flex items-center gap-2 bg-red-50 dark:bg-rose-900/20 hover:bg-red-100 dark:hover:bg-rose-900/40 border border-red-200 dark:border-rose-800/50 text-red-600 dark:text-rose-400 px-5 py-2.5 rounded-xl transition-all font-bold text-sm"
                >
                    <TrashIcon class="h-5 w-5" />
                    Vaciar Papelera
                </button>
            </div>
        </template>

        <div class="py-8">
            <!-- Empty state -->
            <div
                v-if="trashedTickets.length === 0"
                class="flex flex-col items-center justify-center py-24 text-center"
            >
                <div class="w-20 h-20 rounded-2xl bg-gray-100 dark:bg-zinc-800 flex items-center justify-center mb-5">
                    <ArchiveBoxXMarkIcon class="h-10 w-10 text-gray-300 dark:text-zinc-600" />
                </div>
                <h3 class="text-lg font-bold text-gray-500 dark:text-zinc-400">La papelera está vacía</h3>
                <p class="text-sm text-gray-400 dark:text-zinc-600 mt-1">No hay tickets eliminados por el momento.</p>
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
                    v-for="ticket in trashedTickets"
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
                        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 truncate">{{ ticket.title }}</h3>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1.5">
                            <div v-if="ticket.client || ticket.creator?.client" class="flex items-center gap-1 text-xs text-[#264ab3] dark:text-blue-400 font-semibold">
                                <BuildingOfficeIcon class="h-3.5 w-3.5" />
                                {{ ticket.client?.business_name || ticket.creator?.client?.business_name }}
                            </div>
                            <div v-if="ticket.creator" class="flex items-center gap-1 text-xs text-gray-400 dark:text-zinc-500">
                                <UserIcon class="h-3.5 w-3.5" />
                                {{ ticket.creator.name }}
                            </div>
                            <div class="flex items-center gap-1 text-xs text-red-400 dark:text-rose-500 font-medium">
                                <TrashIcon class="h-3.5 w-3.5" />
                                Eliminado: {{ formatDate(ticket.deleted_at) }}
                            </div>
                        </div>
                    </div>

                    <!-- Restore button -->
                    <button
                        @click="restoreTicket(ticket.id)"
                        class="flex items-center gap-1.5 shrink-0 text-xs font-bold bg-green-50 dark:bg-green-900/20 hover:bg-green-100 dark:hover:bg-green-900/40 border border-green-200 dark:border-green-800/50 text-green-700 dark:text-green-400 px-4 py-2 rounded-xl transition-all opacity-0 group-hover:opacity-100"
                        title="Restaurar ticket"
                    >
                        <ArrowUturnLeftIcon class="h-4 w-4" />
                        Restaurar
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Confirm Empty Trash Modal -->
    <Modal :show="showConfirmModal" @close="closeEmptyConfirm" max-width="md">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-red-100 dark:bg-rose-900/30 flex items-center justify-center shrink-0">
                    <ExclamationTriangleIcon class="h-6 w-6 text-red-600 dark:text-rose-400" />
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">¿Vaciar la papelera?</h3>
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Esta acción eliminará <strong>permanentemente</strong> los
                        <strong>{{ trashedTickets.length }} ticket{{ trashedTickets.length !== 1 ? 's' : '' }}</strong>
                        en la papelera. Esta operación <strong>no se puede deshacer</strong>.
                    </p>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button
                    @click="closeEmptyConfirm"
                    class="px-5 py-2.5 rounded-xl border border-gray-200 dark:border-zinc-700 text-gray-700 dark:text-gray-300 font-bold text-sm hover:bg-gray-50 dark:hover:bg-zinc-800 transition-colors"
                >
                    Cancelar
                </button>
                <button
                    @click="emptyTrash"
                    :disabled="confirmingEmpty"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 dark:bg-rose-700 dark:hover:bg-rose-600 text-white font-bold text-sm transition-colors disabled:opacity-50"
                >
                    <TrashIcon class="h-4 w-4" />
                    {{ confirmingEmpty ? 'Eliminando...' : 'Sí, vaciar papelera' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
