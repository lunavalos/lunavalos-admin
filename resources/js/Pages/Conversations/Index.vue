<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ChatBubbleLeftRightIcon,
    ExclamationTriangleIcon,
    TicketIcon,
    SparklesIcon,
    ArchiveBoxIcon,
} from '@heroicons/vue/24/outline';
import { ref, computed, nextTick, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    conversations: { type: Array, default: () => [] },
    conversation: { type: Object, default: null },
    filtros: { type: Object, default: () => ({ status: 'open' }) },
});

const hilo = ref(null);
const mensajes = ref([]);

const replyForm = useForm({ body: '' });
const ticketForm = useForm({ title: '' });
const mostrarTicket = ref(false);

watch(
    () => props.conversation,
    (c) => {
        mensajes.value = c?.messages ? [...c.messages] : [];
        nextTick(bajarAlFinal);
    },
    { immediate: true }
);

const bajarAlFinal = () => {
    if (hilo.value) hilo.value.scrollTop = hilo.value.scrollHeight;
};

const filtrar = (status) => {
    router.get(
        props.conversation
            ? route('conversations.show', props.conversation.id)
            : route('conversations.index'),
        { status },
        { preserveState: true, preserveScroll: true }
    );
};

const responder = () => {
    if (!replyForm.body.trim()) return;
    replyForm.post(route('conversations.reply', props.conversation.id), {
        preserveScroll: true,
        onSuccess: () => {
            replyForm.reset('body');
            nextTick(bajarAlFinal);
        },
    });
};

const crearTicket = () => {
    ticketForm.post(route('conversations.createTicket', props.conversation.id));
};

const cambiarEstado = (status) => {
    router.post(route('conversations.status', props.conversation.id), { status }, { preserveScroll: true });
};

const alternarIa = () => {
    router.post(route('conversations.toggleAi', props.conversation.id), {}, { preserveScroll: true });
};

// Tiempo real. El canal es privado porque aquí viajan mensajes de clientes
// finales de terceros.
let canal = null;
const suscribir = () => {
    if (!props.conversation || !window.Echo) return;
    canal = window.Echo.private(`conversation.${props.conversation.id}`)
        .listen('.message.sent', (e) => {
            if (!mensajes.value.some((m) => m.id === e.id)) {
                mensajes.value.push(e);
                nextTick(bajarAlFinal);
            }
        });
};
const desuscribir = () => {
    if (canal && props.conversation && window.Echo) {
        window.Echo.leave(`conversation.${props.conversation.id}`);
        canal = null;
    }
};

onMounted(suscribir);
onUnmounted(desuscribir);
watch(() => props.conversation?.id, () => { desuscribir(); suscribir(); });

const horaCorta = (iso) =>
    new Date(iso).toLocaleString('es-MX', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });

const nombreContacto = (c) => c.contact_name || c.contact_wa_id;

const estadoEntrega = {
    pending: { texto: 'Enviando…', clase: 'text-gray-400' },
    sent: { texto: 'Enviado', clase: 'text-gray-400' },
    delivered: { texto: 'Entregado', clase: 'text-gray-400' },
    read: { texto: 'Leído', clase: 'text-blue-500' },
    failed: { texto: 'No entregado', clase: 'text-red-600 font-semibold' },
};

const ventanaCerrada = computed(
    () => props.conversation && !props.conversation.ventana_abierta
);
</script>

