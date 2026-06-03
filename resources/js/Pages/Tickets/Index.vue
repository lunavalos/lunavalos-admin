<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
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
    PaperClipIcon,
    TrashIcon,
    BuildingOfficeIcon,
    BriefcaseIcon,
    ArchiveBoxXMarkIcon,
    EnvelopeIcon,
    ArchiveBoxIcon,
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
    clients: Array,
});

const isAdmin = computed(() => {
    return page.props.auth?.user?.is_admin;
});

const statuses = [
    { name: 'Nuevos', icon: InboxIcon, color: 'bg-blue-500', bg: 'bg-gray-50 dark:bg-zinc-950' },
    { name: 'En Proceso', icon: ArrowPathIcon, color: 'bg-yellow-500', bg: 'bg-gray-50 dark:bg-zinc-950' },
    { name: 'En Revisión', icon: ChatBubbleOvalLeftEllipsisIcon, color: 'bg-purple-500', bg: 'bg-gray-50 dark:bg-zinc-950' },
    { name: 'Ajustes', icon: AdjustmentsHorizontalIcon, color: 'bg-orange-500', bg: 'bg-gray-50 dark:bg-zinc-950' },
    { name: 'Completados', icon: CheckCircleIcon, color: 'bg-green-500', bg: 'bg-gray-50 dark:bg-zinc-950' },
];

const priorityColors = {
    'Baja': 'bg-gray-100 text-gray-700 dark:bg-zinc-800 dark:text-gray-300',
    'Media': 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    'Alta': 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
    'Urgente': 'bg-red-100 text-red-700 dark:bg-rose-900/40 dark:text-rose-300',
};

// For filtering
const showOnlyMyTickets = ref(localStorage.getItem('ticket_filter_only_my') === 'true');
const selectedUserId = ref(localStorage.getItem('ticket_filter_user_id') ? parseInt(localStorage.getItem('ticket_filter_user_id')) : null);
const selectedClientId = ref(localStorage.getItem('ticket_filter_client_id') ? parseInt(localStorage.getItem('ticket_filter_client_id')) : null);

watch(showOnlyMyTickets, (val) => {
    localStorage.setItem('ticket_filter_only_my', val ? 'true' : 'false');
});
watch(selectedUserId, (val) => {
    if (val === null) {
        localStorage.removeItem('ticket_filter_user_id');
    } else {
        localStorage.setItem('ticket_filter_user_id', val.toString());
    }
});
watch(selectedClientId, (val) => {
    if (val === null) {
        localStorage.removeItem('ticket_filter_client_id');
    } else {
        localStorage.setItem('ticket_filter_client_id', val.toString());
    }
});

const filteredTickets = computed(() => {
    let result = props.tickets;
    
    if (isAdmin.value) {
        if (selectedUserId.value) {
            result = result.filter(t => t.assigned_id === selectedUserId.value || t.creator_id === selectedUserId.value);
        }
    } else {
        if (showOnlyMyTickets.value) {
            result = result.filter(t => t.assigned_id === page.props.auth.user.id || t.creator_id === page.props.auth.user.id);
        }
    }

    if (selectedClientId.value) {
        result = result.filter(t => t.client_id === selectedClientId.value || (t.creator && t.creator.client_id === selectedClientId.value));
    }

    return result;
});

// Organize tickets by status
const columns = computed(() => {
    return statuses.map(status => ({
        ...status,
        tickets: filteredTickets.value.filter(t => t.status === status.name)
    }));
});

// Modal state for creating ticket
const isCreateModalOpen = ref(false);
const createForm = useForm({
    title: '',
    priority: 'Media',
    content: '',
    assigned_id: null,
    client_id: null,
    client_service_id: null,
    due_date: '',
    files: [],
});

// Servicios del cliente seleccionado
const selectedClientServices = computed(() => {
    if (!createForm.client_id) return [];
    const client = props.clients?.find(c => c.id === createForm.client_id);
    return client?.services ?? [];
});

// Resetear el servicio cuando cambia el cliente
watch(() => createForm.client_id, () => {
    createForm.client_service_id = null;
});

// ── Combobox buscador de cliente ──────────────────────────────────────────────
const clientSearch      = ref('');
const clientDropdownOpen = ref(false);
const clientComboRef    = ref(null);

