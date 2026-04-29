<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, nextTick } from 'vue';
import { 
    ChevronLeftIcon,
    ChevronRightIcon,
    PaperAirplaneIcon,
    PaperClipIcon,
    ChatBubbleLeftRightIcon,
    UserIcon,
    CalendarIcon,
    ClockIcon,
    CheckCircleIcon,
    ArrowPathIcon,
    InboxIcon,
    AdjustmentsHorizontalIcon,
    XMarkIcon,
    PlusIcon,
    TrashIcon,
    BuildingOfficeIcon,
    BriefcaseIcon,
    PlayIcon,
    StopCircleIcon,
} from '@heroicons/vue/24/outline';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Wysiwyg from '@/Components/Wysiwyg.vue';

const props = defineProps({
    ticket: Object,
    assignableUsers: Array,
    clientServices: Array,
});

const page = usePage();

const isClient = computed(() => {
    return page.props.auth.user.is_client;
});

const isAdmin = computed(() => {
    return page.props.auth.user.is_admin;
});

const statusColors = {
    'Nuevos': 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/40 dark:text-blue-300 dark:border-blue-900/50',
    'En Proceso': 'bg-yellow-100 text-yellow-700 border-yellow-200 dark:bg-yellow-900/40 dark:text-yellow-300 dark:border-yellow-900/50',
    'En Revisión': 'bg-purple-100 text-purple-700 border-purple-200 dark:bg-purple-900/40 dark:text-purple-300 dark:border-purple-900/50',
    'Ajustes': 'bg-orange-100 text-orange-700 border-orange-200 dark:bg-orange-900/40 dark:text-orange-300 dark:border-orange-900/50',
    'Completados': 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:text-green-300 dark:border-green-900/50',
};

const priorityColors = {
    'Baja': 'bg-gray-100 text-gray-700 dark:bg-zinc-800 dark:text-gray-300',
    'Media': 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    'Alta': 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
    'Urgente': 'bg-red-100 text-red-700 dark:bg-rose-900/40 dark:text-rose-300',
};

// Message form
const messageForm = useForm({
    message: '',
    file: null,
    change_status: '',
});

const fileInput = ref(null);
const messageContainer = ref(null);

const scrollToBottom = () => {
    if (messageContainer.value) {
        messageContainer.value.scrollTop = messageContainer.value.scrollHeight;
    }
};

onMounted(() => {
    scrollToBottom();
});

const handleFileChange = (e) => {
    messageForm.file = e.target.files[0];
};

const submitMessage = (statusToChange = '') => {
    messageForm.change_status = statusToChange;
    messageForm.post(route('tickets.addMessage', props.ticket.id), {
        onSuccess: () => {
            messageForm.reset();
            if (fileInput.value) fileInput.value.value = '';
            nextTick(() => scrollToBottom());
        },
        preserveScroll: true,
    });
};

// Quick status updates (mostly for admin/employees)
const statusForm = useForm({
    status: '',
    due_date: props.ticket.due_date ? new Date(props.ticket.due_date).toISOString().split('T')[0] : '',
});

const updateStatus = (newStatus) => {
    statusForm.status = newStatus;
    statusForm.post(route('tickets.updateStatus', props.ticket.id), {
        preserveScroll: true,
        only: ['ticket']
    });
};

const assignForm = useForm({
    assigned_id: props.ticket.assigned_id,
});

const serviceForm = useForm({
    client_service_id: props.ticket.client_service_id ?? null,
});

const updateService = () => {
    serviceForm.post(route('tickets.updateService', props.ticket.id), {
        preserveScroll: true,
    });
};

const updateAssignee = () => {
    assignForm.post(route('tickets.assign', props.ticket.id), {
        preserveScroll: true,
    });
};

const formatDate = (dateString, withTime = false) => {
    if (!dateString) return null;
    const options = { 
        day: '2-digit', 
        month: 'long', 
        year: 'numeric' 
    };
    if (withTime) {
        options.hour = '2-digit';
        options.minute = '2-digit';
    }
    return new Date(dateString).toLocaleDateString('es-MX', options);
};

// Logic for visibility of actions
const canAssign = computed(() => {
    if (props.ticket?.status === 'Completados') return false;
    if (isAdmin.value) return true;
    if (!isClient.value && (isCreator.value || props.ticket?.assigned_id === page.props.auth?.user?.id)) return true;
    return false;
});
const canProcess = computed(() => props.ticket?.status !== 'Completados' && !isClient.value);
const canReview = computed(() => props.ticket?.assigned_id === page.props.auth?.user?.id && (props.ticket?.status === 'En Proceso' || props.ticket?.status === 'Ajustes'));
const canComment = computed(() => {
    if (props.ticket?.status === 'Completados') return false;
    if (isAdmin.value || isClient.value) return true;
    return props.ticket?.assigned_id === page.props.auth?.user?.id;
});
const canTake = computed(() => {
    return !isAdmin.value && !isClient.value && !props.ticket?.assigned_id && props.ticket?.status === 'Nuevos';
});

