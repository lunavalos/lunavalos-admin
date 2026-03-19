<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { 
    InboxIcon, 
    PlusIcon, 
    ChevronRightIcon,
    MagnifyingGlassIcon,
    CalendarIcon,
    ChatBubbleOvalLeftEllipsisIcon,
    XMarkIcon,
    PaperClipIcon
} from '@heroicons/vue/24/outline';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Wysiwyg from '@/Components/Wysiwyg.vue';

const props = defineProps({
    tickets: Array,
});

const search = ref('');

const filteredTickets = computed(() => {
    if (!search.value) return props.tickets;
    const s = search.value.toLowerCase();
    return props.tickets.filter(t => 
        t.title.toLowerCase().includes(s) || 
        t.status.toLowerCase().includes(s) ||
        t.id.toString().includes(s)
    );
});

const formatDate = (dateString) => {
    if (!dateString) return null;
    return new Date(dateString).toLocaleDateString('es-MX', { 
        day: '2-digit', 
        month: 'short', 
        year: 'numeric' 
    });
};

// Create logic (copied from Dashboard for consistency)
const isCreateModalOpen = ref(false);
const createForm = useForm({
    title: '',
    priority: 'Media',
    content: '',
    files: [],
});

const openCreateModal = () => {
    isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
    isCreateModalOpen.value = false;
    createForm.reset();
};

const submitCreate = () => {
    createForm.post(route('tickets.store'), {
        onSuccess: () => closeCreateModal(),
    });
};
</script>

