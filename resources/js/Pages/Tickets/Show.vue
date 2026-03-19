<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, nextTick } from 'vue';
import { 
    ChevronLeftIcon,
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
    PlusIcon
} from '@heroicons/vue/24/outline';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Wysiwyg from '@/Components/Wysiwyg.vue';

const props = defineProps({
    ticket: Object,
    assignableUsers: Array,
});

const page = usePage();

const isClient = computed(() => {
    return page.props.auth.user.is_client;
});

const isAdmin = computed(() => {
    return page.props.auth.user.is_admin;
});

const statusColors = {
    'Nuevos': 'bg-blue-100 text-blue-700 border-blue-200',
    'En Proceso': 'bg-yellow-100 text-yellow-700 border-yellow-200',
    'En Revisión': 'bg-purple-100 text-purple-700 border-purple-200',
    'Ajustes': 'bg-orange-100 text-orange-700 border-orange-200',
    'Completados': 'bg-green-100 text-green-700 border-green-200',
};

const priorityColors = {
    'Baja': 'bg-gray-100 text-gray-700',
    'Media': 'bg-blue-100 text-blue-700',
    'Alta': 'bg-orange-100 text-orange-700',
    'Urgente': 'bg-red-100 text-red-700',
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
const canAssign = computed(() => isAdmin.value && props.ticket?.status !== 'Completados');
const canProcess = computed(() => props.ticket?.status !== 'Completados' && !isClient.value && (props.ticket?.status === 'Nuevos' || props.ticket?.assigned_id === page.props.auth?.user?.id));
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
    const isAssigned = props.ticket?.assigned_id === page.props.auth?.user?.id;

    // Rule 1: In 'Ajustes', only the assigned user can close after 7 days of silence
    if (status === 'Ajustes') {
        return isAssigned && daysSinceLastResponse.value > 7;
    }

    // Rule 2: In 'En Revisión'
    if (status === 'En Revisión') {
        // Creator or Admin can close, BUT NOT if they are the ones assigned to the work
        if ((isCreator.value || isAdmin.value) && !isAssigned) return true;
        
        // Exception: Assigned user can close if creator silent for 7 days
        if (isAssigned && daysSinceLastResponse.value > 7) return true;
    }

    return false;
});

const canRequestAdjustments = computed(() => {
    const isAssigned = props.ticket?.assigned_id === page.props.auth?.user?.id;
    // Only Creator or Admin can request adjustments, and only when the ticket is in 'En Revisión'
    // Exclude the assigned user from requesting adjustments to their own work
    return props.ticket?.status === 'En Revisión' && (isCreator.value || isAdmin.value) && !isAssigned;
});

const today = new Date().toISOString().split('T')[0];

const takeTicket = () => {
    assignForm.assigned_id = page.props.auth?.user?.id;
    updateAssignee();
};

</script>