const isCreator = computed(() => {
    return props.ticket?.creator_id === page.props.auth?.user?.id;
});

const daysSinceLastResponse = computed(() => {
    if (!props.ticket?.messages || props.ticket.messages.length === 0) {
        const lastDate = new Date(props.ticket.created_at);
        return (new Date() - lastDate) / (1000 * 60 * 60 * 24);
    }
    
    // Find messages from anybody NOT the assigned user (creator or admins)
    const creatorMessages = props.ticket.messages.filter(m => m.user_id !== props.ticket.assigned_id);
    if (creatorMessages.length === 0) {
        const lastDate = new Date(props.ticket.created_at);
        return (new Date() - lastDate) / (1000 * 60 * 60 * 24);
    }
    
    const latestMessage = [...creatorMessages].sort((a,b) => new Date(b.created_at) - new Date(a.created_at))[0];
    return (new Date() - new Date(latestMessage.created_at)) / (1000 * 60 * 60 * 24);
});

const canClose = computed(() => {
    const status = props.ticket?.status;

    if (status === 'Completados') return false;

    if (isAdmin.value || isCreator.value || props.ticket?.assigned_id === page.props.auth?.user?.id) {
        return true;
    }

    return false;
});

const canRequestAdjustments = computed(() => {
    // Only Client can request adjustments or complete, and only when the ticket is in 'En Revisión'
    return props.ticket?.status === 'En Revisión' && isClient.value;
});

const today = new Date().toISOString().split('T')[0];

const takeTicket = () => {
    assignForm.assigned_id = page.props.auth?.user?.id;
    updateAssignee();
};

const canDelete = computed(() => {
    if (isAdmin.value) return true;
    // Client can delete their own ticket if not assigned yet
    if (isClient.value && isCreator.value && !props.ticket.assigned_id) return true;
    return false;
});

const deleteTicket = () => {
    if (confirm('¿Mover este ticket a la papelera?')) {
        router.delete(route('tickets.destroy', props.ticket.id));
    }
};

// Work timer
const startWorkForm = useForm({});

const startWork = () => {
    startWorkForm.post(route('tickets.startWork', props.ticket.id));
};

const isAssignedToMe = computed(() => {
    return props.ticket?.assigned_id === page.props.auth?.user?.id;
});

// Compute elapsed/total work time
const formatDuration = (start, end) => {
    if (!start) return null;
    const from = new Date(start);
    const to = end ? new Date(end) : new Date();
    const diffMs = to - from;
    const totalMinutes = Math.floor(diffMs / 60000);
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    if (hours === 0) return `${minutes}m`;
    return `${hours}h ${minutes}m`;
};

// System messages that represent status changes (logged in conversation)
const statusLogMessages = computed(() => {
    if (!props.ticket?.messages) return [];
    return props.ticket.messages
        .filter(m => m.user_id === null)
        .sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
});

</script>

