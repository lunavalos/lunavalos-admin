<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import draggable from 'vuedraggable';
import { 
    PlusIcon, 
    ChatBubbleOvalLeftEllipsisIcon, 
    UserIcon, 
    CalendarIcon,
    ExclamationCircleIcon,
    CheckCircleIcon,
    ArrowPathIcon,
    InboxIcon,
    AdjustmentsHorizontalIcon,
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

const page = usePage();

const props = defineProps({
    tickets: Array,
    assignableUsers: Array,
});

const isAdmin = computed(() => {
    return page.props.auth?.user?.is_admin;
});

const statuses = [
    { name: 'Nuevos', icon: InboxIcon, color: 'bg-blue-500', bg: 'bg-blue-50' },
    { name: 'En Proceso', icon: ArrowPathIcon, color: 'bg-yellow-500', bg: 'bg-yellow-50' },
    { name: 'En Revisión', icon: ChatBubbleOvalLeftEllipsisIcon, color: 'bg-purple-500', bg: 'bg-purple-50' },
    { name: 'Ajustes', icon: AdjustmentsHorizontalIcon, color: 'bg-orange-500', bg: 'bg-orange-50' },
    { name: 'Completados', icon: CheckCircleIcon, color: 'bg-green-500', bg: 'bg-green-50' },
];

const priorityColors = {
    'Baja': 'bg-gray-100 text-gray-700',
    'Media': 'bg-blue-100 text-blue-700',
    'Alta': 'bg-orange-100 text-orange-700',
    'Urgente': 'bg-red-100 text-red-700',
};

// Organize tickets by status
const columns = computed(() => {
    return statuses.map(status => ({
        ...status,
        tickets: props.tickets.filter(t => t.status === status.name)
    }));
});

// Modal state for creating ticket
const isCreateModalOpen = ref(false);
const createForm = useForm({
    title: '',
    priority: 'Media',
    content: '',
    assigned_id: null,
    due_date: '',
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

// Handle drag and drop status change
const onMove = (evt) => {
    const ticketId = evt.item.__vueParentComponent.props.item.id;
    const newStatus = evt.to.dataset.status;
    
    // We update via inertia
    router.post(route('tickets.updateStatus', ticketId), {
        status: newStatus
    }, {
        preserveScroll: true,
        only: ['tickets']
    });
};

// Form for quick actions
const actionForm = useForm({});

const updateTicketStatus = (ticketId, newStatus) => {
    actionForm.post(route('tickets.updateStatus', ticketId), {
        data: { status: newStatus },
        preserveScroll: true,
    });
};

const today = new Date().toISOString().split('T')[0];

const formatDate = (dateString) => {
    if (!dateString) return null;
    return new Date(dateString).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
};

</script>

<template>
    <Head title="Tickets" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold leading-tight text-gray-900 flex items-center">
                        <InboxIcon class="h-8 w-8 mr-2 text-[#264ab3]" />
                        Módulo de Tickets
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Gestión visual de tareas y solicitudes de clientes.</p>
                </div>
                <button 
                    @click="openCreateModal"
                    class="bg-[#264ab3] hover:bg-[#193074] text-white px-5 py-2.5 rounded-xl shadow-lg shadow-blue-200 transition-all font-bold flex items-center group"
                >
                    <PlusIcon class="h-5 w-5 mr-1 group-hover:rotate-90 transition-transform" />
                    Nuevo Ticket
                </button>
            </div>
        </template>

        <div class="py-6 h-[calc(100vh-160px)]">
            <div class="h-full overflow-x-auto pb-4 custom-scrollbar">
                <div class="flex space-x-6 min-w-max h-full px-2">
                    <!-- KANBAN COLUMNS -->
                    <div 
                        v-for="column in columns" 
                        :key="column.name"
                        class="w-80 flex flex-col bg-gray-100 rounded-2xl border border-gray-200 shadow-sm"
                    >
                        <!-- Column Header -->
                        <div class="p-4 flex items-center justify-between border-b border-gray-200 bg-white/50 rounded-t-2xl backdrop-blur-sm">
                            <div class="flex items-center">
                                <component :is="column.icon" class="h-5 w-5 mr-2" :class="column.color.replace('bg-', 'text-')" />
                                <h3 class="font-bold text-gray-800 uppercase tracking-tight text-sm">
                                    {{ column.name }}
                                </h3>
                            </div>
                            <span class="bg-gray-200 text-gray-600 text-xs font-bold px-2 py-0.5 rounded-full">
                                {{ column.tickets.length }}
                            </span>
                        </div>

                        <!-- Draggable Area -->
                        <!-- 
                            Note: For simplicity and since we don't have drag-drop logic fully implemented in backend 
                            yet (reordering index), we just show them. 
                            Users can click to open or move.
                        -->
                        <div class="flex-1 overflow-y-auto p-3 space-y-3 custom-scrollbar">
                            <Link 
                                v-for="ticket in column.tickets" 
                                :key="ticket.id"
                                :href="route('tickets.show', ticket.id)"
                                class="block bg-white p-4 rounded-xl border border-gray-100 shadow-sm hover:shadow-md hover:border-blue-200 transition-all group relative cursor-pointer"
                            >
                                <!-- Ticket Priority Badge -->
                                <div class="flex justify-between items-start mb-2">
                                    <span 
                                        class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-widest"
                                        :class="priorityColors[ticket.priority]"
                                    >
                                        {{ ticket.priority }}
                                    </span>
                                    <div class="flex -space-x-2">
                                        <div v-if="ticket.assigned" class="relative group/avatar">
                                            <div class="h-6 w-6 rounded-full bg-[#264ab3] text-white flex items-center justify-center text-[10px] border-2 border-white font-bold" :title="ticket.assigned.name">
                                                {{ ticket.assigned.name.charAt(0) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Ticket Title -->
                                <h4 class="font-bold text-gray-800 text-sm mb-2 group-hover:text-[#264ab3] transition-colors line-clamp-2">
                                    {{ ticket.title }}
                                </h4>

                                <!-- Ticket Meta -->
                                <div class="flex items-center justify-between mt-4 pt-3 border-t border-gray-50 text-[11px] text-gray-400 font-medium">
                                    <div class="flex items-center space-x-3">
                                        <div class="flex items-center">
                                            <ChatBubbleOvalLeftEllipsisIcon class="h-4 w-4 mr-1 text-gray-400" />
                                            {{ ticket.messages?.length || 0 }}
                                        </div>
                                        <div v-if="ticket.due_date" class="flex items-center text-orange-600 font-bold bg-orange-50 px-2 py-0.5 rounded border border-orange-100">
                                            <CalendarIcon class="h-3 w-3 mr-1" />
                                            {{ formatDate(ticket.due_date) }}
                                        </div>
                                    </div>
                                    <div class="flex items-center text-gray-300 font-bold">
                                        #{{ ticket.id }}
                                    </div>
                                </div>
                            </Link>

                            <!-- Empty State in Column -->
                            <div v-if="column.tickets.length === 0" class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center text-gray-300">
                                <InboxIcon class="h-8 w-8 mx-auto mb-2 opacity-50" />
                                <span class="text-xs">Sin tickets</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- NEW TICKET MODAL -->
        <Modal :show="isCreateModalOpen" @close="closeCreateModal" max-width="2xl">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6 border-b pb-4">
                    <h2 class="text-xl font-bold text-gray-900">Crear Nuevo Ticket</h2>
                    <button @click="closeCreateModal" class="text-gray-400 hover:text-gray-600 transition">
                        <XMarkIcon class="h-6 w-6" />
                    </button>
                </div>

                <form @submit.prevent="submitCreate">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <InputLabel for="title" value="Título del Ticket" />
                            <TextInput
                                id="title"
                                type="text"
                                class="mt-1 block w-full border-gray-300 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-xl"
                                v-model="createForm.title"
                                required
                                autofocus
                                placeholder="Ej: Error en formulario de contacto"
                            />
                            <InputError class="mt-2" :message="createForm.errors.title" />
                        </div>

                        <div>
                            <InputLabel for="priority" value="Prioridad" />
                            <select
                                id="priority"
                                class="mt-1 block w-full border-gray-300 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-xl shadow-sm"
                                v-model="createForm.priority"
                                required
                            >
                                <option value="Baja">Baja</option>
                                <option value="Media">Media</option>
                                <option value="Alta">Alta</option>
                                <option value="Urgente">Urgente</option>
                            </select>
                            <InputError class="mt-2" :message="createForm.errors.priority" />
                        </div>

                        <div v-if="!$page.props.auth.user.is_client">
                            <InputLabel for="assigned_id" value="Asignar a (Opcional)" />
                            <select
                                id="assigned_id"
                                class="mt-1 block w-full border-gray-300 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-xl shadow-sm"
                                v-model="createForm.assigned_id"
                            >
                                <option :value="null">Sin asignar</option>
                                <option v-for="user in assignableUsers" :key="user.id" :value="user.id">
                                    {{ user.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="createForm.errors.assigned_id" />
                        </div>

                        <div v-if="isAdmin">
                            <InputLabel for="due_date" value="Fecha de Entrega (Opcional)" />
                            <TextInput
                                id="due_date"
                                type="date"
                                class="mt-1 block w-full"
                                v-model="createForm.due_date"
                                :min="today"
                            />
                            <InputError class="mt-2" :message="createForm.errors.due_date" />
                        </div>

                        <div class="md:col-span-2">
                            <InputLabel for="content" value="Descripción / Contenido" />
                            <Wysiwyg 
                                v-model="createForm.content"
                                placeholder="Describe a detalle el requerimiento..."
                                class="mt-1"
                            />
                            <InputError class="mt-2" :message="createForm.errors.content" />
                        </div>

                        <!-- Multi-file Upload -->
                        <div class="md:col-span-2 pt-2">
                            <InputLabel value="Adjuntar Archivos (Opcional)" />
                            <div class="mt-1 flex flex-col space-y-2">
                                <label class="flex items-center justify-center px-4 py-3 bg-gray-50 border-2 border-dashed border-gray-300 rounded-xl hover:bg-blue-50 hover:border-[#264ab3] transition-all cursor-pointer group">
                                    <input 
                                        type="file" 
                                        multiple 
                                        @change="e => createForm.files = Array.from(e.target.files)"
                                        class="hidden" 
                                    />
                                    <PaperClipIcon class="h-5 w-5 mr-2 text-gray-400 group-hover:text-[#264ab3]" />
                                    <span class="text-sm text-gray-500 group-hover:text-[#264ab3] font-medium">
                                        Selecciona uno o varios archivos...
                                    </span>
                                </label>
                                
                                <!-- Preview of selected files -->
                                <div v-if="createForm.files.length > 0" class="flex flex-wrap gap-2 pt-2">
                                    <div v-for="(file, idx) in createForm.files" :key="idx" class="flex items-center bg-blue-50 text-blue-700 px-3 py-1.5 rounded-lg text-[11px] font-bold border border-blue-100 italic">
                                        <PaperClipIcon class="h-3 w-3 mr-1" />
                                        {{ file.name }}
                                        <button type="button" @click="createForm.files.splice(idx, 1)" class="ml-2 text-red-500 hover:text-red-700">
                                            <XMarkIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <InputError class="mt-2" :message="createForm.errors.files" />
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end space-x-3">
                        <SecondaryButton @click="closeCreateModal" class="rounded-xl px-6">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton
                            class="rounded-xl px-8 !bg-[#264ab3] hover:!bg-[#193074]"
                            :class="{ 'opacity-25': createForm.processing }"
                            :disabled="createForm.processing"
                        >
                            Crear Ticket
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    height: 8px;
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}

/* Glassmorphism subtle effect for column headers */
.backdrop-blur-sm {
    backdrop-filter: blur(4px);
}
</style>
