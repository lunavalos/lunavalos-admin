<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
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
    PencilSquareIcon,
    PhotoIcon,
    ArchiveBoxIcon,
    EyeIcon,
    EyeSlashIcon,
    LightBulbIcon,
    LinkIcon,
    ClipboardDocumentCheckIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Wysiwyg from '@/Components/Wysiwyg.vue';
import ClientAssetsManager from '@/Components/ClientAssetsManager.vue';
import TicketCanvas from '@/Components/TicketCanvas.vue';

const props = defineProps({
    ticket: Object,
    assignableUsers: Array,
    clientServices: Array,
    billingCycles: { type: Array, default: () => [] },
});

const page = usePage();

const isClient = computed(() => {
    return page.props.auth.user.is_client;
});

const isAdmin = computed(() => {
    return page.props.auth.user.is_admin;
});

// Tabs: conversation | documents | canvas
const activeTab = ref('conversation');

// Resolve the client object (from ticket.client or ticket.creator.client) with its assets
const clientForCanvas = computed(() => props.ticket?.client || props.ticket?.creator?.client || null);
const canvasItems = computed(() => props.ticket?.canvas_items || []);

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

const scrollToBottom = (behavior = 'instant') => {
    nextTick(() => {
        if (messageContainer.value) {
            messageContainer.value.scrollTo({
                top: messageContainer.value.scrollHeight,
                behavior,
            });
        }
    });
};

// ── Live chat ──────────────────────────────────────────────────────────────
const liveMessages = ref([]); // messages received via WebSocket
const isConnected = ref(false);

/**
 * Generates a short "pop" notification sound using the Web Audio API.
 * No external file needed.
 */
const playPop = () => {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.08);
        gain.gain.setValueAtTime(0.25, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.15);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.15);
    } catch (_) {
        // AudioContext not available — fail silently
    }
};

onMounted(() => {
    // Wait for all messages to be painted before scrolling
    scrollToBottom('instant');

    // Subscribe to the public ticket channel via Laravel Echo / Reverb
    if (window.Echo) {
        window.Echo.channel(`ticket.${props.ticket.id}`)
            .subscribed(() => {
                isConnected.value = true;
            })
            .listen('.message.sent', (payload) => {
                // Avoid duplicating messages the current user just sent
                const alreadyExists =
                    props.ticket.messages.some(m => m.id === payload.id) ||
                    liveMessages.value.some(m => m.id === payload.id);

                if (!alreadyExists) {
                    liveMessages.value.push(payload);
                    playPop();
                    scrollToBottom('instant');
                }
            });
    }
});

// Scroll when Inertia refreshes props.ticket.messages (after sending your own message)
watch(
    () => props.ticket.messages?.length,
    () => scrollToBottom('smooth'),
);

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leaveChannel(`ticket.${props.ticket.id}`);
    }
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
            scrollToBottom('instant');
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

const cycleForm = useForm({
    billing_cycle_id:      props.ticket.billing_cycle_id ?? null,
    deliverable_credit_id: props.ticket.deliverable_credit_id ?? null,
});

const cycleCredits = computed(() => {
    if (!cycleForm.billing_cycle_id) return [];
    return props.billingCycles?.find(c => c.id === cycleForm.billing_cycle_id)?.credits ?? [];
});

watch(() => cycleForm.billing_cycle_id, () => {
    cycleForm.deliverable_credit_id = null;
});