<template>
    <Head v-if="ticket" :title="ticket.title" />
    <Head v-else title="Cargando Ticket..." />

    <AuthenticatedLayout v-if="ticket">
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex items-center">
                    <Link :href="route('tickets.index')" class="mr-4 p-2 bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-zinc-800 hover:border-[#264ab3] text-gray-500 dark:text-zinc-400 hover:text-[#264ab3] transition-all">
                        <ChevronLeftIcon class="h-6 w-6" />
                    </Link>
                    <div>
                        <div class="flex items-center space-x-2 mb-1">
                            <span class="text-xs font-bold text-gray-400 dark:text-zinc-500">TICKET #{{ ticket.id }}</span>
                            <span 
                                class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-tighter"
                                :class="priorityColors[ticket.priority]"
                            >
                                {{ ticket.priority }}
                            </span>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 leading-tight">{{ ticket.title }}</h2>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <div class="flex items-center">
                        <select 
                            v-if="!isClient"
                            @change="updateStatus($event.target.value)"
                            class="px-4 py-2 rounded-xl font-bold text-sm border-2 focus:ring-0 cursor-pointer transition-all appearance-none"
                            :class="statusColors[ticket.status]"
                        >
                            <option v-for="status in ['Nuevos', 'En Proceso', 'En Revisión', 'Ajustes', 'Completados']" 
                                :key="status" 
                                :value="status"
                                :selected="ticket.status === status"
                            >
                                {{ status }}
                            </option>
                        </select>
                        <div v-else class="px-4 py-2 rounded-xl font-bold text-sm border-2 transition-all" :class="statusColors[ticket.status]">
                            {{ ticket.status }}
                        </div>
                    </div>
                    
                    <!-- Admin Assign -->
                    <div v-if="canAssign" class="relative">
                        <select 
                            v-model="assignForm.assigned_id"
                            @change="updateAssignee"
                            class="text-sm border-gray-300 dark:border-zinc-800 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-xl bg-blue-50 dark:bg-zinc-950 text-blue-700 dark:text-blue-400 font-bold"
                        >
                            <option :value="null">Asignar Ticket</option>
                            <option v-for="user in assignableUsers" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Delete Button -->
                    <button 
                        v-if="canDelete"
                        @click="deleteTicket"
                        class="p-2.5 bg-red-50 dark:bg-rose-900/20 text-red-600 dark:text-rose-400 rounded-xl border border-red-100 dark:border-rose-900/40 hover:bg-red-600 dark:hover:bg-rose-600 hover:text-white transition-all flex items-center group"
                        title="Eliminar Ticket"
                    >
                        <TrashIcon class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 h-[calc(100vh-160px)] grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- LEFT: CONVERSATION -->
            <div class="lg:col-span-2 flex flex-col bg-white dark:bg-zinc-900 rounded-3xl border border-gray-200 dark:border-zinc-800 overflow-hidden">
                <!-- Chat Messages -->
                <div ref="messageContainer" class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar bg-gray-50/50 dark:bg-zinc-950/50">
                    <!-- Ticket Description as First Message -->
                    <div 
                        v-if="ticket" 
                        class="flex items-start"
                        :class="ticket.creator_id === $page.props.auth.user.id ? 'flex-row-reverse' : ''"
                    >
                        <img v-if="ticket.creator?.profile_photo_url"
                            :src="ticket.creator.profile_photo_url" 
                            :alt="ticket.creator?.name"
                            class="h-10 w-10 rounded-2xl object-cover shrink-0 shadow-sm border-2 border-white"
                            :class="[
                                ticket.creator_id === $page.props.auth.user.id ? 'ml-4' : 'mr-4'
                            ]"
                        />
                        <div v-else
                            class="h-10 w-10 rounded-2xl flex items-center justify-center text-white shrink-0 shadow-sm font-bold border-2 border-white dark:border-zinc-900"
                            :class="[
                                ticket.creator_id === $page.props.auth.user.id ? 'ml-4 bg-[#264ab3]' : 'mr-4 bg-gray-200 !text-gray-500 dark:bg-zinc-800 dark:!text-zinc-400'
                            ]"
                        >
                            {{ ticket.creator?.name ? ticket.creator.name.charAt(0) : '?' }}
                        </div>
                        <div 
                            class="flex-1 max-w-2xl p-5 rounded-2xl border"
                            :class="[
                                ticket.creator_id === $page.props.auth.user.id 
                                ? 'bg-blue-600 text-white border-blue-700 rounded-tr-none' 
                                : 'bg-white dark:bg-zinc-900 text-gray-700 dark:text-gray-200 border-gray-100 dark:border-zinc-800 rounded-tl-none'
                            ]"
                        >
                            <div 
                                class="flex justify-between items-center mb-2"
                                :class="ticket.creator_id === page.props.auth?.user?.id ? 'flex-row-reverse' : ''"
                            >
                                <span class="font-bold text-sm" :class="ticket.creator_id === page.props.auth?.user?.id ? 'text-white' : 'text-gray-900 dark:text-gray-100'">
                                    {{ ticket.creator?.name || 'Usuario' }}
                                </span>
                                <span class="text-[10px]" :class="ticket.creator_id === page.props.auth?.user?.id ? 'text-blue-200' : 'text-gray-400 dark:text-zinc-500'">
                                    {{ formatDate(ticket.created_at, true) }}
                                </span>
                            </div>
                            <div class="text-sm whitespace-pre-wrap leading-relaxed wysiwyg-content" v-html="ticket.content || 'Sin descripción'">
                            </div>

                            <!-- Initial Attachments -->
                            <div v-if="ticket.attachments?.length > 0" class="mt-4 flex flex-wrap gap-2 overflow-hidden border-t pt-3" :class="ticket.creator_id === $page.props.auth.user.id ? 'border-blue-500' : 'border-gray-50 dark:border-zinc-800'">
                                <a 
                                    v-for="file in ticket.attachments" 
                                    :key="file.id"
                                    :href="'/storage/' + file.file_path" 
                                    target="_blank"
                                    class="inline-flex items-center px-3 py-1.5 rounded-lg text-[10px] font-bold transition-all"
                                    :class="ticket.creator_id === $page.props.auth.user.id ? 'bg-blue-500 text-white hover:bg-blue-400' : 'bg-gray-100 dark:bg-zinc-800 text-[#264ab3] dark:text-blue-400 hover:bg-gray-200 dark:hover:bg-zinc-600'"
                                >
                                    <PaperClipIcon class="h-3 w-3 mr-1" />
                                    {{ file.file_name }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Conversation Messages -->
                    <div 
                        v-for="msg in ticket.messages" 
                        :key="msg.id"
                        class="flex items-start"
                        :class="msg.user_id === $page.props.auth.user.id ? 'flex-row-reverse' : (msg.user_id === null ? 'justify-center my-4' : '')"
                    >
                        <!-- System message (Bot) -->
                        <div v-if="msg.user_id === null" class="w-full max-w-lg bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-600 text-gray-500 dark:text-zinc-300 text-xs px-4 py-3 rounded-2xl text-center shadow-sm flex flex-col items-center">
                            <span class="font-bold mb-1 flex items-center text-gray-700 dark:text-gray-100">
                                🤖 Sistema
                            </span>
                            <span>{{ msg.message }}</span>
                            <span class="text-[10px] text-gray-400 dark:text-zinc-500 mt-1 block">{{ formatDate(msg.created_at, true) }}</span>
                        </div>
                        
                        <!-- Regular User Message -->
                        <template v-else>
                            <img v-if="msg.user?.profile_photo_url"
                                :src="msg.user.profile_photo_url" 
                                :alt="msg.user?.name"
                                class="h-10 w-10 rounded-2xl object-cover shrink-0 shadow-sm border-2 border-white"
                                :class="[
                                    msg.user_id === $page.props.auth.user.id ? 'ml-4' : 'mr-4'
                                ]"
                            />
                            <div v-else
                                class="h-10 w-10 rounded-2xl flex items-center justify-center text-white shrink-0 shadow-sm font-bold border-2 border-white"
                                :class="[
                                    msg.user_id === $page.props.auth.user.id ? 'ml-4 bg-[#264ab3]' : 'mr-4 bg-purple-500'
                                ]"
                            >
                                {{ msg.user?.name ? msg.user.name.charAt(0) : '?' }}
                            </div>
                            <div 
                                class="flex-1 max-w-2xl p-5 rounded-2xl border"
                                :class="[
                                    msg.user_id === $page.props.auth.user.id 
                                    ? 'bg-blue-600 text-white border-blue-700 rounded-tr-none' 
                                    : 'bg-white dark:bg-zinc-900 text-gray-700 dark:text-gray-200 border-gray-100 dark:border-zinc-800 rounded-tl-none'
                                ]"
                            >
                                <div 
                                    class="flex justify-between items-center mb-2"
                                    :class="msg.user_id === page.props.auth?.user?.id ? 'flex-row-reverse' : ''"
                                >
                                    <span class="font-bold text-sm" :class="msg.user_id === page.props.auth?.user?.id ? 'text-white' : 'text-gray-900 dark:text-gray-100'">
                                        {{ msg.user?.name || 'Usuario' }}
                                    </span>
                                    <span class="text-[10px]" :class="msg.user_id === page.props.auth?.user?.id ? 'text-blue-200' : 'text-gray-400 dark:text-zinc-500'">
                                        {{ formatDate(msg.created_at, true) }}
                                    </span>
                                </div>
                                <div class="text-sm whitespace-pre-wrap leading-relaxed wysiwyg-content" v-html="msg.message">
                                </div>
                                
                                <!-- File Attachment link -->
                                <div v-if="msg.file_path" class="mt-4 pt-3 border-t overflow-hidden" :class="msg.user_id === $page.props.auth.user.id ? 'border-blue-500' : 'border-gray-50 dark:border-zinc-800'">
                                    <a 
                                        :href="'/storage/' + msg.file_path" 
                                        target="_blank"
                                        class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold transition-all"
                                        :class="msg.user_id === $page.props.auth.user.id ? 'bg-blue-500 text-white hover:bg-blue-400' : 'bg-gray-100 dark:bg-zinc-800 text-[#264ab3] dark:text-blue-400 hover:bg-gray-200 dark:hover:bg-zinc-600'"
                                    >
                                        <PaperClipIcon class="h-4 w-4 mr-2" />
                                        Descargar Archivo Adjunto
                                    </a>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Chat Input Area -->
                <div v-if="canComment" class="p-4 bg-white dark:bg-zinc-900 border-t border-gray-100 dark:border-zinc-800">
                    <form @submit.prevent="submitMessage('')" class="flex flex-col space-y-3">
                        <div class="flex flex-col bg-gray-50 dark:bg-zinc-950 rounded-2xl border border-gray-200 dark:border-zinc-800 focus-within:border-[#264ab3] dark:focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-100 dark:focus-within:ring-blue-900/30 transition-all overflow-hidden">
                            <Wysiwyg 
                                v-model="messageForm.message"
                                placeholder="Escribe un mensaje aquí..."
                            />
                            
                            <div class="flex items-center justify-between p-2 bg-white/50 dark:bg-zinc-900/50 border-t border-gray-100 dark:border-zinc-800">
                                <label class="cursor-pointer p-2 text-gray-400 dark:text-zinc-500 hover:text-[#264ab3] dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-xl transition-all relative overflow-hidden">
                                    <input type="file" ref="fileInput" @change="handleFileChange" class="absolute inset-0 opacity-0 cursor-pointer" />
                                    <PaperClipIcon class="h-5 w-5" />
                                </label>
                                <div class="flex items-center space-x-2">
                                    <div v-if="messageForm.file" class="text-[10px] text-[#264ab3] font-bold italic animate-pulse">
                                        Archivo listo para enviar
                                    </div>
                                    <button 
                                        type="submit"
                                        class="bg-[#264ab3] text-white p-3 rounded-xl hover:bg-[#193074] transition-all disabled:opacity-50"
                                        :disabled="messageForm.processing || (!messageForm.message || messageForm.message === '<p><br></p>')"
                                    >
                                        <PaperAirplaneIcon class="h-5 w-5 rotate-[-45deg]" />
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons based on status -->
                        <div class="flex flex-wrap gap-2 pt-2">
                            <!-- Client/Admin sends and asks for "Ajustes" -->
                            <button 
                                v-if="canRequestAdjustments"
                                type="button"
                                @click="submitMessage('Ajustes')"
                                class="text-xs font-bold bg-orange-500 text-white px-4 py-2 rounded-xl border border-orange-600 hover:bg-orange-600 transition-all flex items-center shadow-none shadow-sm"
                                :disabled="messageForm.processing || !messageForm.message"
                            >
                                <AdjustmentsHorizontalIcon class="h-4 w-4 mr-2" />
                                Solicitar Ajustes
                            </button>

                            <button 
                                v-if="canRequestAdjustments"
                                type="button"
                                @click="submitMessage('Completados')"
                                class="text-xs font-bold bg-green-600 text-white px-4 py-2 rounded-xl border border-green-700 hover:bg-green-700 transition-all flex items-center shadow-none shadow-sm"
                                :disabled="messageForm.processing || !messageForm.message"
                            >
                                <CheckCircleIcon class="h-4 w-4 mr-2" />
                                Aceptar y Completar
                            </button>
                        </div>
                    </form>
                </div>
                <div v-else class="p-8 bg-gray-50 dark:bg-zinc-900 border-t border-gray-100 dark:border-zinc-800 text-center">
                    <p v-if="ticket.status === 'Completados'" class="text-sm text-gray-500 dark:text-zinc-400 italic font-bold">
                        Esta conversación ha finalizado.
                    </p>
                    <p v-else class="text-sm text-gray-500 dark:text-zinc-400 italic">
                        Solo el administrador y el usuario asignado pueden participar en esta conversación.
                    </p>
                </div>
            </div>

            <!-- RIGHT: SIDEBAR INFO -->
            <div class="space-y-6">
                <!-- Info Card -->
                <div class="bg-white dark:bg-zinc-900 rounded-3xl border border-gray-200 dark:border-zinc-800 p-6">
                    <h3 class="font-bold text-gray-900 dark:text-gray-100 border-b border-gray-100 dark:border-zinc-800 pb-4 mb-4 flex items-center uppercase text-xs tracking-widest text-[#264ab3] dark:text-blue-400">
                        <ClockIcon class="h-4 w-4 mr-2" />
                        Detalles del Ticket
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <span class="text-xs text-gray-400 dark:text-zinc-500 block mb-1">Creador:</span>
                            <div class="flex items-center">
                                <img v-if="ticket.creator?.profile_photo_url"
                                    :src="ticket.creator.profile_photo_url" 
                                    :alt="ticket.creator.name"
                                    class="h-6 w-6 rounded-full object-cover border border-gray-100 dark:border-zinc-800 shadow-sm mr-2"
                                />
                                <span v-else class="h-6 w-6 rounded-full bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-zinc-400 flex items-center justify-center text-[10px] font-bold mr-2">
                                    {{ ticket.creator.name.charAt(0) }}
                                </span>
                                <span class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ ticket.creator.name }}</span>
                            </div>
                        </div>

                        <div>
                            <span class="text-xs text-gray-400 dark:text-zinc-500 block mb-1">Asignado a:</span>
                            <div v-if="ticket.assigned" class="flex items-center">
                                <img v-if="ticket.assigned?.profile_photo_url"
                                    :src="ticket.assigned.profile_photo_url" 
                                    :alt="ticket.assigned.name"
                                    class="h-6 w-6 rounded-full object-cover border border-gray-100 dark:border-zinc-800 shadow-sm mr-2"
                                />
                                <span v-else class="h-6 w-6 rounded-full bg-[#264ab3] dark:bg-blue-600 text-white flex items-center justify-center text-[10px] font-bold mr-2">
                                    {{ ticket.assigned?.name ? ticket.assigned.name.charAt(0) : '?' }}
                                </span>
                                <span class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ ticket.assigned?.name }}</span>
                            </div>
                            <div v-else-if="canTake" class="pt-1">
                                <button 
                                    @click="takeTicket"
                                    class="w-full bg-blue-50 dark:bg-blue-900/30 text-[#264ab3] dark:text-blue-400 border border-blue-100 dark:border-blue-900/50 hover:bg-[#264ab3] dark:hover:bg-blue-600 hover:text-white px-3 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-center uppercase tracking-wider"
                                >
                                    <PlusIcon class="h-3.5 w-3.5 mr-1" />
                                    Tomar este ticket
                                </button>
                            </div>
                            <div v-else class="text-sm text-gray-400 dark:text-zinc-500 italic">No asignado</div>
                        </div>

                        <div>
                            <span class="text-xs text-gray-400 block mb-1">Fecha de Entrega:</span>
                            <div v-if="ticket.due_date" class="flex items-center text-orange-600 font-bold text-sm">
                                <CalendarIcon class="h-4 w-4 mr-2" />
                                {{ formatDate(ticket.due_date) }}
                            </div>
                            <div v-else class="text-sm text-gray-400 italic">No establecida</div>
                        </div>

                        <div>
                            <span class="text-xs text-gray-400 dark:text-zinc-500 block mb-1">Último cambio de estado:</span>
                            <div v-if="ticket.status_updated_at" class="flex items-center text-gray-700 dark:text-gray-200 font-bold text-sm">
                                <ArrowPathIcon class="h-4 w-4 mr-2 text-gray-400 dark:text-zinc-500" />
                                {{ formatDate(ticket.status_updated_at, true) }}
                            </div>
                            <div v-else class="text-sm text-gray-400 dark:text-zinc-500 italic">No registrado</div>
                        </div>

                        <!-- Date Picker for Assignee -->
                        <div v-if="canProcess" class="pt-4 border-t border-gray-50 dark:border-zinc-800">
                            <InputLabel for="due_date" value="Establecer Entrega" class="text-[10px]" />
                            <div class="flex mt-1 space-x-2">
                                <TextInput 
                                    id="due_date" 
                                    type="date" 
                                    class="text-xs flex-1 rounded-xl dark:bg-zinc-950 border-gray-300 dark:border-zinc-800" 
                                    v-model="statusForm.due_date"
                                    :min="today" 
                                />
                                <button 
                                    v-if="ticket.status === 'Nuevos'"
                                    @click="updateStatus('En Proceso')"
                                    class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 text-xs font-bold rounded-xl transition-all"
                                >
                                    Fijar
                                </button>
                                <button 
                                    v-else
                                    @click="updateStatus(ticket.status)"
                                    class="bg-gray-200 hover:bg-gray-300 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-gray-700 dark:text-gray-200 px-3 py-1 text-xs font-bold rounded-xl transition-all"
                                >
                                    Actualizar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Work Timer Widget (only for assigned user, only if ticket is not completed) -->
                <div v-if="isAssignedToMe && ticket.status !== 'Completados'" class="bg-white dark:bg-zinc-900 rounded-3xl border border-gray-200 dark:border-zinc-800 p-6">
                    <h3 class="font-bold border-b border-gray-100 dark:border-zinc-800 pb-4 mb-4 flex items-center uppercase text-xs tracking-widest text-emerald-600 dark:text-emerald-400">
                        <ClockIcon class="h-4 w-4 mr-2" />
                        Tiempo de Trabajo
                    </h3>

                    <!-- Not started yet -->
                    <button
                        v-if="!ticket.work_started_at"
                        @click="startWork"
                        :disabled="startWorkForm.processing"
                        class="w-full flex items-center justify-center gap-2 bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 text-white text-sm font-bold py-3 px-4 rounded-xl transition-all shadow-sm"
                    >
                        <PlayIcon class="h-4 w-4" />
                        Iniciar Trabajo
                    </button>

                    <!-- Work in progress (started, not finished) -->
                    <div v-else-if="!ticket.work_finished_at" class="flex items-center gap-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-900/40 rounded-xl px-4 py-3">
                        <span class="relative flex h-3 w-3 shrink-0">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                        </span>
                        <div>
                            <p class="text-xs font-bold text-emerald-700 dark:text-emerald-300">En progreso</p>
                            <p class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-0.5">
                                Iniciado: {{ formatDate(ticket.work_started_at, true) }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Work Metrics Card (visible to all non-clients when work was tracked) -->
                <div v-if="!isClient && ticket.work_started_at" class="bg-white dark:bg-zinc-900 rounded-3xl border border-gray-200 dark:border-zinc-800 p-6">
                    <h3 class="font-bold border-b border-gray-100 dark:border-zinc-800 pb-4 mb-4 flex items-center uppercase text-xs tracking-widest text-emerald-600 dark:text-emerald-400">
                        <ClockIcon class="h-4 w-4 mr-2" />
                        Métricas de Trabajo
                    </h3>
                    <div class="space-y-3">
                        <!-- Start / finish timestamps -->
                        <div>
                            <span class="text-xs text-gray-400 dark:text-zinc-500 block mb-1">Inicio del trabajo:</span>
                            <p class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ formatDate(ticket.work_started_at, true) }}</p>
                        </div>
                        <div v-if="ticket.work_finished_at">
                            <span class="text-xs text-gray-400 dark:text-zinc-500 block mb-1">Completado el:</span>
                            <p class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ formatDate(ticket.work_finished_at, true) }}</p>
                        </div>
                        <div class="pt-3 border-t border-gray-50 dark:border-zinc-800">
                            <span class="text-xs text-gray-400 dark:text-zinc-500 block mb-1">
                                {{ ticket.work_finished_at ? 'Tiempo total (inicio → completado):' : 'Tiempo en curso desde inicio:' }}
                            </span>
                            <p class="text-2xl font-black"
                                :class="ticket.work_finished_at
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-yellow-500 dark:text-yellow-400'"
                            >
                                {{ formatDuration(ticket.work_started_at, ticket.work_finished_at) }}
                            </p>
                        </div>

                        <!-- Status change timeline -->
                        <div v-if="statusLogMessages.length > 0" class="pt-3 border-t border-gray-50 dark:border-zinc-800">
                            <span class="text-xs text-gray-400 dark:text-zinc-500 font-bold uppercase block mb-3">Historial de cambios</span>
                            <ol class="relative border-l border-gray-200 dark:border-zinc-700 ml-2 space-y-3">
                                <li
                                    v-for="msg in statusLogMessages"
                                    :key="msg.id"
                                    class="ml-4"
                                >
                                    <span class="absolute -left-[7px] mt-1 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-gray-100 dark:bg-zinc-800 ring-2 ring-white dark:ring-zinc-900">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400 dark:bg-zinc-500"></span>
                                    </span>
                                    <p class="text-[11px] text-gray-700 dark:text-gray-300 leading-snug" v-html="msg.message"></p>
                                    <time class="text-[10px] text-gray-400 dark:text-zinc-600">{{ formatDate(msg.created_at, true) }}</time>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- Client Info Card (Only for Staff/Admin when linked to a Client) -->
                <div v-if="!isClient && (ticket.client || ticket.creator?.client)" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-900/40 rounded-3xl p-6 shadow-sm">
                    <h3 class="font-bold text-gray-900 dark:text-gray-100 border-b border-blue-200/50 dark:border-blue-800/30 pb-4 mb-4 flex items-center uppercase text-xs tracking-widest text-[#264ab3] dark:text-blue-400">
                        <BuildingOfficeIcon class="h-4 w-4 mr-2" />
                        Datos del Cliente
                    </h3>
 
                    <div class="space-y-4">
                        <div>
                            <span class="text-[10px] text-gray-400 dark:text-zinc-500 font-bold uppercase block mb-1">Empresa / Negocio:</span>
                            <p class="text-sm font-bold text-[#264ab3] dark:text-blue-400">{{ ticket.client?.business_name || ticket.creator?.client?.business_name }}</p>
                        </div>

                        <div>
                            <span class="text-[10px] text-gray-400 dark:text-zinc-500 font-bold uppercase block mb-1">Contacto:</span>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ ticket.client?.contact_name || ticket.creator?.client?.contact_name || 'Sin contacto' }}</p>
                            <p class="text-[11px] text-gray-500 dark:text-zinc-400">{{ ticket.client?.email || ticket.creator?.client?.email || '' }}</p>
                        </div>

                            <!-- Servicio vinculado -->
                            <div>
                                <span class="text-[10px] text-gray-400 dark:text-zinc-500 font-bold uppercase block mb-1">Servicio Relacionado:</span>

                                <!-- Servicio ya vinculado -->
                                <div v-if="ticket.clientService" class="flex items-center gap-2 mt-1">
                                    <BriefcaseIcon class="h-4 w-4 text-[#264ab3] dark:text-blue-400 shrink-0" />
                                    <span class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ ticket.clientService.service_name }}</span>
                                    <span v-if="ticket.clientService.billing_type === 'monthly'" class="text-[10px] bg-green-100 dark:bg-emerald-900/40 text-green-700 dark:text-emerald-300 px-1.5 py-0.5 rounded font-bold">Mensual</span>
                                    <span v-else-if="ticket.clientService.billing_type === 'annual'" class="text-[10px] bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 px-1.5 py-0.5 rounded font-bold">Anual</span>
                                    <span v-else-if="ticket.clientService.billing_type === 'once'" class="text-[10px] bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 px-1.5 py-0.5 rounded font-bold">Único</span>
                                </div>

                                <!-- Sin servicio: mostrar selector inline (solo para staff/admin) -->
                                <div v-else-if="!isClient && clientServices && clientServices.length > 0" class="mt-1 space-y-2">
                                    <select
                                        v-model="serviceForm.client_service_id"
                                        class="w-full text-sm border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 text-gray-700 dark:text-gray-300 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-xl shadow-sm py-1.5"
                                    >
                                        <option :value="null">Sin servicio específico</option>
                                        <option
                                            v-for="svc in clientServices"
                                            :key="svc.id"
                                            :value="svc.id"
                                        >
                                            {{ svc.service_name }}
                                            <template v-if="svc.billing_type === 'monthly'"> · Mensual</template>
                                            <template v-else-if="svc.billing_type === 'annual'"> · Anual</template>
                                        </option>
                                    </select>
                                    <button
                                        type="button"
                                        @click="updateService"
                                        :disabled="serviceForm.processing || serviceForm.client_service_id === null"
                                        class="w-full flex items-center justify-center gap-1.5 bg-[#264ab3] hover:bg-[#193074] disabled:opacity-40 text-white text-xs font-bold py-1.5 px-3 rounded-xl transition-all"
                                    >
                                        <BriefcaseIcon class="h-3.5 w-3.5" />
                                        Vincular Servicio
                                    </button>
                                </div>

                                <!-- Sin servicio y sin servicios disponibles -->
                                <div v-else class="flex items-center gap-2 mt-1 text-gray-400 dark:text-zinc-500 italic text-sm">
                                    <BriefcaseIcon class="h-4 w-4 shrink-0" />
                                    Sin servicio específico
                                </div>
                            </div>

                        <div class="pt-2">
                            <Link 
                                v-if="ticket.client_id || ticket.creator?.client?.id"
                                :href="route('clients.show', ticket.client_id || ticket.creator?.client?.id)"
                                class="w-full bg-[#264ab3] text-white px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center justify-center hover:bg-[#193074] shadow-none"
                            >
                                Ver Ficha Completa
                                <ChevronRightIcon class="h-4 w-4 ml-1" />
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Action Card for Client -->
                <div v-if="isClient && ticket.status === 'Completados'" class="bg-green-50 dark:bg-green-900/20 border border-green-100 dark:border-green-900/40 rounded-3xl p-6 text-center">
                    <CheckCircleIcon class="h-10 w-10 text-green-500 dark:text-green-400 mx-auto mb-3" />
                    <h4 class="font-bold text-green-900 dark:text-green-300 text-lg">Ticket Finalizado</h4>
                    <p class="text-sm text-green-700 dark:text-green-400 mt-2">Este ticket ha sido resuelto y aceptado satisfactoriamente.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
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

