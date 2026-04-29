<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { 
    InboxIcon, 
    PlusIcon, 
    ChevronRightIcon,
    MagnifyingGlassIcon,
    CalendarIcon,
    ChatBubbleOvalLeftEllipsisIcon,
    XMarkIcon,
    PaperClipIcon,
    TrashIcon,
    ArchiveBoxIcon,
    CheckCircleIcon,
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
const activeTab = ref('active'); // 'active' | 'archive'

const activeTickets = computed(() => {
    return props.tickets.filter(t => t.status !== 'Completados');
});

const archivedTickets = computed(() => {
    return props.tickets.filter(t => t.status === 'Completados');
});

const filteredActiveTickets = computed(() => {
    if (!search.value) return activeTickets.value;
    const s = search.value.toLowerCase();
    return activeTickets.value.filter(t => 
        t.title.toLowerCase().includes(s) || 
        t.status.toLowerCase().includes(s) ||
        t.id.toString().includes(s)
    );
});

const filteredArchivedTickets = computed(() => {
    if (!search.value) return archivedTickets.value;
    const s = search.value.toLowerCase();
    return archivedTickets.value.filter(t => 
        t.title.toLowerCase().includes(s) || 
        t.id.toString().includes(s)
    );
});

const currentTickets = computed(() => {
    return activeTab.value === 'active' ? filteredActiveTickets.value : filteredArchivedTickets.value;
});

const formatDate = (dateString) => {
    if (!dateString) return null;
    return new Date(dateString).toLocaleDateString('es-MX', { 
        day: '2-digit', 
        month: 'short', 
        year: 'numeric' 
    });
};

// Create logic
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

const deleteTicket = (ticketId) => {
    if (confirm('¿Mover este ticket a la papelera?')) {
        router.delete(route('tickets.destroy', ticketId));
    }
};
</script>

<template>
    <Head title="Mis Tickets" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100 flex items-center">
                        <InboxIcon class="h-8 w-8 mr-2 text-[#264ab3]" />
                        Mis Tickets
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-zinc-400 mt-1">Consulta y gestiona todas tus solicitudes de servicio.</p>
                </div>
                <button 
                    @click="openCreateModal"
                    class="bg-[#264ab3] hover:bg-[#193074] text-white px-6 py-3 rounded-2xl shadow-lg shadow-blue-200 dark:shadow-none transition-all font-bold flex items-center group"
                >
                    <PlusIcon class="h-5 w-5 mr-1 group-hover:rotate-90 transition-transform" />
                    Nueva Solicitud
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl">

                <!-- Search & Tab controls -->
                <div class="mb-6 flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                    <!-- Search -->
                    <div class="relative w-full sm:max-w-sm">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" />
                        </div>
                        <input 
                            v-model="search"
                            type="text" 
                            placeholder="Buscar por título o ID..." 
                            class="block w-full pl-11 pr-4 py-3 border-gray-100 dark:border-zinc-700 bg-white dark:bg-zinc-800 dark:text-gray-100 dark:placeholder-zinc-500 rounded-2xl shadow-sm focus:ring-2 focus:ring-[#264ab3] focus:border-transparent transition-all"
                        >
                    </div>

                    <!-- Tabs -->
                    <div class="inline-flex p-1 bg-gray-100 dark:bg-zinc-800 rounded-2xl shadow-inner shrink-0">
                        <!-- Activos Tab -->
                        <button
                            @click="activeTab = 'active'"
                            :class="activeTab === 'active'
                                ? 'bg-white dark:bg-zinc-700 text-[#264ab3] dark:text-blue-400 shadow-sm'
                                : 'text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-gray-200'"
                            class="flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-bold transition-all duration-200"
                        >
                            <InboxIcon class="h-4 w-4" />
                            Activos
                            <span
                                v-if="activeTickets.length > 0"
                                :class="activeTab === 'active' ? 'bg-[#264ab3] dark:bg-blue-600 text-white' : 'bg-gray-300 dark:bg-zinc-600 text-gray-700 dark:text-gray-200'"
                                class="text-[10px] font-black px-2 py-0.5 rounded-full min-w-[20px] text-center leading-4 transition-colors"
                            >
                                {{ activeTickets.length }}
                            </span>
                        </button>

                        <!-- Historial Tab -->
                        <button
                            @click="activeTab = 'archive'"
                            :class="activeTab === 'archive'
                                ? 'bg-white dark:bg-zinc-700 text-green-700 dark:text-green-400 shadow-sm'
                                : 'text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-gray-200'"
                            class="flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-bold transition-all duration-200"
                        >
                            <ArchiveBoxIcon class="h-4 w-4" />
                            Historial
                            <span
                                v-if="archivedTickets.length > 0"
                                :class="activeTab === 'archive' ? 'bg-green-600 text-white' : 'bg-gray-300 dark:bg-zinc-600 text-gray-700 dark:text-gray-200'"
                                class="text-[10px] font-black px-2 py-0.5 rounded-full min-w-[20px] text-center leading-4 transition-colors"
                            >
                                {{ archivedTickets.length }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Archive header banner -->
                <div
                    v-if="activeTab === 'archive'"
                    class="mb-4 flex items-center gap-3 px-5 py-3 bg-green-50 dark:bg-green-900/20 border border-green-100 dark:border-green-900/40 rounded-2xl"
                >
                    <CheckCircleIcon class="h-5 w-5 text-green-500 dark:text-green-400 shrink-0" />
                    <p class="text-sm text-green-700 dark:text-green-300 font-medium">
                        Estos tickets ya fueron completados y aceptados. Son de solo lectura.
                    </p>
                </div>

                <!-- Tickets List -->
                <div
                    :class="activeTab === 'archive'
                        ? 'bg-white dark:bg-zinc-900 rounded-3xl shadow-xl shadow-gray-100 dark:shadow-none border border-green-100 dark:border-green-900/30 overflow-hidden'
                        : 'bg-white dark:bg-zinc-900 rounded-3xl shadow-xl shadow-gray-100 dark:shadow-none border border-gray-100 dark:border-zinc-800 overflow-hidden'"
                >
                    <!-- Empty state -->
                    <div v-if="currentTickets.length === 0" class="p-20 text-center">
                        <div
                            :class="activeTab === 'archive' ? 'bg-green-50 dark:bg-green-900/20' : 'bg-gray-50 dark:bg-zinc-800'"
                            class="h-20 w-20 rounded-full flex items-center justify-center mx-auto mb-4"
                        >
                            <ArchiveBoxIcon v-if="activeTab === 'archive'" class="h-10 w-10 text-green-300 dark:text-green-700" />
                            <InboxIcon v-else class="h-10 w-10 text-gray-200 dark:text-zinc-600" />
                        </div>
                        <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-1">
                            {{ activeTab === 'archive' ? 'No hay tickets en el historial' : 'No se encontraron tickets activos' }}
                        </h3>
                        <p class="text-gray-400 dark:text-zinc-500">
                            {{ activeTab === 'archive' ? 'Los tickets completados aparecerán aquí.' : 'Prueba con otros términos o crea una nueva solicitud.' }}
                        </p>
                    </div>

                    <!-- Ticket rows -->
                    <div v-else class="divide-y divide-gray-50 dark:divide-zinc-800">
                        <Link 
                            v-for="ticket in currentTickets" 
                            :key="ticket.id"
                            :href="route('tickets.show', ticket.id)"
                            class="p-6 flex flex-col md:flex-row md:items-center transition-all group relative"
                            :class="activeTab === 'archive'
                                ? 'hover:bg-green-50/50 dark:hover:bg-green-900/10'
                                : 'hover:bg-gray-50 dark:hover:bg-zinc-800/50'"
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
                                    <span class="text-xs text-gray-400 dark:text-zinc-500 font-bold tracking-wider">#{{ ticket.id }}</span>
                                    <span class="text-xs text-gray-500 dark:text-zinc-500 italic flex items-center">
                                        <CalendarIcon class="h-3.5 w-3.5 mr-1" />
                                        {{ formatDate(ticket.created_at) }}
                                    </span>
                                </div>
                                <h4
                                    class="text-lg font-bold transition-colors truncate"
                                    :class="activeTab === 'archive'
                                        ? 'text-gray-600 dark:text-zinc-400 group-hover:text-green-700 dark:group-hover:text-green-400'
                                        : 'text-gray-900 dark:text-gray-100 group-hover:text-[#264ab3] dark:group-hover:text-blue-400'"
                                >
                                    {{ ticket.title }}
                                </h4>
                            </div>

                            <div class="mt-4 md:mt-0 flex items-center justify-between md:justify-end md:space-x-8 shrink-0">
                                <div class="flex items-center space-x-6">
                                    <div class="flex flex-col items-center">
                                        <span class="text-[10px] uppercase text-gray-400 dark:text-zinc-500 font-bold mb-1 tracking-tighter">Mensajes</span>
                                        <div class="flex items-center font-bold text-sm text-gray-600 dark:text-gray-300">
                                            <ChatBubbleOvalLeftEllipsisIcon class="h-4 w-4 mr-1 text-blue-500" />
                                            {{ ticket.messages?.length || 0 }}
                                        </div>
                                    </div>

                                    <div v-if="ticket.assigned" class="flex flex-col items-center dark:text-zinc-500">
                                        <span class="text-[10px] uppercase text-gray-400 dark:text-zinc-500 font-bold mb-1 tracking-tighter">Asignado</span>
                                        <img v-if="ticket.assigned.profile_photo_url"
                                            :src="ticket.assigned.profile_photo_url"
                                            :alt="ticket.assigned.name"
                                            :title="ticket.assigned.name"
                                            class="h-8 w-8 rounded-full object-cover border-2 border-white shadow-sm"
                                        />
                                        <div v-else class="h-8 w-8 rounded-full bg-blue-100 dark:bg-blue-900/40 text-[#264ab3] dark:text-blue-400 flex items-center justify-center text-xs font-bold border-2 border-white dark:border-zinc-800 shadow-sm" :title="ticket.assigned.name">
                                            {{ ticket.assigned.name.charAt(0) }}
                                        </div>
                                    </div>
                                </div>

                                <div
                                    :class="activeTab === 'archive'
                                        ? 'bg-green-50 dark:bg-green-900/20 text-green-400 dark:text-green-600 group-hover:bg-green-100 dark:group-hover:bg-green-900/30 group-hover:text-green-700 dark:group-hover:text-green-400'
                                        : 'bg-gray-50 dark:bg-zinc-800 text-gray-400 dark:text-zinc-600 group-hover:bg-blue-100 dark:group-hover:bg-blue-900/30 group-hover:text-[#264ab3] dark:group-hover:text-blue-400'"
                                    class="p-3 rounded-2xl transition-all"
                                >
                                    <ChevronRightIcon class="h-6 w-6" />
                                </div>
                            </div>
                            
                            <!-- Overlay Delete Button (only for active, unassigned tickets) -->
                            <button 
                                v-if="activeTab === 'active' && !ticket.assigned_id"
                                @click.prevent="deleteTicket(ticket.id)"
                                class="absolute top-4 right-4 p-2 bg-white dark:bg-zinc-800 rounded-xl border border-gray-100 dark:border-zinc-700 text-gray-400 dark:text-zinc-500 hover:text-red-600 hover:border-red-100 hover:bg-red-50 dark:hover:bg-red-900/20 dark:hover:border-red-900/40 transition-all opacity-0 group-hover:opacity-100 z-10"
                                title="Eliminar Ticket"
                            >
                                <TrashIcon class="h-4 w-4" />
                            </button>
                        </Link>
                    </div>
                </div>

            </div>
        </div>

        <!-- CREATE MODAL -->
        <Modal :show="isCreateModalOpen" @close="closeCreateModal" max-width="2xl">
            <div class="p-8">
                <div class="flex items-center justify-between mb-8 border-b border-gray-100 dark:border-zinc-800 pb-4">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center">
                        <InboxIcon class="h-7 w-7 mr-3 text-[#264ab3]" />
                        Nuevo Ticket
                    </h2>
                    <button @click="closeCreateModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-2 hover:bg-gray-100 dark:hover:bg-zinc-800 rounded-full">
                        <XMarkIcon class="h-6 w-6" />
                    </button>
                </div>

                <form @submit.prevent="submitCreate">
                    <div class="space-y-6">
                        <div>
                            <InputLabel for="title" value="Título del Ticket" class="font-bold text-gray-700 dark:text-zinc-300" />
                            <TextInput
                                id="title"
                                type="text"
                                class="mt-1 block w-full border-gray-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-2xl shadow-sm"
                                v-model="createForm.title"
                                required
                                autofocus
                                placeholder="Ej: Problema con el acceso a correos o nueva campaña..."
                            />
                            <InputError class="mt-2" :message="createForm.errors.title" />
                        </div>

                        <div>
                            <InputLabel for="priority" value="¿Qué tan urgente es?" class="font-bold text-gray-700 dark:text-zinc-300" />
                            <select
                                id="priority"
                                class="mt-1 block w-full border-gray-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-2xl shadow-sm"
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
                            <InputLabel for="content" value="Descripción detallada" class="font-bold text-gray-700 dark:text-zinc-300" />
                            <Wysiwyg 
                                v-model="createForm.content"
                                placeholder="Explícanos tu requerimiento a detalle..."
                                class="rounded-2xl"
                            />
                            <InputError class="mt-2" :message="createForm.errors.content" />
                        </div>

                        <div class="pt-2">
                            <InputLabel value="Adjuntar Archivos (Opcional)" class="font-bold text-gray-700 dark:text-zinc-300" />
                            <div class="mt-1 flex flex-col space-y-3">
                                <label class="flex items-center justify-center px-4 py-6 bg-gray-50 dark:bg-zinc-800 border-2 border-dashed border-gray-200 dark:border-zinc-700 rounded-2xl hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:border-[#264ab3] transition-all cursor-pointer group">
                                    <input type="file" multiple @change="e => createForm.files = Array.from(e.target.files)" class="hidden" />
                                    <PaperClipIcon class="h-6 w-6 mr-3 text-gray-400 group-hover:text-[#264ab3]" />
                                    <span class="text-sm text-gray-500 dark:text-zinc-400 group-hover:text-[#264ab3] dark:group-hover:text-blue-400 font-bold">
                                        Arrastra o selecciona archivos...
                                    </span>
                                </label>
                                <div v-if="createForm.files.length > 0" class="flex flex-wrap gap-2 pt-2">
                                    <div v-for="(file, idx) in createForm.files" :key="idx" class="flex items-center bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 px-4 py-2 rounded-xl text-[11px] font-bold border border-blue-100 dark:border-blue-900/40">
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
                        <PrimaryButton class="rounded-2xl px-10 py-3 !bg-[#264ab3] shadow-lg shadow-blue-200 dark:shadow-none" :disabled="createForm.processing">
                            Crear Ticket
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