const selectedClientLabel = computed(() => {
    if (!createForm.client_id) return null;
    return props.clients?.find(c => c.id === createForm.client_id)?.business_name ?? null;
});

const filteredClients = computed(() => {
    const q = clientSearch.value.trim().toLowerCase();
    if (!q) return props.clients ?? [];
    return (props.clients ?? []).filter(c =>
        c.business_name?.toLowerCase().includes(q)
    );
});

const openClientDropdown = () => {
    clientSearch.value = '';
    clientDropdownOpen.value = true;
};

const selectClient = (client) => {
    createForm.client_id = client ? client.id : null;
    clientSearch.value   = '';
    clientDropdownOpen.value = false;
};

const clearClient = () => {
    createForm.client_id = null;
    clientSearch.value   = '';
    clientDropdownOpen.value = false;
};

const handleClickOutsideClient = (e) => {
    if (clientComboRef.value && !clientComboRef.value.contains(e.target)) {
        clientDropdownOpen.value = false;
    }
};

// ── Combobox buscador para Filtro de Clientes ─────────────────────────────────
const filterClientSearch       = ref('');
const filterClientDropdownOpen = ref(false);
const filterClientComboRef     = ref(null);

const selectedFilterClientLabel = computed(() => {
    if (!selectedClientId.value) return 'Todos los clientes';
    return props.clients?.find(c => c.id === selectedClientId.value)?.business_name ?? 'Todos los clientes';
});

const filteredClientsForFilter = computed(() => {
    const q = filterClientSearch.value.trim().toLowerCase();
    if (!q) return props.clients ?? [];
    return (props.clients ?? []).filter(c =>
        c.business_name?.toLowerCase().includes(q)
    );
});

const openFilterClientDropdown = () => {
    filterClientSearch.value = '';
    filterClientDropdownOpen.value = true;
};

const selectFilterClient = (client) => {
    selectedClientId.value = client ? client.id : null;
    filterClientSearch.value = '';
    filterClientDropdownOpen.value = false;
};

const clearFilterClient = () => {
    selectedClientId.value = null;
    filterClientSearch.value = '';
    filterClientDropdownOpen.value = false;
};

const handleClickOutsideFilterClient = (e) => {
    if (filterClientComboRef.value && !filterClientComboRef.value.contains(e.target)) {
        filterClientDropdownOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutsideClient);
    document.addEventListener('mousedown', handleClickOutsideFilterClient);
});
onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutsideClient);
    document.removeEventListener('mousedown', handleClickOutsideFilterClient);
});

const openCreateModal = () => {
    isCreateModalOpen.value = true;
};

/**
 * Returns true if the user has entered any data in the create form.
 * Used to decide whether to show the abandon-confirmation dialog.
 */
const formIsDirty = computed(() => {
    return !!createForm.title ||
        createForm.priority !== 'Media' ||
        (!!createForm.content && createForm.content !== '<p><br></p>') ||
        !!createForm.assigned_id ||
        !!createForm.client_id ||
        !!createForm.due_date ||
        createForm.files.length > 0;
});

const closeCreateModal = () => {
    isCreateModalOpen.value = false;
    createForm.reset();
    clientSearch.value = '';
    clientDropdownOpen.value = false;
};

/**
 * Called whenever something tries to close the create-ticket modal
 * (backdrop click, X button, Cancel button).
 * If the form has any data, asks for confirmation first.
 */