:deep(.wysiwyg-content p) {
    margin-bottom: 0.5rem;
}
:deep(.wysiwyg-content p:last-child) {
    margin-bottom: 0;
}
:deep(.wysiwyg-content ul) {
    list-style-type: disc;
    padding-left: 1.25rem;
    margin-bottom: 0.5rem;
}
:deep(.wysiwyg-content ol) {
    list-style-type: decimal;
    padding-left: 1.25rem;
    margin-bottom: 0.5rem;
}

/* Main styles for the conversation bubbles content */
:deep(.wysiwyg-content), 
:deep(.wysiwyg-content *) {
    color: inherit !important;
}

/* User's own bubbles (Blue) - Should ALWAYS be white text */
.bg-blue-600 :deep(.wysiwyg-content),
.bg-blue-600 :deep(.wysiwyg-content *),
.dark .bg-blue-600 :deep(.wysiwyg-content),
.dark .bg-blue-600 :deep(.wysiwyg-content *) {
    color: white !important;
}

/* Dark mode generic content (Incoming/System bubbles) */
.dark .bg-white :deep(.wysiwyg-content),
.dark .bg-white :deep(.wysiwyg-content *),
.dark .bg-zinc-900 :deep(.wysiwyg-content),
.dark .bg-zinc-900 :deep(.wysiwyg-content *),
.dark .bg-gray-50 :deep(.wysiwyg-content),
.dark .bg-gray-50 :deep(.wysiwyg-content *),
.dark .bg-zinc-800 :deep(.wysiwyg-content),
.dark .bg-zinc-800 :deep(.wysiwyg-content *) {
    color: #e2e8f0 !important;
}

/* Ensure links remain visible and themed */
:deep(.wysiwyg-content a) {
    color: #3b82f6 !important;
    text-decoration: underline !important;
    font-weight: bold;
}

/* Links in blue bubbles need better contrast */
.bg-blue-600 :deep(.wysiwyg-content a),
.dark .bg-blue-600 :deep(.wysiwyg-content a) {
    color: #fbd38d !important;
}
</style>