<template>
    <Head title="Conversaciones" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-zinc-100 leading-tight flex items-center gap-2">
                <ChatBubbleLeftRightIcon class="h-6 w-6" />
                Conversaciones
            </h2>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg flex h-[75vh] overflow-hidden">

                    <!-- Bandeja -->
                    <aside class="w-full sm:w-80 border-r border-gray-200 dark:border-zinc-800 flex flex-col">
                        <div class="p-3 border-b border-gray-200 dark:border-zinc-800 flex gap-1">
                            <button
                                v-for="f in [['open','Abiertas'],['archived','Archivadas'],['all','Todas']]"
                                :key="f[0]"
                                @click="filtrar(f[0])"
                                :class="[
                                    'px-3 py-1 text-xs rounded-full transition',
                                    filtros.status === f[0]
                                        ? 'bg-[#264ab3] text-white'
                                        : 'text-gray-600 dark:text-zinc-400 hover:bg-gray-100 dark:hover:bg-zinc-800',
                                ]"
                            >{{ f[1] }}</button>
                        </div>

                        <div class="flex-1 overflow-y-auto">
                            <p v-if="!conversations.length" class="p-6 text-sm text-gray-500 text-center">
                                No hay conversaciones.
                            </p>

                            <Link
                                v-for="c in conversations"
                                :key="c.id"
                                :href="route('conversations.show', c.id)"
                                preserve-scroll
                                :class="[
                                    'block px-4 py-3 border-b border-gray-100 dark:border-zinc-800 transition',
                                    conversation && conversation.id === c.id
                                        ? 'bg-blue-50 dark:bg-blue-900/20'
                                        : 'hover:bg-gray-50 dark:hover:bg-zinc-800/50',
                                ]"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <span class="font-medium text-sm text-gray-900 dark:text-zinc-100 truncate">
                                        {{ nombreContacto(c) }}
                                    </span>
                                    <span
                                        v-if="c.unread_count"
                                        class="shrink-0 bg-[#264ab3] text-white text-[10px] rounded-full px-1.5 py-0.5"
                                    >{{ c.unread_count }}</span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-zinc-400 truncate">
                                    {{ c.client?.business_name || 'LunAvalos' }}
                                </p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] text-gray-400">
                                        {{ c.last_message_at ? horaCorta(c.last_message_at) : '' }}
                                    </span>
                                    <!-- Fuera de la ventana de 24 h Meta no entrega texto libre. -->
                                    <span v-if="!c.ventana_abierta" class="text-[10px] text-amber-600">
                                        ventana cerrada
                                    </span>
                                </div>
                            </Link>
                        </div>
                    </aside>

                    <!-- Hilo -->
                    <section class="hidden sm:flex flex-1 flex-col">
                        <div v-if="!conversation" class="flex-1 grid place-items-center text-gray-400 text-sm">
                            Selecciona una conversación.
                        </div>

                        <template v-else>
                            <header class="px-4 py-3 border-b border-gray-200 dark:border-zinc-800 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 dark:text-zinc-100 truncate">
                                        {{ nombreContacto(conversation) }}
                                    </p>
                                    <p class="text-xs text-gray-500 truncate">
                                        {{ conversation.contact_wa_id }}
                                        <span v-if="conversation.numero"> · vía {{ conversation.numero }}</span>
                                    </p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button
                                        @click="alternarIa"
                                        :class="[
                                            'p-1.5 rounded-md transition',
                                            conversation.ai_enabled
                                                ? 'bg-violet-100 text-violet-700'
                                                : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-zinc-800',
                                        ]"
                                        :title="conversation.ai_enabled ? 'Agente de IA activo' : 'Agente de IA apagado'"
                                    >
                                        <SparklesIcon class="h-5 w-5" />
                                    </button>
                                    <button
                                        @click="mostrarTicket = !mostrarTicket"
                                        class="p-1.5 rounded-md text-gray-400 hover:bg-gray-100 dark:hover:bg-zinc-800"
                                        title="Crear ticket"
                                    >
                                        <TicketIcon class="h-5 w-5" />
                                    </button>
                                    <button
                                        @click="cambiarEstado(conversation.status === 'archived' ? 'open' : 'archived')"
                                        class="p-1.5 rounded-md text-gray-400 hover:bg-gray-100 dark:hover:bg-zinc-800"
                                        :title="conversation.status === 'archived' ? 'Reabrir' : 'Archivar'"
                                    >
                                        <ArchiveBoxIcon class="h-5 w-5" />
                                    </button>
                                </div>
                            </header>

                            <!-- Escalar a ticket -->
                            <div v-if="mostrarTicket" class="px-4 py-3 bg-gray-50 dark:bg-zinc-800/50 border-b border-gray-200 dark:border-zinc-800">
                                <div class="flex gap-2">
                                    <input
                                        v-model="ticketForm.title"
                                        placeholder="Título del ticket"
                                        class="flex-1 text-sm rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-900"
                                        @keyup.enter="crearTicket"
                                    />
                                    <button
                                        @click="crearTicket"
                                        :disabled="ticketForm.processing || !ticketForm.title"
                                        class="px-3 py-1.5 text-sm rounded-md bg-[#264ab3] text-white disabled:opacity-50"
                                    >Crear</button>
                                </div>
                                <p v-if="conversation.tickets?.length" class="mt-2 text-xs text-gray-500">
                                    Ya enlazados:
                                    <Link
                                        v-for="t in conversation.tickets"
                                        :key="t.id"
                                        :href="route('tickets.show', t.id)"
                                        class="text-[#264ab3] hover:underline mr-2"
                                    >#{{ t.id }}</Link>
                                </p>
                            </div>

                            <div ref="hilo" class="flex-1 overflow-y-auto p-4 space-y-3">
                                <div
                                    v-for="m in mensajes"
                                    :key="m.id"
                                    :class="['flex', m.direction === 'out' ? 'justify-end' : 'justify-start']"
                                >
                                    <div
                                        :class="[
                                            'max-w-[75%] rounded-2xl px-3 py-2 text-sm',
                                            m.direction === 'out'
                                                ? 'bg-[#264ab3] text-white rounded-br-sm'
                                                : 'bg-gray-100 dark:bg-zinc-800 text-gray-900 dark:text-zinc-100 rounded-bl-sm',
                                        ]"
                                    >
                                        <p class="whitespace-pre-wrap break-words">{{ m.body }}</p>
                                        <div class="mt-1 flex items-center gap-2 text-[10px] opacity-80">
                                            <span>{{ horaCorta(m.created_at) }}</span>
                                            <span v-if="m.author_type === 'ai'">· IA</span>
                                            <span v-else-if="m.user">· {{ m.user.name }}</span>
                                            <span
                                                v-if="m.direction === 'out'"
                                                :class="estadoEntrega[m.delivery_status]?.clase"
                                            >· {{ estadoEntrega[m.delivery_status]?.texto }}</span>
                                        </div>
                                        <!-- Antes un envío rechazado se guardaba como si hubiera
                                             llegado; ahora el equipo lo ve. -->
                                        <p
                                            v-if="m.delivery_status === 'failed' && m.delivery_error"
                                            class="mt-1 text-[10px] text-red-200"
                                        >{{ m.delivery_error }}</p>
                                    </div>
                                </div>
                            </div>

                            <footer class="border-t border-gray-200 dark:border-zinc-800 p-3">
                                <div
                                    v-if="ventanaCerrada"
                                    class="flex items-start gap-2 rounded-md bg-amber-50 dark:bg-amber-900/20 p-3 text-xs text-amber-800 dark:text-amber-300"
                                >
                                    <ExclamationTriangleIcon class="h-4 w-4 shrink-0 mt-0.5" />
                                    <span>
                                        Pasaron más de 24 horas desde el último mensaje del contacto.
                                        Meta no entrega texto libre fuera de esa ventana; hace falta
                                        una plantilla aprobada.
                                    </span>
                                </div>

                                <form v-else @submit.prevent="responder" class="flex gap-2">
                                    <input
                                        v-model="replyForm.body"
                                        placeholder="Escribe una respuesta…"
                                        class="flex-1 text-sm rounded-full border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                                    />
                                    <button
                                        type="submit"
                                        :disabled="replyForm.processing || !replyForm.body.trim()"
                                        class="px-4 py-2 text-sm rounded-full bg-[#264ab3] text-white disabled:opacity-50"
                                    >Enviar</button>
                                </form>
                                <p v-if="replyForm.errors.body" class="mt-2 text-xs text-red-600">
                                    {{ replyForm.errors.body }}
                                </p>
                            </footer>
                        </template>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