const requestCloseCreateModal = () => {
    if (formIsDirty.value) {
        if (!confirm('¿Estás seguro de abandonar el formulario? Los datos ingresados se perderán.')) {
            return;
        }
    }
    closeCreateModal();
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

const deleteTicket = (ticketId) => {
    if (confirm('¿Mover este ticket a la papelera?')) {
        router.delete(route('tickets.destroy', ticketId));
    }
};

const archiveTicket = (ticketId) => {
    if (confirm('¿Archivar este ticket completado?')) {
        router.post(route('tickets.toggleArchive', ticketId), {}, {
            preserveScroll: true,
        });
    }
};

// ── Team Report Modal ─────────────────────────────────────────────────────────
const isReportModalOpen = ref(false);
const reportForm = useForm({
    user_id:   null,
    date_from: '',
    date_to:   '',
});

const openReportModal = () => {
    isReportModalOpen.value = true;
};

const closeReportModal = () => {
    isReportModalOpen.value = false;
    reportForm.reset();
};

const submitReport = () => {
    reportForm.post(route('tickets.sendTeamReport'), {
        onSuccess: () => closeReportModal(),
    });
};

</script>

<template>
    <Head title="Tickets" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100 flex items-center">
                        <InboxIcon class="h-8 w-8 mr-2 text-[#264ab3] dark:text-blue-400" />
                        Módulo de Tickets
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-zinc-400 mt-1">Gestión visual de tareas y solicitudes de clientes.</p>
                </div>
                <div class="flex items-center space-x-3">
                    <!-- Checkbox for normal users -->
                    <div v-if="!isAdmin" class="flex items-center bg-white dark:bg-zinc-900 px-4 py-2 border border-gray-200 dark:border-zinc-800 rounded-xl">
                        <input id="only-my-tickets" type="checkbox" v-model="showOnlyMyTickets" class="rounded border-gray-300 dark:border-zinc-600 dark:bg-zinc-950 text-[#264ab3] shadow-sm focus:ring-[#264ab3]">
                        <label for="only-my-tickets" class="ml-2 text-sm text-gray-700 dark:text-gray-300 font-bold whitespace-nowrap cursor-pointer">Ver solo mis tickets</label>
                    </div>
                    
                    <!-- Dropdown for admins -->
                    <div v-if="isAdmin" class="flex items-center">
                        <select v-model="selectedUserId" class="border-gray-200 dark:border-zinc-800 rounded-xl text-sm font-bold text-gray-700 dark:text-gray-300 bg-white dark:bg-zinc-900 focus:border-[#264ab3] focus:ring-[#264ab3] px-10 py-2">
                            <option :value="null">Todos los usuarios</option>
                            <option v-for="assignableUser in assignableUsers" :key="assignableUser.id" :value="assignableUser.id">
                                {{ assignableUser.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Dropdown for client filter -->
                    <div ref="filterClientComboRef" class="relative w-[220px]">
                        <!-- Trigger button -->
                        <button
                            v-if="!filterClientDropdownOpen"
                            type="button"
                            @click="openFilterClientDropdown"
                            class="border border-gray-200 dark:border-zinc-800 rounded-xl text-sm font-bold text-gray-700 dark:text-gray-300 bg-white dark:bg-zinc-900 focus:border-[#264ab3] focus:ring-1 focus:ring-[#264ab3] px-4 py-2 flex items-center justify-between w-full transition"
                        >
                            <span class="truncate" :class="!selectedClientId ? 'text-gray-500 dark:text-zinc-400' : ''">
                                {{ selectedFilterClientLabel }}
                            </span>
                            <svg class="h-4 w-4 text-gray-400 dark:text-zinc-500 shrink-0 ml-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <!-- Search input (visible when open) -->
                        <div v-else class="relative w-full">
                            <input
                                id="filter_client_search"
                                v-model="filterClientSearch"
                                type="text"
                                autofocus
                                placeholder="Buscar cliente…"
                                class="w-full border border-[#264ab3] dark:border-blue-500 bg-white dark:bg-zinc-950 text-gray-800 dark:text-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#264ab3] transition"
                            />
                            <svg class="absolute right-3 top-2.5 h-4 w-4 text-gray-400 dark:text-zinc-500 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                            </svg>
                        </div>

                        <!-- Dropdown list -->
                        <div
                            v-if="filterClientDropdownOpen"
                            class="absolute right-0 z-50 mt-1 w-[240px] bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl shadow-xl overflow-y-auto"
                            style="max-height: 220px;"
                        >
                            <!-- Clear option (Todos los clientes) -->
                            <button
                                type="button"
                                @click="clearFilterClient"
                                class="w-full text-left px-4 py-2.5 text-sm text-gray-400 dark:text-zinc-500 italic hover:bg-gray-50 dark:hover:bg-zinc-800 transition flex items-center justify-between"
                                :class="!selectedClientId ? 'bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 font-semibold' : ''"
                            >
                                <span>Todos los clientes</span>
                                <svg v-if="!selectedClientId" class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div class="border-t border-gray-100 dark:border-zinc-800"></div>

                            <!-- Client options -->
                            <button
                                v-for="client in filteredClientsForFilter"
                                :key="client.id"
                                type="button"
                                @click="selectFilterClient(client)"
                                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:text-[#264ab3] dark:hover:text-blue-400 transition flex items-center justify-between group"
                                :class="selectedClientId === client.id ? 'bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 font-semibold' : ''"
                            >
                                <span class="truncate">{{ client.business_name }}</span>
                                <svg v-if="selectedClientId === client.id" class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                </svg>
                            </button>

                            <!-- Empty state -->
                            <div v-if="filteredClientsForFilter.length === 0" class="px-4 py-6 text-center text-sm text-gray-400 dark:text-zinc-500 italic">
                                No se encontraron clientes
                            </div>
                        </div>
                    </div>

                    <!-- Archive link for staff and admins -->
                    <Link
                        v-if="!$page.props.auth.user.is_client"
                        :href="route('tickets.archive')"
                        class="flex items-center gap-1.5 bg-white dark:bg-zinc-900 hover:bg-blue-50 dark:hover:bg-blue-900/20 border border-gray-200 dark:border-zinc-800 hover:border-blue-200 dark:hover:border-blue-800 text-gray-500 dark:text-zinc-400 hover:text-blue-500 dark:hover:text-blue-400 px-4 py-2.5 rounded-xl transition-all font-bold text-sm"
                    >
                        <ArchiveBoxIcon class="h-5 w-5" />
                        Archivados
                    </Link>

                    <!-- Trash link for admins -->
                    <Link
                        v-if="isAdmin"
                        :href="route('tickets.trash')"
                        class="flex items-center gap-1.5 bg-white dark:bg-zinc-900 hover:bg-red-50 dark:hover:bg-rose-900/20 border border-gray-200 dark:border-zinc-800 hover:border-red-200 dark:hover:border-rose-800 text-gray-500 dark:text-zinc-400 hover:text-red-500 dark:hover:text-rose-400 px-4 py-2.5 rounded-xl transition-all font-bold text-sm"
                    >
                        <ArchiveBoxXMarkIcon class="h-5 w-5" />
                        Papelera
                    </Link>

                    <!-- Send Report button for admins -->
                    <button
                        v-if="isAdmin"
                        @click="openReportModal"
                        class="flex items-center gap-1.5 bg-white dark:bg-zinc-900 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 border border-gray-200 dark:border-zinc-800 hover:border-emerald-300 dark:hover:border-emerald-700 text-gray-500 dark:text-zinc-400 hover:text-emerald-600 dark:hover:text-emerald-400 px-4 py-2.5 rounded-xl transition-all font-bold text-sm"
                    >
                        <EnvelopeIcon class="h-5 w-5" />
                        Enviar Reporte
                    </button>

                    <button 
                        @click="openCreateModal"
                        class="bg-[#264ab3] dark:bg-blue-600 hover:bg-[#193074] dark:hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl transition-all font-bold flex items-center group"
                    >
                        <PlusIcon class="h-5 w-5 mr-1 group-hover:rotate-90 transition-transform" />
                        Nuevo Ticket
                    </button>
                </div>
            </div>

        </template>

        <div class="py-4">
            <div class="overflow-x-auto pb-2 custom-scrollbar" style="height: calc(100vh - 260px)">
                <div class="flex space-x-6 min-w-max h-full px-2">
                    <!-- KANBAN COLUMNS -->
                    <div 
                        v-for="column in columns" 
                        :key="column.name"
                        class="w-80 flex flex-col rounded-2xl border border-gray-200 dark:border-zinc-900"
                        :class="column.bg"
                    >
                        <!-- Column Header -->
                        <div class="p-4 flex items-center justify-between border-b border-gray-200 dark:border-zinc-900 bg-white/50 dark:bg-zinc-900/50 rounded-t-2xl backdrop-blur-sm">
                            <div class="flex items-center">
                                <component :is="column.icon" class="h-5 w-5 mr-2" :class="column.color.replace('bg-', 'text-')" />
                                <h3 class="font-bold text-gray-800 dark:text-gray-100 uppercase tracking-tight text-sm">
                                    {{ column.name }}
                                </h3>
                            </div>
                            <span class="bg-gray-200 dark:bg-zinc-800 text-gray-600 dark:text-gray-300 text-xs font-bold px-2 py-0.5 rounded-full">
                                {{ column.tickets.length }}
                            </span>
                        </div>

                        <!-- Draggable Area -->
                        <!-- 
                            Note: For simplicity and since we don't have drag-drop logic fully implemented in backend 
                            yet (reordering index), we just show them. 
                            Users can click to open or move.
                        -->
                        <div class="flex-1 overflow-y-auto p-2.5 space-y-2 custom-scrollbar">
                            <Link 
                                v-for="ticket in column.tickets" 
                                :key="ticket.id"
                                :href="route('tickets.show', ticket.id)"
                                class="block bg-white dark:bg-zinc-900 p-2.5 rounded-lg border border-gray-100 dark:border-zinc-800/50 shadow-sm hover:shadow-md hover:border-blue-200 dark:hover:border-blue-800 transition-all group relative cursor-pointer"
                            >
                                <!-- Header: ID, Priority, and Quick actions -->
                                <div class="flex justify-between items-center mb-1.5 text-[10px]">
                                    <div class="flex items-center space-x-1.5">
                                        <span class="font-mono font-bold text-gray-400 dark:text-zinc-600">
                                            #{{ ticket.id }}
                                        </span>
                                        <span 
                                            class="text-[9px] font-extrabold px-1.5 py-0.5 rounded-full uppercase tracking-wider"
                                            :class="priorityColors[ticket.priority]"
                                        >
                                            {{ ticket.priority }}
                                        </span>
                                    </div>
                                    <div class="flex items-center space-x-1">
                                        <button 
                                            v-if="column.name === 'Completados' && !$page.props.auth.user.is_client"
                                            @click.prevent="archiveTicket(ticket.id)"
                                            class="p-0.5 text-gray-400 dark:text-zinc-500 hover:text-blue-500 dark:hover:text-blue-400 transition-colors"
                                            title="Archivar"
                                        >
                                            <ArchiveBoxIcon class="h-3.5 w-3.5" />
                                        </button>
                                        <button 
                                            v-if="isAdmin"
                                            @click.prevent="deleteTicket(ticket.id)"
                                            class="p-0.5 text-gray-400 dark:text-zinc-500 hover:text-red-500 dark:hover:text-rose-400 transition-colors"
                                            title="Eliminar"
                                        >
                                            <TrashIcon class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Ticket Title -->
                                <h4 class="font-bold text-gray-800 dark:text-gray-100 text-xs mb-1 group-hover:text-[#264ab3] dark:group-hover:text-blue-400 transition-colors line-clamp-2 leading-snug">
                                    {{ ticket.title }}
                                </h4>

                                <!-- Client & Service (Compact text line instead of bulky boxes) -->
                                <div v-if="ticket.client_id || (ticket.creator && ticket.creator.client)" class="text-[10px] text-gray-500 dark:text-zinc-400 truncate mb-2">
                                    <span class="font-bold text-gray-700 dark:text-zinc-300">
                                        {{ ticket.client?.business_name || ticket.creator?.client?.business_name }}
                                    </span>
                                    <template v-if="ticket.client_service_id && ticket.clientService">
                                        <span class="mx-1 text-gray-300 dark:text-zinc-700">·</span>
                                        <span class="text-indigo-600 dark:text-blue-400 font-semibold">
                                            {{ ticket.clientService.service_name }}
                                        </span>
                                    </template>
                                </div>

                                <!-- Ticket Meta / Footer -->
                                <div class="mt-2 pt-2 border-t border-gray-100 dark:border-zinc-800/80 flex items-center justify-between text-[10px]">
                                    <div class="flex items-center space-x-2 text-gray-400 dark:text-zinc-500">
                                        <span class="flex items-center" title="Mensajes">
                                            <ChatBubbleOvalLeftEllipsisIcon class="h-3.5 w-3.5 mr-0.5 text-gray-400" />
                                            {{ ticket.messages?.length || 0 }}
                                        </span>
                                        <span v-if="ticket.due_date" class="flex items-center text-orange-600 dark:text-orange-400 font-semibold bg-orange-50 dark:bg-orange-950/20 px-1 py-0.5 rounded border border-orange-100/30" title="Fecha de entrega">
                                            <CalendarIcon class="h-3.5 w-3.5 mr-0.5" />
                                            {{ formatDate(ticket.due_date) }}
                                        </span>
                                    </div>
                                    
                                    <div v-if="ticket.assigned" class="flex items-center text-gray-500 dark:text-zinc-400 shrink-0">
                                        <span class="text-[9px] font-semibold mr-1 max-w-[60px] truncate">{{ ticket.assigned.name.split(' ')[0] }}</span>
                                        <img v-if="ticket.assigned.profile_photo_url"
                                            :src="ticket.assigned.profile_photo_url"
                                            :alt="ticket.assigned.name"
                                            :title="ticket.assigned.name"
                                            width="20"
                                            height="20"
                                            class="h-5 w-5 rounded-full object-cover border border-gray-100 dark:border-zinc-800 shadow-sm"
                                        />
                                        <div v-else class="h-5 w-5 rounded-full bg-[#264ab3] dark:bg-blue-600 text-white flex items-center justify-center text-[8px] font-bold border border-gray-100 dark:border-zinc-800" :title="ticket.assigned.name">
                                            {{ ticket.assigned.name.charAt(0) }}
                                        </div>
                                    </div>
                                </div>
                            </Link>

                            <!-- Empty State in Column -->
                            <div v-if="column.tickets.length === 0" class="border-2 border-dashed border-gray-200 dark:border-zinc-900 rounded-xl p-6 text-center text-gray-300 dark:text-zinc-800">
                                <InboxIcon class="h-8 w-8 mx-auto mb-2 opacity-50" />
                                <span class="text-xs">Sin tickets</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- NEW TICKET MODAL -->
        <Modal :show="isCreateModalOpen" @close="requestCloseCreateModal" max-width="2xl">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6 border-b border-gray-100 dark:border-zinc-800 pb-4">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Crear Nuevo Ticket</h2>
                    <button @click="requestCloseCreateModal" class="text-gray-400 hover:text-gray-600 transition">
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
                                class="mt-1 block w-full border-gray-300 dark:border-zinc-800 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-xl dark:bg-zinc-950"
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
                                class="mt-1 block w-full border-gray-300 dark:border-zinc-800 bg-white dark:bg-zinc-950 text-gray-700 dark:text-gray-300 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-xl shadow-sm"
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
                                class="mt-1 block w-full border-gray-300 dark:border-zinc-800 bg-white dark:bg-zinc-950 text-gray-700 dark:text-gray-300 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-xl shadow-sm"
                                v-model="createForm.assigned_id"
                            >
                                <option :value="null">Sin asignar</option>
                                <option v-for="user in assignableUsers" :key="user.id" :value="user.id">
                                    {{ user.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="createForm.errors.assigned_id" />
                        </div>

                        <div v-if="!$page.props.auth.user.is_client" ref="clientComboRef" class="relative">
                            <InputLabel for="client_search" value="Cliente / Empresa Beneficiaria" />

                            <!-- Trigger button -->
                            <button
                                v-if="!clientDropdownOpen"
                                type="button"
                                id="client_id"
                                @click="openClientDropdown"
                                class="mt-1 w-full flex items-center justify-between border border-gray-300 dark:border-zinc-800 bg-white dark:bg-zinc-950 text-gray-700 dark:text-gray-300 rounded-xl shadow-sm px-3 py-2 text-sm text-left focus:outline-none focus:border-[#264ab3] focus:ring-1 focus:ring-[#264ab3] transition"
                            >
                                <span :class="!selectedClientLabel ? 'text-gray-400 dark:text-zinc-500' : ''">
                                    {{ selectedClientLabel ?? 'Seleccionar Cliente' }}
                                </span>
                                <svg class="h-4 w-4 text-gray-400 dark:text-zinc-500 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                </svg>
                            </button>

                            <!-- Search input (visible when open) -->
                            <div v-if="clientDropdownOpen" class="mt-1 relative">
                                <input
                                    id="client_search"
                                    v-model="clientSearch"
                                    type="text"
                                    autofocus
                                    placeholder="Buscar cliente…"
                                    class="w-full border border-[#264ab3] dark:border-blue-500 bg-white dark:bg-zinc-950 text-gray-800 dark:text-gray-200 rounded-xl shadow-sm px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#264ab3] transition"
                                />
                                <svg class="absolute right-3 top-2.5 h-4 w-4 text-gray-400 dark:text-zinc-500 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                                </svg>
                            </div>

                            <!-- Dropdown list -->
                            <div
                                v-if="clientDropdownOpen"
                                class="absolute z-50 mt-1 w-full bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl shadow-xl overflow-y-auto"
                                style="max-height: 450px;"
                            >
                                <!-- Clear option -->
                                <button
                                    type="button"
                                    @click="clearClient"
                                    class="w-full text-left px-4 py-2.5 text-sm text-gray-400 dark:text-zinc-500 italic hover:bg-gray-50 dark:hover:bg-zinc-800 transition"
                                >
                                    Sin asignar cliente
                                </button>
                                <div class="border-t border-gray-100 dark:border-zinc-800"></div>

                                <!-- Client options -->
                                <button
                                    v-for="client in filteredClients"
                                    :key="client.id"
                                    type="button"
                                    @click="selectClient(client)"
                                    class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:text-[#264ab3] dark:hover:text-blue-400 transition flex items-center justify-between group"
                                    :class="createForm.client_id === client.id ? 'bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 font-semibold' : ''"
                                >
                                    {{ client.business_name }}
                                    <svg v-if="createForm.client_id === client.id" class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                    </svg>
                                </button>

                                <!-- Empty state -->
                                <div v-if="filteredClients.length === 0" class="px-4 py-6 text-center text-sm text-gray-400 dark:text-zinc-500 italic">
                                    No se encontraron clientes
                                </div>
                            </div>

                            <InputError class="mt-2" :message="createForm.errors.client_id" />
                        </div>

                        <!-- Servicio del cliente (aparece dinámicamente si el cliente tiene servicios) -->
                        <div v-if="!$page.props.auth.user.is_client && createForm.client_id">
                            <InputLabel for="client_service_id" value="Servicio Relacionado" />
                            <select
                                id="client_service_id"
                                class="mt-1 block w-full border-gray-300 dark:border-zinc-800 bg-white dark:bg-zinc-950 text-gray-700 dark:text-gray-300 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-xl shadow-sm"
                                v-model="createForm.client_service_id"
                            >
                                <option :value="null">Sin servicio específico</option>
                                <option
                                    v-for="svc in selectedClientServices"
                                    :key="svc.id"
                                    :value="svc.id"
                                >
                                    {{ svc.service_name }}
                                </option>
                                <option v-if="selectedClientServices.length === 0" disabled>
                                    Este cliente no tiene servicios activos
                                </option>
                            </select>
                            <InputError class="mt-2" :message="createForm.errors.client_service_id" />
                        </div>

                        <div v-if="isAdmin">
                            <InputLabel for="due_date" value="Fecha de Entrega (Opcional)" />
                            <TextInput
                                id="due_date"
                                type="date"
                                class="mt-1 block w-full border-gray-300 dark:border-zinc-800 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-xl dark:bg-zinc-950"
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
                                <label class="flex items-center justify-center px-4 py-3 bg-gray-50 dark:bg-zinc-950/50 border-2 border-dashed border-gray-300 dark:border-zinc-800 rounded-xl hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:border-[#264ab3] dark:hover:border-blue-500 transition-all cursor-pointer group">
                                    <input 
                                        type="file" 
                                        multiple 
                                        @change="e => createForm.files = Array.from(e.target.files)"
                                        class="hidden" 
                                    />
                                    <PaperClipIcon class="h-5 w-5 mr-2 text-gray-400 dark:text-zinc-500 group-hover:text-[#264ab3] dark:group-hover:text-blue-400" />
                                    <span class="text-sm text-gray-500 dark:text-zinc-400 group-hover:text-[#264ab3] dark:group-hover:text-blue-400 font-medium">
                                        Selecciona uno o varios archivos...
                                    </span>
                                </label>
                                
                                <!-- Preview of selected files -->
                                <div v-if="createForm.files.length > 0" class="flex flex-wrap gap-2 pt-2">
                                    <div v-for="(file, idx) in createForm.files" :key="idx" class="flex items-center bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 px-3 py-1.5 rounded-lg text-[11px] font-bold border border-blue-100 dark:border-blue-800/30 italic">
                                        <PaperClipIcon class="h-3 w-3 mr-1" />
                                        {{ file.name }}
                                        <button type="button" @click="createForm.files.splice(idx, 1)" class="ml-2 text-red-500 dark:text-rose-400 hover:text-red-700 dark:hover:text-rose-300">
                                            <XMarkIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <InputError class="mt-2" :message="createForm.errors.files" />
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end space-x-3">
                        <SecondaryButton @click="requestCloseCreateModal" class="rounded-xl px-6">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton
                            class="rounded-xl px-8 !bg-[#264ab3] hover:!bg-[#193074] !shadow-none"
                            :class="{ 'opacity-25': createForm.processing }"
                            :disabled="createForm.processing"
                        >
                            Crear Ticket
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- TEAM REPORT MODAL -->
        <Modal :show="isReportModalOpen" @close="closeReportModal" max-width="lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6 border-b border-gray-100 dark:border-zinc-800 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl">
                            <EnvelopeIcon class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Enviar Reporte de Tickets</h2>
                            <p class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">Se enviará al correo principal configurado en Ajustes</p>
                        </div>
                    </div>
                    <button @click="closeReportModal" class="text-gray-400 hover:text-gray-600 transition">
                        <XMarkIcon class="h-6 w-6" />
                    </button>
                </div>

                <form @submit.prevent="submitReport" class="space-y-5">
                    <!-- User selector -->
                    <div>
                        <InputLabel for="report_user" value="Usuario (miembro del equipo)" />
                        <select
                            id="report_user"
                            class="mt-1 block w-full border-gray-300 dark:border-zinc-800 bg-white dark:bg-zinc-950 text-gray-700 dark:text-gray-300 focus:border-emerald-500 focus:ring-emerald-500 rounded-xl shadow-sm"
                            v-model="reportForm.user_id"
                            required
                        >
                            <option :value="null" disabled>Seleccionar usuario...</option>
                            <option v-for="user in assignableUsers" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="reportForm.errors.user_id" />
                    </div>

                    <!-- Date range -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="report_date_from" value="Desde" />
                            <input
                                id="report_date_from"
                                type="date"
                                class="mt-1 block w-full border-gray-300 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 focus:border-emerald-500 focus:ring-emerald-500 rounded-xl shadow-sm text-sm"
                                v-model="reportForm.date_from"
                                required
                            />
                            <InputError class="mt-1" :message="reportForm.errors.date_from" />
                        </div>
                        <div>
                            <InputLabel for="report_date_to" value="Hasta" />
                            <input
                                id="report_date_to"
                                type="date"
                                class="mt-1 block w-full border-gray-300 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 focus:border-emerald-500 focus:ring-emerald-500 rounded-xl shadow-sm text-sm"
                                v-model="reportForm.date_to"
                                :min="reportForm.date_from"
                                required
                            />
                            <InputError class="mt-1" :message="reportForm.errors.date_to" />
                        </div>
                    </div>

                    <!-- Info note -->
                    <div class="flex items-start gap-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/40 rounded-xl p-3">
                        <svg class="h-4 w-4 text-amber-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                        <p class="text-xs text-amber-700 dark:text-amber-300">
                            El reporte incluirá todos los tickets <strong>asignados</strong> a este usuario creados en el rango seleccionado, agrupados por estado.
                        </p>
                    </div>

                    <div class="flex justify-end space-x-3 pt-2">
                        <SecondaryButton type="button" @click="closeReportModal" class="rounded-xl px-6">
                            Cancelar
                        </SecondaryButton>
                        <button
                            type="submit"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-2.5 rounded-xl font-bold transition flex items-center gap-2 disabled:opacity-50"
                            :disabled="reportForm.processing"
                        >
                            <EnvelopeIcon class="h-4 w-4" />
                            {{ reportForm.processing ? 'Enviando...' : 'Enviar Reporte' }}
                        </button>
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

/* Dark Mode Scroller */
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background: #3f3f46;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #52525b;
}

/* Glassmorphism subtle effect for column headers */
.backdrop-blur-sm {
    backdrop-filter: blur(4px);
}
</style>