<template>
    <Head title="Mis Tickets" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-2xl font-bold leading-tight text-gray-900 flex items-center">
                        <InboxIcon class="h-8 w-8 mr-2 text-[#264ab3]" />
                        Historial de Tickets
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Consulta y gestiona todas tus solicitudes de servicio.</p>
                </div>
                <button 
                    @click="openCreateModal"
                    class="bg-[#264ab3] hover:bg-[#193074] text-white px-6 py-3 rounded-2xl shadow-lg shadow-blue-200 transition-all font-bold flex items-center group"
                >
                    <PlusIcon class="h-5 w-5 mr-1 group-hover:rotate-90 transition-transform" />
                    Nueva Solicitud
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl">
                <!-- Search & Filters -->
                <div class="mb-8 relative max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" />
                    </div>
                    <input 
                        v-model="search"
                        type="text" 
                        placeholder="Buscar por título, estatus o ID..." 
                        class="block w-full pl-11 pr-4 py-3 border-gray-100 bg-white rounded-2xl shadow-sm focus:ring-2 focus:ring-[#264ab3] focus:border-transparent transition-all"
                    >
                </div>

                <!-- Tickets List -->
                <div class="bg-white rounded-3xl shadow-xl shadow-gray-100 border border-gray-100 overflow-hidden">
                    <div v-if="filteredTickets.length === 0" class="p-20 text-center">
                        <div class="bg-gray-50 h-20 w-20 rounded-full flex items-center justify-center mx-auto mb-4">
                            <InboxIcon class="h-10 w-10 text-gray-200" />
                        </div>
                        <h3 class="text-xl font-bold text-gray-800 mb-1">No se encontraron tickets</h3>
                        <p class="text-gray-400">Prueba con otros términos o crea una nueva solicitud.</p>
                    </div>

                    <div v-else class="divide-y divide-gray-50">
                        <Link 
                            v-for="ticket in filteredTickets" 
                            :key="ticket.id"
                            :href="route('tickets.show', ticket.id)"
                            class="p-6 flex flex-col md:flex-row md:items-center hover:bg-gray-50 transition-all group"
                        >
                            <div class="flex-1 min-w-0 md:pr-8">
                                <div class="flex items-center space-x-3 mb-2">
                                    <span class="text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-widest text-white" :class="{
                                        'bg-blue-500': ticket.status === 'Nuevos',
                                        'bg-yellow-500': ticket.status === 'En Proceso',
                                        'bg-purple-500': ticket.status === 'En Revisión',
                                        'bg-orange-500': ticket.status === 'Ajustes',
                                        'bg-green-500': ticket.status === 'Completados',
                                    }">
                                        {{ ticket.status }}
                                    </span>
                                    <span class="text-xs text-gray-400 font-bold tracking-wider">#{{ ticket.id }}</span>
                                    <span class="text-xs text-gray-500 italic flex items-center">
                                        <CalendarIcon class="h-3.5 w-3.5 mr-1" />
                                        {{ formatDate(ticket.created_at) }}
                                    </span>
                                </div>
                                <h4 class="text-lg font-bold text-gray-900 group-hover:text-[#264ab3] transition-colors truncate">
                                    {{ ticket.title }}
                                </h4>
                            </div>

                            <div class="mt-4 md:mt-0 flex items-center justify-between md:justify-end md:space-x-8 shrink-0">
                                <div class="flex items-center space-x-6">
                                    <div class="flex flex-col items-center">
                                        <span class="text-[10px] uppercase text-gray-400 font-bold mb-1 tracking-tighter">Mensajes</span>
                                        <div class="flex items-center font-bold text-sm text-gray-600">
                                            <ChatBubbleOvalLeftEllipsisIcon class="h-4 w-4 mr-1 text-blue-500" />
                                            {{ ticket.messages?.length || 0 }}
                                        </div>
                                    </div>

                                    <div v-if="ticket.assigned" class="flex flex-col items-center">
                                        <span class="text-[10px] uppercase text-gray-400 font-bold mb-1 tracking-tighter">Asignado</span>
                                        <div class="h-8 w-8 rounded-full bg-blue-100 text-[#264ab3] flex items-center justify-center text-xs font-bold border-2 border-white shadow-sm" :title="ticket.assigned.name">
                                            {{ ticket.assigned.name.charAt(0) }}
                                        </div>
                                    </div>
                                </div>

                                <div class="p-3 rounded-2xl bg-gray-50 group-hover:bg-blue-100 transition-all text-gray-400 group-hover:text-[#264ab3]">
                                    <ChevronRightIcon class="h-6 w-6" />
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- REUSE CREATE MODAL FOR CONSISTENCY -->
        <Modal :show="isCreateModalOpen" @close="closeCreateModal" max-width="2xl">
            <div class="p-8">
                <div class="flex items-center justify-between mb-8 border-b border-gray-100 pb-4">
                    <h2 class="text-2xl font-bold text-gray-900 flex items-center">
                        <InboxIcon class="h-7 w-7 mr-3 text-[#264ab3]" />
                        Nuevo Ticket
                    </h2>
                    <button @click="closeCreateModal" class="text-gray-400 hover:text-gray-600 transition-colors p-2 hover:bg-gray-100 rounded-full">
                        <XMarkIcon class="h-6 w-6" />
                    </button>
                </div>

                <form @submit.prevent="submitCreate">
                    <div class="space-y-6">
                        <div>
                            <InputLabel for="title" value="Título del Ticket" class="font-bold text-gray-700" />
                            <TextInput
                                id="title"
                                type="text"
                                class="mt-1 block w-full border-gray-200 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-2xl shadow-sm"
                                v-model="createForm.title"
                                required
                                autofocus
                                placeholder="Ej: Problema con el acceso a correos o nueva campaña..."
                            />
                            <InputError class="mt-2" :message="createForm.errors.title" />
                        </div>

                        <div>
                            <InputLabel for="priority" value="¿Qué tan urgente es?" class="font-bold text-gray-700" />
                            <select
                                id="priority"
                                class="mt-1 block w-full border-gray-200 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-2xl shadow-sm"
                                v-model="createForm.priority"
                                required
                            >
                                <option value="Baja">Baja (Varios días)</option>
                                <option value="Media">Media (Normal)</option>
                                <option value="Alta">Alta (Próximas 24-48h)</option>
                                <option value="Urgente">Urgente (Crítico)</option>
                            </select>
                            <InputError class="mt-2" :message="createForm.errors.priority" />
                        </div>

                        <div>
                            <InputLabel for="content" value="Descripción detallada" class="font-bold text-gray-700" />
                            <Wysiwyg 
                                v-model="createForm.content"
                                placeholder="Explícanos tu requerimiento a detalle..."
                                class="rounded-2xl"
                            />
                            <InputError class="mt-2" :message="createForm.errors.content" />
                        </div>

                        <div class="pt-2">
                            <InputLabel value="Adjuntar Archivos (Opcional)" class="font-bold text-gray-700" />
                            <div class="mt-1 flex flex-col space-y-3">
                                <label class="flex items-center justify-center px-4 py-6 bg-gray-50 border-2 border-dashed border-gray-200 rounded-2xl hover:bg-blue-50 hover:border-[#264ab3] transition-all cursor-pointer group">
                                    <input type="file" multiple @change="e => createForm.files = Array.from(e.target.files)" class="hidden" />
                                    <PaperClipIcon class="h-6 w-6 mr-3 text-gray-400 group-hover:text-[#264ab3]" />
                                    <span class="text-sm text-gray-500 group-hover:text-[#264ab3] font-bold">
                                        Arrastra o selecciona archivos...
                                    </span>
                                </label>
                                <div v-if="createForm.files.length > 0" class="flex flex-wrap gap-2 pt-2">
                                    <div v-for="(file, idx) in createForm.files" :key="idx" class="flex items-center bg-blue-50 text-blue-700 px-4 py-2 rounded-xl text-[11px] font-bold border border-blue-100">
                                        <PaperClipIcon class="h-3 w-3 mr-2" />
                                        <span class="truncate max-w-[150px]">{{ file.name }}</span>
                                        <button type="button" @click="createForm.files.splice(idx, 1)" class="ml-3 text-red-500 hover:text-red-700">
                                            <XMarkIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <InputError class="mt-2" :message="createForm.errors.files" />
                        </div>
                    </div>

                    <div class="mt-10 flex justify-end space-x-3">
                        <SecondaryButton @click="closeCreateModal" class="rounded-2xl px-8 py-3">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton class="rounded-2xl px-10 py-3 !bg-[#264ab3] shadow-lg shadow-blue-200" :disabled="createForm.processing">
                            Crear Ticket
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