<template>
    <Head v-if="ticket" :title="ticket.title" />
    <Head v-else title="Cargando Ticket..." />

    <AuthenticatedLayout v-if="ticket">
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex items-center">
                    <Link :href="route('tickets.index')" class="mr-4 p-2 bg-white rounded-xl border border-gray-200 hover:border-[#264ab3] text-gray-500 hover:text-[#264ab3] transition-all">
                        <ChevronLeftIcon class="h-6 w-6" />
                    </Link>
                    <div>
                        <div class="flex items-center space-x-2 mb-1">
                            <span class="text-xs font-bold text-gray-400">TICKET #{{ ticket.id }}</span>
                            <span 
                                class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-tighter"
                                :class="priorityColors[ticket.priority]"
                            >
                                {{ ticket.priority }}
                            </span>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 leading-tight">{{ ticket.title }}</h2>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <div class="px-4 py-2 rounded-xl font-bold text-sm border-2" :class="statusColors[ticket.status]">
                        {{ ticket.status }}
                    </div>
                    
                    <!-- Admin Assign -->
                    <div v-if="canAssign" class="relative">
                        <select 
                            v-model="assignForm.assigned_id"
                            @change="updateAssignee"
                            class="text-sm border-gray-300 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-xl shadow-sm bg-blue-50 text-blue-700 font-bold"
                        >
                            <option :value="null">Asignar Ticket</option>
                            <option v-for="user in assignableUsers" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6 h-[calc(100vh-160px)] grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- LEFT: CONVERSATION -->
            <div class="lg:col-span-2 flex flex-col bg-white rounded-3xl border border-gray-200 shadow-xl shadow-gray-100 overflow-hidden">
                <!-- Chat Messages -->
                <div ref="messageContainer" class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar bg-gray-50/50">
                    <!-- Ticket Description as First Message -->
                    <div 
                        v-if="ticket" 
                        class="flex items-start"
                        :class="ticket.creator_id === $page.props.auth.user.id ? 'flex-row-reverse' : ''"
                    >
                        <div 
                            class="h-10 w-10 rounded-2xl flex items-center justify-center text-white shrink-0 shadow-sm font-bold border-2 border-white"
                            :class="[
                                ticket.creator_id === $page.props.auth.user.id ? 'ml-4 bg-[#264ab3]' : 'mr-4 bg-gray-200 !text-gray-500'
                            ]"
                        >
                            {{ ticket.creator?.name ? ticket.creator.name.charAt(0) : '?' }}
                        </div>
                        <div 
                            class="flex-1 max-w-2xl p-5 rounded-2xl shadow-sm border"
                            :class="[
                                ticket.creator_id === $page.props.auth.user.id 
                                ? 'bg-blue-600 text-white border-blue-700 rounded-tr-none' 
                                : 'bg-white text-gray-700 border-gray-100 rounded-tl-none'
                            ]"
                        >
                            <div 
                                class="flex justify-between items-center mb-2"
                                :class="ticket.creator_id === page.props.auth?.user?.id ? 'flex-row-reverse' : ''"
                            >
                                <span class="font-bold text-sm" :class="ticket.creator_id === page.props.auth?.user?.id ? 'text-white' : 'text-gray-900'">
                                    {{ ticket.creator?.name || 'Usuario' }}
                                </span>
                                <span class="text-[10px]" :class="ticket.creator_id === page.props.auth?.user?.id ? 'text-blue-200' : 'text-gray-400'">
                                    {{ formatDate(ticket.created_at, true) }}
                                </span>
                            </div>
                            <div class="text-sm whitespace-pre-wrap leading-relaxed wysiwyg-content" v-html="ticket.content || 'Sin descripción'">
                            </div>

                            <!-- Initial Attachments -->
                            <div v-if="ticket.attachments?.length > 0" class="mt-4 flex flex-wrap gap-2 overflow-hidden border-t pt-3" :class="ticket.creator_id === $page.props.auth.user.id ? 'border-blue-500' : 'border-gray-50'">
                                <a 
                                    v-for="file in ticket.attachments" 
                                    :key="file.id"
                                    :href="'/storage/' + file.file_path" 
                                    target="_blank"
                                    class="inline-flex items-center px-3 py-1.5 rounded-lg text-[10px] font-bold transition-all"
                                    :class="ticket.creator_id === $page.props.auth.user.id ? 'bg-blue-500 text-white hover:bg-blue-400' : 'bg-gray-100 text-[#264ab3] hover:bg-gray-200'"
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
                        <div v-if="msg.user_id === null" class="w-full max-w-lg bg-gray-50 border border-gray-200 text-gray-500 text-xs px-4 py-3 rounded-2xl text-center shadow-sm flex flex-col items-center">
                            <span class="font-bold mb-1 flex items-center text-gray-700">
                                🤖 Sistema
                            </span>
                            <span>{{ msg.message }}</span>
                            <span class="text-[10px] text-gray-400 mt-1 block">{{ formatDate(msg.created_at, true) }}</span>
                        </div>
                        
                        <!-- Regular User Message -->
                        <template v-else>
                            <div 
                                class="h-10 w-10 rounded-2xl flex items-center justify-center text-white shrink-0 shadow-sm font-bold border-2 border-white"
                                :class="[
                                    msg.user_id === $page.props.auth.user.id ? 'ml-4 bg-[#264ab3]' : 'mr-4 bg-purple-500'
                                ]"
                            >
                                {{ msg.user?.name ? msg.user.name.charAt(0) : '?' }}
                            </div>
                            <div 
                                class="flex-1 max-w-2xl p-5 rounded-2xl shadow-sm border"
                                :class="[
                                    msg.user_id === $page.props.auth.user.id 
                                    ? 'bg-blue-600 text-white border-blue-700 rounded-tr-none' 
                                    : 'bg-white text-gray-700 border-gray-100 rounded-tl-none'
                                ]"
                            >
                                <div 
                                    class="flex justify-between items-center mb-2"
                                    :class="msg.user_id === page.props.auth?.user?.id ? 'flex-row-reverse' : ''"
                                >
                                    <span class="font-bold text-sm" :class="msg.user_id === page.props.auth?.user?.id ? 'text-white' : 'text-gray-900'">
                                        {{ msg.user?.name || 'Usuario' }}
                                    </span>
                                    <span class="text-[10px]" :class="msg.user_id === page.props.auth?.user?.id ? 'text-blue-200' : 'text-gray-400'">
                                        {{ formatDate(msg.created_at, true) }}
                                    </span>
                                </div>
                                <div class="text-sm whitespace-pre-wrap leading-relaxed wysiwyg-content" v-html="msg.message">
                                </div>
                                
                                <!-- File Attachment link -->
                                <div v-if="msg.file_path" class="mt-4 pt-3 border-t overflow-hidden" :class="msg.user_id === $page.props.auth.user.id ? 'border-blue-500' : 'border-gray-50'">
                                    <a 
                                        :href="'/storage/' + msg.file_path" 
                                        target="_blank"
                                        class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold transition-all"
                                        :class="msg.user_id === $page.props.auth.user.id ? 'bg-blue-500 text-white hover:bg-blue-400' : 'bg-gray-100 text-[#264ab3] hover:bg-gray-200'"
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
                <div v-if="canComment" class="p-4 bg-white border-t border-gray-100 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.02)]">
                    <form @submit.prevent="submitMessage('')" class="flex flex-col space-y-3">
                        <div class="flex flex-col bg-gray-50 rounded-2xl border border-gray-200 focus-within:border-[#264ab3] focus-within:ring-2 focus-within:ring-blue-100 transition-all overflow-hidden">
                            <Wysiwyg 
                                v-model="messageForm.message"
                                placeholder="Escribe un mensaje aquí..."
                            />
                            
                            <div class="flex items-center justify-between p-2 bg-white/50 border-t border-gray-100">
                                <label class="cursor-pointer p-2 text-gray-400 hover:text-[#264ab3] hover:bg-blue-50 rounded-xl transition-all relative overflow-hidden">
                                    <input type="file" ref="fileInput" @change="handleFileChange" class="absolute inset-0 opacity-0 cursor-pointer" />
                                    <PaperClipIcon class="h-5 w-5" />
                                </label>
                                <div class="flex items-center space-x-2">
                                    <div v-if="messageForm.file" class="text-[10px] text-[#264ab3] font-bold italic animate-pulse">
                                        Archivo listo para enviar
                                    </div>
                                    <button 
                                        type="submit"
                                        class="bg-[#264ab3] text-white p-3 rounded-xl shadow-lg shadow-blue-200 hover:bg-[#193074] transition-all disabled:opacity-50"
                                        :disabled="messageForm.processing || (!messageForm.message || messageForm.message === '<p><br></p>')"
                                    >
                                        <PaperAirplaneIcon class="h-5 w-5 rotate-[-45deg]" />
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons based on status -->
                        <div class="flex flex-wrap gap-2 pt-2">
                            <!-- User/Assignee sends and changes to "En Revisión" -->
                            <button 
                                v-if="canReview"
                                type="button"
                                @click="submitMessage('En Revisión')"
                                class="text-xs font-bold bg-purple-600 text-white px-4 py-2 rounded-xl border border-purple-700 hover:bg-purple-700 transition-all shadow-lg shadow-purple-200 flex items-center"
                                :disabled="messageForm.processing || !messageForm.message"
                            >
                                <CheckCircleIcon class="h-4 w-4 mr-2" />
                                Enviar y pasar a Revisión
                            </button>

                            <!-- Client/Admin sends and asks for "Ajustes" -->
                            <button 
                                v-if="canRequestAdjustments"
                                type="button"
                                @click="submitMessage('Ajustes')"
                                class="text-xs font-bold bg-orange-500 text-white px-4 py-2 rounded-xl border border-orange-600 hover:bg-orange-600 transition-all shadow-lg shadow-orange-100 flex items-center"
                                :disabled="messageForm.processing || !messageForm.message"
                            >
                                <AdjustmentsHorizontalIcon class="h-4 w-4 mr-2" />
                                Solicitar Ajustes
                            </button>

                            <!-- Close Ticket Button -->
                            <button 
                                v-if="canClose"
                                type="button"
                                @click="updateStatus('Completados')"
                                class="text-xs font-bold bg-green-600 text-white px-4 py-2 rounded-xl border border-green-700 hover:bg-green-700 transition-all shadow-lg shadow-green-200 flex items-center"
                            >
                                <CheckCircleIcon class="h-4 w-4 mr-2" />
                                Finalizar Ticket
                            </button>
                        </div>
                    </form>
                </div>
                <div v-else class="p-8 bg-gray-50 border-t border-gray-100 text-center">
                    <p v-if="ticket.status === 'Completados'" class="text-sm text-gray-500 italic font-bold">
                        Esta conversación ha finalizado.
                    </p>
                    <p v-else class="text-sm text-gray-500 italic">
                        Solo el administrador y el usuario asignado pueden participar en esta conversación.
                    </p>
                </div>
            </div>

            <!-- RIGHT: SIDEBAR INFO -->
            <div class="space-y-6">
                <!-- Info Card -->
                <div class="bg-white rounded-3xl border border-gray-200 shadow-xl shadow-gray-100 p-6">
                    <h3 class="font-bold text-gray-900 border-b border-gray-100 pb-4 mb-4 flex items-center uppercase text-xs tracking-widest text-[#264ab3]">
                        <ClockIcon class="h-4 w-4 mr-2" />
                        Detalles del Ticket
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <span class="text-xs text-gray-400 block mb-1">Creador:</span>
                            <div class="flex items-center">
                                <span class="h-6 w-6 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center text-[10px] font-bold mr-2">
                                    {{ ticket.creator.name.charAt(0) }}
                                </span>
                                <span class="text-sm font-bold text-gray-700">{{ ticket.creator.name }}</span>
                            </div>
                        </div>

                        <div>
                            <span class="text-xs text-gray-400 block mb-1">Asignado a:</span>
                            <div v-if="ticket.assigned" class="flex items-center">
                                <span class="h-6 w-6 rounded-full bg-[#264ab3] text-white flex items-center justify-center text-[10px] font-bold mr-2">
                                    {{ ticket.assigned?.name ? ticket.assigned.name.charAt(0) : '?' }}
                                </span>
                                <span class="text-sm font-bold text-gray-700">{{ ticket.assigned?.name }}</span>
                            </div>
                            <div v-else-if="canTake" class="pt-1">
                                <button 
                                    @click="takeTicket"
                                    class="w-full bg-blue-50 text-[#264ab3] border border-blue-100 hover:bg-[#264ab3] hover:text-white px-3 py-2 rounded-xl text-xs font-bold transition-all shadow-sm flex items-center justify-center uppercase tracking-wider"
                                >
                                    <PlusIcon class="h-3.5 w-3.5 mr-1" />
                                    Tomar este ticket
                                </button>
                            </div>
                            <div v-else class="text-sm text-gray-400 italic">No asignado</div>
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
                            <span class="text-xs text-gray-400 block mb-1">Último cambio de estado:</span>
                            <div v-if="ticket.status_updated_at" class="flex items-center text-gray-700 font-bold text-sm">
                                <ArrowPathIcon class="h-4 w-4 mr-2 text-gray-400" />
                                {{ formatDate(ticket.status_updated_at, true) }}
                            </div>
                            <div v-else class="text-sm text-gray-400 italic">No registrado</div>
                        </div>

                        <!-- Date Picker for Assignee -->
                        <div v-if="canProcess" class="pt-4 border-t border-gray-50">
                            <InputLabel for="due_date" value="Establecer Entrega" class="text-[10px]" />
                            <div class="flex mt-1 space-x-2">
                                <TextInput 
                                    id="due_date" 
                                    type="date" 
                                    class="text-xs flex-1 rounded-xl" 
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
                                    class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1 text-xs font-bold rounded-xl transition-all"
                                >
                                    Actualizar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Card for Client -->
                <div v-if="isClient && ticket.status === 'Completados'" class="bg-green-50 border border-green-100 rounded-3xl p-6 text-center">
                    <CheckCircleIcon class="h-10 w-10 text-green-500 mx-auto mb-3" />
                    <h4 class="font-bold text-green-900 text-lg">Ticket Finalizado</h4>
                    <p class="text-sm text-green-700 mt-2">Este ticket ha sido resuelto y aceptado satisfactoriamente.</p>
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
</style>