const updateCycle = () => {
    cycleForm.post(route('tickets.updateCycle', props.ticket.id), {
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

const backHref = computed(() => {
    if (props.ticket.source_type === 'recurring' && props.ticket.client_id) {
        return route('recurring.clients.show', props.ticket.client_id);
    }
    return route('tickets.index');
});

const deleteTicket = () => {
    if (confirm('¿Mover este ticket a la papelera?')) {
        router.delete(route('tickets.destroy', props.ticket.id));
    }
};

const toggleArchive = () => {
    const action = props.ticket.is_archived ? 'desarchivar' : 'archivar';
    if (confirm(`¿Estás seguro de ${action} este ticket?`)) {
        router.post(route('tickets.toggleArchive', props.ticket.id), {}, {
            preserveScroll: true,
        });
    }
};

const toggleClientVisibility = () => {
    router.post(route('tickets.toggleClientVisibility', props.ticket.id), {}, {
        preserveScroll: true,
    });
};

const publicUrl = computed(() => {
    if (!props.ticket.public_token) return null;
    return window.location.origin + '/tickets/p/' + props.ticket.public_token;
});

const copyingLink = ref(false);

const generateOrCopyPublicLink = () => {
    if (props.ticket.public_token) {
        navigator.clipboard.writeText(publicUrl.value);
        copyingLink.value = true;
        setTimeout(() => { copyingLink.value = false; }, 2000);
    } else {
        router.post(route('tickets.generatePublicLink', props.ticket.id), {}, {
            preserveScroll: true,
        });
    }
};

const revokePublicLink = () => {
    if (confirm('¿Revocar el enlace público? Dejará de funcionar de inmediato.')) {
        router.delete(route('tickets.revokePublicLink', props.ticket.id), {
            preserveScroll: true,
        });
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

/**
 * Returns the display name for a given user object.
 * If the current viewer is a Client, internal staff names are masked as "LunAvalos".
 * Internal users always see real names.
 */
const displayName = (user) => {
    if (!user) return 'Usuario';
    // If the viewer is a client AND this message author is NOT a client → mask name
    if (isClient.value && !user.is_client) return 'LunAvalos';
    return user.name || 'Usuario';
};

const displayInitial = (user) => {
    return displayName(user).charAt(0).toUpperCase();
};

// Compute elapsed/total work time
const formatDuration = (start, end) => {
    if (!start) return null;
    const from = new Date(start);
    const to = end ? new Date(end) : new Date();
    const diffMs = to - from;
    const totalMinutes = Math.floor(diffMs / 60000);
    const days    = Math.floor(totalMinutes / 1440);
    const hours   = Math.floor((totalMinutes % 1440) / 60);
    const minutes = totalMinutes % 60;

    if (days > 0 && hours > 0) return `${days}d ${hours}h ${minutes}m`;
    if (days > 0)              return `${days}d ${minutes}m`;
    if (hours > 0)             return `${hours}h ${minutes}m`;
    return `${minutes}m`;
};

// System messages that represent status changes (logged in conversation)
const statusLogMessages = computed(() => {
    const all = [
        ...(props.ticket?.messages ?? []),
        ...liveMessages.value,
    ];
    // deduplicate by id
    const seen = new Set();
    return all
        .filter(m => {
            if (m.user_id !== null) return false;
            if (seen.has(m.id)) return false;
            seen.add(m.id);
            return true;
        })
        .sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
});

// All messages for the conversation (props + live WebSocket messages, deduped)
const allMessages = computed(() => {
    const combined = [
        ...(props.ticket?.messages ?? []),
        ...liveMessages.value,
    ];
    const seen = new Set();
    return combined
        .filter(m => {
            if (seen.has(m.id)) return false;
            seen.add(m.id);
            return true;
        })
        .sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
});

// ── Edit message ─────────────────────────────────────────────────────────────
const editingMessageId = ref(null);
const editForm = useForm({ message: '' });

/**
 * The ID of the last real (non-system) message across all combined messages.
 * Used to gate non-admin staff edit access.
 */
const lastUserMessageId = computed(() => {
    const userMsgs = allMessages.value.filter(m => m.user_id !== null);
    if (!userMsgs.length) return null;
    return userMsgs[userMsgs.length - 1].id;
});

/**
 * Returns true if the current user is allowed to edit a given message.
 */
const canEditMessage = (msg) => {
    if (!msg.user_id) return false;                        // System messages: never
    if (isClient.value) return false;                      // Clients: never
    const authorIsClient = msg.user?.is_client;           // Client messages: nobody can edit
    if (authorIsClient) return false;
    if (isAdmin.value) return true;                        // Admins: any non-client message
    // Staff: only own message AND only if it is the very last one
    return msg.user_id === page.props.auth?.user?.id && msg.id === lastUserMessageId.value;
};

const startEdit = (msg) => {
    editingMessageId.value = msg.id;
    editForm.message = msg.message;
};

const cancelEdit = () => {
    editingMessageId.value = null;
    editForm.reset();
};

const saveEdit = (msg) => {
    editForm.put(route('tickets.messages.update', msg.id), {
        preserveScroll: true,
        onSuccess: () => {
            editingMessageId.value = null;
            editForm.reset();
        },
    });
};
</script>

<template>
    <Head v-if="ticket" :title="ticket.title" />
    <Head v-else title="Cargando Ticket..." />

    <AuthenticatedLayout v-if="ticket">
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-2">
                <div class="flex items-center min-w-0">
                    <Link :href="backHref" class="mr-2 p-1.5 bg-white dark:bg-zinc-900 rounded-lg border border-gray-200 dark:border-zinc-800 hover:border-[#264ab3] text-gray-500 dark:text-zinc-400 hover:text-[#264ab3] transition-all">
                        <ChevronLeftIcon class="h-4 w-4" />
                    </Link>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                            <span class="text-[10px] font-bold text-gray-400 dark:text-zinc-500 font-mono">#{{ ticket.id }}</span>
                            <span
                                v-if="isConnected"
                                class="inline-flex items-center gap-1 text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300"
                            >
                                <span class="relative flex h-1.5 w-1.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                                </span>
                                LIVE
                            </span>
                            <span
                                class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase tracking-tighter"
                                :class="priorityColors[ticket.priority]"
                            >
                                {{ ticket.priority }}
                            </span>
                            <span
                                v-if="ticket.client?.business_name || ticket.creator?.client?.business_name"
                                class="text-[10px] text-gray-500 dark:text-zinc-400 truncate"
                            >
                                · {{ ticket.client?.business_name || ticket.creator?.client?.business_name }}
                            </span>
                        </div>
                        <h2 class="text-base md:text-lg font-bold text-gray-900 dark:text-gray-100 leading-tight truncate">{{ ticket.title }}</h2>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <select
                        v-if="!isClient"
                        @change="updateStatus($event.target.value)"
                        class="px-2.5 py-1 rounded-lg font-bold text-xs border focus:ring-0 cursor-pointer transition-all appearance-none"
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
                    <div v-else class="px-2.5 py-1 rounded-lg font-bold text-xs border" :class="statusColors[ticket.status]">
                        {{ ticket.status }}
                    </div>

                    <select
                        v-if="canAssign"
                        v-model="assignForm.assigned_id"
                        @change="updateAssignee"
                        class="text-xs border-gray-300 dark:border-zinc-800 rounded-lg bg-blue-50 dark:bg-zinc-950 text-blue-700 dark:text-blue-400 font-bold py-1 max-w-[160px]"
                    >
                        <option :value="null">Asignar…</option>
                        <option v-for="user in assignableUsers" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </option>
                    </select>

                    <button
                        v-if="canDelete"
                        @click="deleteTicket"
                        class="p-1.5 bg-red-50 dark:bg-rose-900/20 text-red-600 dark:text-rose-400 rounded-lg border border-red-100 dark:border-rose-900/40 hover:bg-red-600 hover:text-white transition-all animate-all"
                        title="Eliminar Ticket"
                    >
                        <TrashIcon class="h-4 w-4" />
                    </button>

                    <!-- Archive/Unarchive Button -->
                    <button
                        v-if="!isClient && (props.ticket.status === 'Completados' || props.ticket.is_archived)"
                        @click="toggleArchive"
                        class="p-1.5 bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 rounded-lg border border-blue-100 dark:border-blue-900/40 hover:bg-[#264ab3] hover:text-white transition-all flex items-center gap-1.5 font-bold text-xs"
                        :title="props.ticket.is_archived ? 'Desarchivar Ticket' : 'Archivar Ticket'"
                    >
                        <ArchiveBoxIcon class="h-4 w-4" />
                        <span>{{ props.ticket.is_archived ? 'Desarchivar' : 'Archivar' }}</span>
                    </button>

                    <!-- Client Visibility Toggle -->
                    <button
                        v-if="!isClient && props.ticket.source_type === 'support'"
                        @click="toggleClientVisibility"
                        :class="[
                            'p-1.5 rounded-lg border transition-all flex items-center gap-1.5 font-bold text-xs',
                            props.ticket.visible_to_client
                                ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-900/40 hover:bg-emerald-600 hover:text-white'
                                : 'bg-gray-50 dark:bg-zinc-800 text-gray-500 dark:text-zinc-400 border-gray-200 dark:border-zinc-700 hover:bg-gray-200 dark:hover:bg-zinc-700'
                        ]"
                        :title="props.ticket.visible_to_client ? 'Ocultar al cliente' : 'Hacer visible al cliente'"
                    >
                        <EyeIcon v-if="props.ticket.visible_to_client" class="h-4 w-4" />
                        <EyeSlashIcon v-else class="h-4 w-4" />
                        <span>{{ props.ticket.visible_to_client ? 'Visible cliente' : 'Oculto cliente' }}</span>
                    </button>

                    <!-- Public Link -->
                    <template v-if="!isClient">
                        <button
                            @click="generateOrCopyPublicLink"
                            :class="[
                                'p-1.5 rounded-lg border transition-all flex items-center gap-1.5 font-bold text-xs',
                                props.ticket.public_token
                                    ? 'bg-violet-50 dark:bg-violet-900/20 text-violet-700 dark:text-violet-400 border-violet-200 dark:border-violet-900/40 hover:bg-violet-600 hover:text-white'
                                    : 'bg-gray-50 dark:bg-zinc-800 text-gray-500 dark:text-zinc-400 border-gray-200 dark:border-zinc-700 hover:bg-gray-200 dark:hover:bg-zinc-700'
                            ]"
                            :title="props.ticket.public_token ? 'Copiar enlace público' : 'Generar enlace público'"
                        >
                            <ClipboardDocumentCheckIcon v-if="copyingLink" class="h-4 w-4" />
                            <LinkIcon v-else class="h-4 w-4" />
                            <span>{{ copyingLink ? '¡Copiado!' : (props.ticket.public_token ? 'Copiar enlace' : 'Enlace público') }}</span>
                        </button>
                        <button
                            v-if="props.ticket.public_token"
                            @click="revokePublicLink"
                            class="p-1.5 rounded-lg border border-red-100 dark:border-rose-900/40 bg-red-50 dark:bg-rose-900/20 text-red-500 dark:text-rose-400 hover:bg-red-500 hover:text-white transition-all flex items-center gap-1.5 font-bold text-xs"
                            title="Revocar enlace público"
                        >
                            <XCircleIcon class="h-4 w-4" />
                            <span>Revocar</span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Tabs nav -->
            <div class="mt-3 border-b border-gray-200 dark:border-zinc-800 flex gap-1">
                <button @click="activeTab = 'conversation'"
                    :class="[
                        'px-3 py-1.5 text-xs font-bold border-b-2 transition-all inline-flex items-center gap-1.5',
                        activeTab === 'conversation'
                            ? 'border-[#264ab3] text-[#264ab3] dark:text-blue-400'
                            : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-zinc-400 dark:hover:text-zinc-200'
                    ]">
                    <ChatBubbleLeftRightIcon class="h-4 w-4" /> Conversación
                </button>
                <button @click="activeTab = 'canvas'"
                    :class="[
                        'px-3 py-1.5 text-xs font-bold border-b-2 transition-all inline-flex items-center gap-1.5',
                        activeTab === 'canvas'
                            ? 'border-[#264ab3] text-[#264ab3] dark:text-blue-400'
                            : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-zinc-400 dark:hover:text-zinc-200'
                    ]">
                    <PhotoIcon class="h-4 w-4" /> Lienzo
                    <span v-if="canvasItems.length"
                        class="rounded-full bg-[#264ab3] text-white px-2 text-[10px] font-black shadow ring-2 ring-blue-200 dark:ring-blue-900/50 animate-pulse">
                        {{ canvasItems.length }}
                    </span>
                </button>
                <button v-if="ticket.client || ticket.creator?.client"
                    @click="activeTab = 'documents'"
                    :class="[
                        'px-3 py-1.5 text-xs font-bold border-b-2 transition-all inline-flex items-center gap-1.5',
                        activeTab === 'documents'
                            ? 'border-[#264ab3] text-[#264ab3] dark:text-blue-400'
                            : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-zinc-400 dark:hover:text-zinc-200'
                    ]">
                    <PaperClipIcon class="h-4 w-4" /> Documentos del cliente
                    <span v-if="clientForCanvas?.assets?.length"
                        class="rounded-full bg-gray-100 dark:bg-zinc-700 px-1.5 text-[9px] text-gray-600 dark:text-zinc-300">
                        {{ clientForCanvas.assets.length }}
                    </span>
                </button>
            </div>
        </template>

        <div class="py-6 grid grid-cols-1 lg:grid-cols-3 gap-6 lg:h-[calc(100vh-200px)]"
             v-show="activeTab === 'conversation'">
            <!-- LEFT: CONVERSATION -->
            <div class="lg:col-span-2 flex flex-col bg-white dark:bg-zinc-900 rounded-3xl border border-gray-200 dark:border-zinc-800 overflow-hidden min-h-[70vh] lg:min-h-0">
                <!-- Chat Messages -->
                <div ref="messageContainer" class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar bg-gray-50/50 dark:bg-zinc-950/50">
                    <!-- Aviso de Lienzo con contenido -->
                    <button v-if="canvasItems.length"
                        type="button"
                        @click="activeTab = 'canvas'"
                        class="w-full flex items-center gap-3 p-3 rounded-xl border border-blue-200 dark:border-blue-900/50 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-all text-left group">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#264ab3] text-white shrink-0 shadow">
                            <PhotoIcon class="h-5 w-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-black text-[#264ab3] dark:text-blue-300">
                                {{ canvasItems.length }} {{ canvasItems.length === 1 ? 'pieza disponible' : 'piezas disponibles' }} en el lienzo
                            </p>
                            <p class="text-[11px] text-gray-600 dark:text-zinc-300 mt-0.5">
                                {{ isClient ? 'Revisa las propuestas, deja comentarios con pines y aprueba o solicita cambios.' : 'Revisa el storyboard, comenta con pines o ajusta el estado de aprobación.' }}
                            </p>
                        </div>
                        <span class="text-[11px] font-bold text-[#264ab3] dark:text-blue-300 group-hover:underline">Abrir lienzo →</span>
                    </button>
                    <!-- Ticket Description as First Message -->
                    <div 
                        v-if="ticket" 
                        class="flex items-start"
                        :class="ticket.creator_id === $page.props.auth.user.id ? 'flex-row-reverse' : ''"
                    >
                        <!-- Anonymous LunAvalos avatar (client viewer + internal author) -->
                        <img
                            v-if="isClient && !ticket.creator?.is_client"
                            src="/favicon.png"
                            alt="LunAvalos"
                            class="h-10 w-10 rounded-2xl object-cover shrink-0 shadow-sm border-2 border-white dark:border-zinc-900 bg-white p-1"
                            :class="ticket.creator_id === $page.props.auth.user.id ? 'ml-4' : 'mr-4'"
                        />
                        <!-- Real photo avatar -->
                        <img v-else-if="ticket.creator?.profile_photo_url"
                            :src="ticket.creator.profile_photo_url" 
                            :alt="displayName(ticket.creator)"
                            width="40"
                            height="40"
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
                            {{ displayInitial(ticket.creator) }}
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
                                    {{ displayName(ticket.creator) }}
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
                        v-for="msg in allMessages"
                        :key="msg.id"
                        v-show="msg.user_id !== null || !isClient"
                        class="flex items-start"
                        :class="msg.user_id === $page.props.auth.user.id ? 'flex-row-reverse' : (msg.user_id === null ? 'justify-center my-4' : '')"
                    >
                        <!-- System message (Bot) — hidden from clients -->
                        <div v-if="msg.user_id === null && !isClient" class="w-full max-w-lg bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-600 text-gray-500 dark:text-zinc-300 text-xs px-4 py-3 rounded-2xl text-center shadow-sm flex flex-col items-center">
                            <span class="font-bold mb-1 flex items-center text-gray-700 dark:text-gray-100">
                                🤖 Sistema
                            </span>
                            <span v-html="msg.message"></span>
                            <span class="text-[10px] text-gray-400 dark:text-zinc-500 mt-1 block">{{ formatDate(msg.created_at, true) }}</span>
                        </div>
                        
                        <!-- Regular User Message -->
                        <template v-else>
                            <!-- Anonymous LunAvalos avatar (client viewer + internal author) -->
                            <img
                                v-if="isClient && !msg.user?.is_client"
                                src="/favicon.png"
                                alt="LunAvalos"
                                class="h-10 w-10 rounded-2xl object-cover shrink-0 shadow-sm border-2 border-white dark:border-zinc-900 bg-white p-1"
                                :class="msg.user_id === $page.props.auth.user.id ? 'ml-4' : 'mr-4'"
                            />
                            <!-- Real photo avatar -->
                            <img v-else-if="msg.user?.profile_photo_url"
                                :src="msg.user.profile_photo_url" 
                                :alt="displayName(msg.user)"
                                width="40"
                                height="40"
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
                                {{ displayInitial(msg.user) }}
                            </div>
                            <div 
                                class="flex-1 max-w-2xl p-5 rounded-2xl border"
                                :class="[
                                    msg.user_id === $page.props.auth.user.id 
                                    ? 'bg-blue-600 text-white border-blue-700 rounded-tr-none' 
                                    : 'bg-white dark:bg-zinc-900 text-gray-700 dark:text-gray-200 border-gray-100 dark:border-zinc-800 rounded-tl-none'
                                ]"
                            >
                                <!-- Header: author + date + edit button -->
                                <div 
                                    class="flex justify-between items-center mb-2"
                                    :class="msg.user_id === page.props.auth?.user?.id ? 'flex-row-reverse' : ''"
                                >
                                    <span class="font-bold text-sm" :class="msg.user_id === page.props.auth?.user?.id ? 'text-white' : 'text-gray-900 dark:text-gray-100'">
                                        {{ displayName(msg.user) }}
                                    </span>
                                    <div class="flex items-center gap-2" :class="msg.user_id === page.props.auth?.user?.id ? 'flex-row-reverse' : ''">
                                        <span class="text-[10px]" :class="msg.user_id === page.props.auth?.user?.id ? 'text-blue-200' : 'text-gray-400 dark:text-zinc-500'">
                                            {{ formatDate(msg.created_at, true) }}
                                        </span>
                                        <!-- Edit button (only shown when not already editing this message) -->
                                        <button
                                            v-if="canEditMessage(msg) && editingMessageId !== msg.id"
                                            @click="startEdit(msg)"
                                            :title="'Editar mensaje'"
                                            class="p-1 rounded-lg transition-all opacity-60 hover:opacity-100"
                                            :class="msg.user_id === page.props.auth?.user?.id ? 'hover:bg-blue-500 text-white' : 'hover:bg-gray-100 dark:hover:bg-zinc-700 text-gray-400 dark:text-zinc-400'"
                                        >
                                            <PencilSquareIcon class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <!-- READ MODE -->
                                <template v-if="editingMessageId !== msg.id">
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
                                </template>

                                <!-- EDIT MODE -->
                                <template v-else>
                                    <div
                                        class="rounded-xl overflow-hidden border"
                                        :class="msg.user_id === $page.props.auth.user.id ? 'border-blue-400 bg-blue-500/30' : 'border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-950'"
                                    >
                                        <Wysiwyg
                                            v-model="editForm.message"
                                            placeholder="Editar mensaje..."
                                        />
                                    </div>
                                    <div class="flex items-center gap-2 mt-3" :class="msg.user_id === $page.props.auth.user.id ? 'justify-end' : 'justify-end'">
                                        <button
                                            type="button"
                                            @click="cancelEdit"
                                            class="text-xs font-bold px-3 py-1.5 rounded-lg transition-all"
                                            :class="msg.user_id === $page.props.auth.user.id ? 'bg-blue-500 hover:bg-blue-400 text-white' : 'bg-gray-100 dark:bg-zinc-700 hover:bg-gray-200 dark:hover:bg-zinc-600 text-gray-600 dark:text-gray-300'"
                                        >
                                            Cancelar
                                        </button>
                                        <button
                                            type="button"
                                            @click="saveEdit(msg)"
                                            :disabled="editForm.processing || !editForm.message || editForm.message === '<p><br></p>'"
                                            class="text-xs font-bold px-3 py-1.5 rounded-lg transition-all disabled:opacity-50 flex items-center gap-1"
                                            :class="msg.user_id === $page.props.auth.user.id ? 'bg-white text-blue-700 hover:bg-blue-50' : 'bg-[#264ab3] hover:bg-[#193074] text-white'"
                                        >
                                            <CheckCircleIcon class="h-3.5 w-3.5" />
                                            Guardar
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Chat Input Area -->
                <div v-if="canComment" class="p-4 bg-white dark:bg-zinc-900 border-t border-gray-100 dark:border-zinc-800">
                    <form @submit.prevent="submitMessage('')" class="flex flex-col space-y-3">
                        <div class="flex flex-col bg-gray-50 dark:bg-zinc-950 rounded-2xl border border-gray-200 dark:border-zinc-800 focus-within:border-[#264ab3] dark:focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-100 dark:focus-within:ring-blue-900/30 transition-all overflow-hidden">
                            <div class="max-h-64 overflow-y-auto">
                                <Wysiwyg 
                                    v-model="messageForm.message"
                                    placeholder="Escribe un mensaje aquí..."
                                />
                            </div>
                            
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
                <div v-if="!isClient" class="bg-white dark:bg-zinc-900 rounded-3xl border border-gray-200 dark:border-zinc-800 p-6">
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
                                    width="24"
                                    height="24"
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
                                    width="24"
                                    height="24"
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

                            <!-- Ciclo de facturación (solo para tickets de soporte con ciclos disponibles) -->
                            <div v-if="!isClient && ticket.source_type === 'support' && billingCycles.length > 0" class="mt-3">
                                <span class="text-[10px] text-gray-400 dark:text-zinc-500 font-bold uppercase block mb-1">Ciclo de Facturación:</span>

                                <!-- Ya vinculado -->
                                <div v-if="ticket.billing_cycle_id && !cycleForm.isDirty" class="space-y-1 mt-1">
                                    <div class="flex items-center gap-2">
                                        <ArrowPathIcon class="h-4 w-4 text-[#264ab3] dark:text-blue-400 shrink-0" />
                                        <span class="text-sm font-bold text-gray-700 dark:text-gray-200">
                                            {{ billingCycles.find(c => c.id === ticket.billing_cycle_id)?.label ?? 'Ciclo vinculado' }}
                                        </span>
                                        <button type="button" @click="cycleForm.billing_cycle_id = null; cycleForm.deliverable_credit_id = null; updateCycle()"
                                            class="ml-auto text-[10px] text-red-500 hover:text-red-700">Desvincular</button>
                                    </div>
                                    <div v-if="ticket.deliverable_credit_id" class="flex items-center gap-1.5 pl-6">
                                        <span class="text-xs text-gray-500 dark:text-zinc-400">Entregable:</span>
                                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">
                                            {{ billingCycles.find(c => c.id === ticket.billing_cycle_id)?.credits?.find(cr => cr.id === ticket.deliverable_credit_id)?.name ?? '—' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Selector -->
                                <div v-else class="mt-1 space-y-2">
                                    <select
                                        v-model="cycleForm.billing_cycle_id"
                                        class="w-full text-sm border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 text-gray-700 dark:text-gray-300 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-xl shadow-sm py-1.5"
                                    >
                                        <option :value="null">Sin ciclo</option>
                                        <option v-for="c in billingCycles" :key="c.id" :value="c.id">
                                            {{ c.label }}
                                            <template v-if="c.status === 'active'"> · Activo</template>
                                            <template v-else-if="c.status === 'locked'"> · Cerrado</template>
                                        </option>
                                    </select>
                                    <!-- Selector de crédito/entregable cuando hay ciclo seleccionado -->
                                    <select
                                        v-if="cycleForm.billing_cycle_id && cycleCredits.length > 0"
                                        v-model="cycleForm.deliverable_credit_id"
                                        class="w-full text-sm border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 text-gray-700 dark:text-gray-300 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-xl shadow-sm py-1.5"
                                    >
                                        <option :value="null">Sin entregable específico</option>
                                        <option v-for="cr in cycleCredits" :key="cr.id" :value="cr.id">
                                            {{ cr.name }}
                                            <template v-if="cr.unit_type === 'fixed'"> (cuota fija)</template>
                                            <template v-else-if="cr.is_unlimited"> (∞)</template>
                                            <template v-else> ({{ cr.remaining }}/{{ cr.capacity }})</template>
                                        </option>
                                    </select>
                                    <button
                                        type="button"
                                        @click="updateCycle"
                                        :disabled="cycleForm.processing || cycleForm.billing_cycle_id === null"
                                        class="w-full flex items-center justify-center gap-1.5 bg-[#264ab3] hover:bg-[#193074] disabled:opacity-40 text-white text-xs font-bold py-1.5 px-3 rounded-xl transition-all"
                                    >
                                        <ArrowPathIcon class="h-3.5 w-3.5" />
                                        Vincular a ciclo
                                    </button>
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

        <!-- TAB: CANVAS / STORYBOARD -->
        <div v-show="activeTab === 'canvas'" class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-800 p-5">
                <TicketCanvas
                    :ticket-id="ticket.id"
                    :items="canvasItems"
                    :can-edit="!isClient"
                    :can-approve="true"
                />
            </div>
        </div>

        <!-- TAB: DOCUMENTOS DEL CLIENTE -->
        <div v-show="activeTab === 'documents'" class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div v-if="clientForCanvas">
                <!-- Documentos y archivos -->
                <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-800 p-5">
                    <ClientAssetsManager
                        :client-id="clientForCanvas.id"
                        :assets="clientForCanvas.assets || []"
                        :can-edit="!isClient"
                    />
                </div>

                <!-- Información del Proyecto (Briefing) -->
                <div
                    v-if="clientForCanvas.briefing_context || clientForCanvas.briefing_target_audience || clientForCanvas.briefing_competitors || clientForCanvas.briefing_references || clientForCanvas.briefing_contact_methods || clientForCanvas.briefing_current_emails"
                    class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-800 overflow-hidden"
                >
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-800 bg-yellow-50/50 dark:bg-yellow-950/10 flex items-center gap-2">
                        <LightBulbIcon class="h-5 w-5 text-yellow-500 dark:text-yellow-400 shrink-0" />
                        <div>
                            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100">Información del Proyecto</h3>
                            <p class="text-[11px] text-gray-500 dark:text-zinc-400">Briefing creativo completado por el cliente.</p>
                        </div>
                    </div>
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div v-if="clientForCanvas.briefing_context" class="md:col-span-2">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-zinc-500 mb-1">Contexto del negocio</p>
                            <p class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap">{{ clientForCanvas.briefing_context }}</p>
                        </div>
                        <div v-if="clientForCanvas.briefing_target_audience">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-zinc-500 mb-1">Público objetivo</p>
                            <p class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap">{{ clientForCanvas.briefing_target_audience }}</p>
                        </div>
                        <div v-if="clientForCanvas.briefing_competitors">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-zinc-500 mb-1">Competidores</p>
                            <p class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap">{{ clientForCanvas.briefing_competitors }}</p>
                        </div>
                        <div v-if="clientForCanvas.briefing_references">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-zinc-500 mb-1">Referencias visuales</p>
                            <p class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap">{{ clientForCanvas.briefing_references }}</p>
                        </div>
                        <div v-if="clientForCanvas.briefing_contact_methods">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-zinc-500 mb-1">Métodos de contacto</p>
                            <p class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap">{{ clientForCanvas.briefing_contact_methods }}</p>
                        </div>
                        <div v-if="clientForCanvas.briefing_current_emails">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-zinc-500 mb-1">Correos empresariales</p>
                            <p class="text-sm text-gray-700 dark:text-gray-200">{{ clientForCanvas.briefing_current_emails }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="text-center text-sm text-gray-500 dark:text-zinc-400 italic py-10">
                Este ticket no tiene un cliente vinculado.
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
