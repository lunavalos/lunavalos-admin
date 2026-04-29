<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    DocumentChartBarIcon,
    ChevronLeftIcon,
    ArrowDownTrayIcon,
    PencilSquareIcon,
    TicketIcon,
    BuildingOfficeIcon,
    CalendarIcon,
    BriefcaseIcon,
    ClockIcon,
    PlayIcon,
    CheckCircleIcon,
    ListBulletIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    report:  Object,
    tickets: { type: Array, default: () => [] },
});

const statusColors = {
    'Nuevos':      'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    'En Proceso':  'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
    'En Revisión': 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
    'Ajustes':     'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
    'Completados': 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
};

const priorityColors = {
    'Baja':    'text-gray-500',
    'Media':   'text-blue-600',
    'Alta':    'text-orange-600 font-bold',
    'Urgente': 'text-red-600 font-bold',
};

const months = [
    'Enero','Febrero','Marzo','Abril','Mayo','Junio',
    'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre',
];
const monthName = (m) => months[(m ?? 1) - 1] ?? '?';

const completedCount  = props.tickets.filter(t => t.status === 'Completados').length;
const inProgressCount = props.tickets.filter(t => ['En Proceso','En Revisión','Ajustes'].includes(t.status)).length;

// Expanded state per ticket
const expandedTickets = ref({});
const toggleExpand = (id) => {
    expandedTickets.value[id] = !expandedTickets.value[id];
};

// Format a date string as human readable
const fmt = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleString('es-MX', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
};

// Calculate duration between two ISO date strings → "Xh Ym"
const duration = (from, to) => {
    if (!from || !to) return null;
    const ms = new Date(to) - new Date(from);
    if (ms <= 0) return null;
    const totalMins = Math.floor(ms / 60000);
    const h = Math.floor(totalMins / 60);
    const m = totalMins % 60;
    if (h === 0) return `${m}m`;
    return m > 0 ? `${h}h ${m}m` : `${h}h`;
};
</script>

<template>
    <Head :title="report.title" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <Link :href="route('reports.index')" class="mr-4 p-2 bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-zinc-800 hover:border-[#264ab3] text-gray-500 hover:text-[#264ab3] transition-all">
                        <ChevronLeftIcon class="h-5 w-5" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-bold text-gray-400 dark:text-zinc-500 uppercase tracking-wider">
                                {{ monthName(report.period_month) }} {{ report.period_year }}
                            </span>
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 leading-tight">{{ report.title }}</h2>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('reports.edit', report.id)"
                        class="flex items-center gap-1.5 px-4 py-2 rounded-xl border border-gray-200 dark:border-zinc-800 text-gray-500 dark:text-zinc-400 hover:text-[#264ab3] hover:border-blue-200 dark:hover:border-blue-800 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all text-sm font-bold"
                    >
                        <PencilSquareIcon class="h-4 w-4" />
                        Editar
                    </Link>
                    <a
                        :href="route('reports.pdf', report.id)"
                        target="_blank"
                        class="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#264ab3] hover:bg-[#193074] text-white text-sm font-bold transition-all"
                    >
                        <ArrowDownTrayIcon class="h-4 w-4" />
                        Descargar PDF
                    </a>
                </div>
            </div>
        </template>

        <div class="py-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- LEFT: Tickets -->
            <div class="lg:col-span-2 space-y-5">

                <!-- Summary -->
                <div v-if="report.summary" class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-100 dark:border-zinc-800 p-6">
                    <h3 class="text-xs font-bold text-gray-400 dark:text-zinc-500 uppercase tracking-widest mb-3">Resumen Ejecutivo</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-wrap">{{ report.summary }}</p>
                </div>

                <!-- Tickets -->
                <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                    <div class="p-5 border-b border-gray-50 dark:border-zinc-800 flex items-center justify-between">
                        <h3 class="text-xs font-bold text-gray-400 dark:text-zinc-500 uppercase tracking-widest flex items-center">
                            <TicketIcon class="h-4 w-4 mr-1.5 text-[#264ab3] dark:text-blue-400" />
                            Actividades del Periodo
                        </h3>
                        <span class="text-xs font-bold text-gray-400 dark:text-zinc-500">{{ tickets.length }} total</span>
                    </div>

                    <div v-if="!tickets.length" class="p-10 text-center text-gray-300 dark:text-zinc-700">
                        <TicketIcon class="h-10 w-10 mx-auto mb-2 opacity-50" />
                        <p class="text-sm">Sin actividades registradas</p>
                    </div>

                    <div v-else class="divide-y divide-gray-50 dark:divide-zinc-800">
                        <div v-for="ticket in tickets" :key="ticket.id" class="p-5 hover:bg-gray-50/60 dark:hover:bg-zinc-950/40 transition-colors">

                            <!-- Header row -->
                            <div class="flex items-start gap-3">
                                <!-- Status dot -->
                                <div class="mt-1.5 w-2 h-2 rounded-full shrink-0"
                                    :class="{
                                        'bg-blue-500':   ticket.status === 'Nuevos',
                                        'bg-yellow-500': ticket.status === 'En Proceso',
                                        'bg-purple-500': ticket.status === 'En Revisión',
                                        'bg-orange-500': ticket.status === 'Ajustes',
                                        'bg-green-500':  ticket.status === 'Completados',
                                    }"
                                ></div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap mb-1">
                                        <span class="font-bold text-gray-800 dark:text-gray-100 text-sm">{{ ticket.title }}</span>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="statusColors[ticket.status]">
                                            {{ ticket.status }}
                                        </span>
                                        <span class="text-[10px]" :class="priorityColors[ticket.priority]">{{ ticket.priority }}</span>
                                    </div>
                                    <div class="flex items-center gap-3 text-[11px] text-gray-400 dark:text-zinc-500">
                                        <span>#{{ ticket.id }}</span>
                                        <span v-if="ticket.client_service" class="flex items-center gap-1">
                                            <BriefcaseIcon class="h-3 w-3" />
                                            {{ ticket.client_service.service_name }}
                                        </span>
                                        <span v-if="ticket.assigned">{{ ticket.assigned.name }}</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <!-- Toggle expand -->
                                    <button
                                        type="button"
                                        @click="toggleExpand(ticket.id)"
                                        class="text-[11px] text-gray-400 dark:text-zinc-500 hover:text-[#264ab3] dark:hover:text-blue-400 flex items-center gap-1 transition-colors"
                                    >
                                        <ListBulletIcon class="h-3.5 w-3.5" />
                                        {{ expandedTickets[ticket.id] ? 'Ocultar' : 'Detalle' }}
                                    </button>
                                    <!-- Link if not force-deleted -->
                                    <Link
                                        v-if="ticket.deleted_at === undefined || ticket.deleted_at === null"
                                        :href="route('tickets.show', ticket.id)"
                                        class="text-[11px] text-[#264ab3] dark:text-blue-400 hover:underline font-bold"
                                    >
                                        Ver →
                                    </Link>
                                    <span v-else class="text-[11px] text-gray-300 dark:text-zinc-700 italic">Eliminado</span>
                                </div>
                            </div>

                            <!-- Expanded detail -->
                            <div v-if="expandedTickets[ticket.id]" class="mt-4 ml-5 space-y-4">

                                <!-- Work dates & duration -->
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div class="bg-gray-50 dark:bg-zinc-950 rounded-xl p-3 border border-gray-100 dark:border-zinc-800">
                                        <div class="flex items-center gap-1.5 text-[10px] font-bold text-gray-400 dark:text-zinc-500 uppercase tracking-wider mb-1">
                                            <PlayIcon class="h-3 w-3 text-emerald-500" />
                                            Inicio de trabajo
                                        </div>
                                        <div class="text-xs text-gray-700 dark:text-gray-300 font-medium">
                                            {{ fmt(ticket.work_started_at) }}
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-zinc-950 rounded-xl p-3 border border-gray-100 dark:border-zinc-800">
                                        <div class="flex items-center gap-1.5 text-[10px] font-bold text-gray-400 dark:text-zinc-500 uppercase tracking-wider mb-1">
                                            <CheckCircleIcon class="h-3 w-3 text-green-500" />
                                            Finalización
                                        </div>
                                        <div class="text-xs text-gray-700 dark:text-gray-300 font-medium">
                                            {{ fmt(ticket.work_finished_at) }}
                                        </div>
                                    </div>
                                    <div class="bg-blue-50 dark:bg-blue-900/10 rounded-xl p-3 border border-blue-100 dark:border-blue-900/30">
                                        <div class="flex items-center gap-1.5 text-[10px] font-bold text-[#264ab3] dark:text-blue-400 uppercase tracking-wider mb-1">
                                            <ClockIcon class="h-3 w-3" />
                                            Duración total
                                        </div>
                                        <div class="text-sm font-black text-[#264ab3] dark:text-blue-400">
                                            {{ duration(ticket.work_started_at, ticket.work_finished_at) ?? '—' }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Status log / bitácora -->
                                <div v-if="ticket.status_log && ticket.status_log.length > 0">
                                    <div class="text-[10px] font-bold text-gray-400 dark:text-zinc-500 uppercase tracking-wider mb-2 flex items-center gap-1">
                                        <ListBulletIcon class="h-3 w-3" />
                                        Bitácora de cambios
                                    </div>
                                    <div class="relative pl-4">
                                        <!-- Timeline line -->
                                        <div class="absolute left-1.5 top-0 bottom-0 w-px bg-gray-200 dark:bg-zinc-700"></div>
                                        <div
                                            v-for="(entry, idx) in ticket.status_log"
                                            :key="idx"
                                            class="relative mb-2 last:mb-0"
                                        >
                                            <div class="absolute -left-2.5 top-1 w-2 h-2 rounded-full bg-gray-300 dark:bg-zinc-600 border border-white dark:border-zinc-900"></div>
                                            <div class="pl-2">
                                                <p class="text-[11px] text-gray-600 dark:text-zinc-300 leading-snug" v-html="entry.message"></p>
                                                <p class="text-[10px] text-gray-400 dark:text-zinc-600 mt-0.5">{{ fmt(entry.created_at) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-[11px] text-gray-300 dark:text-zinc-700 italic">
                                    Sin bitácora de cambios registrada.
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div v-if="report.notes" class="bg-amber-50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-900/30 rounded-2xl p-6">
                    <h3 class="text-xs font-bold text-amber-700 dark:text-amber-400 uppercase tracking-widest mb-3">Observaciones</h3>
                    <p class="text-sm text-amber-800 dark:text-amber-300 leading-relaxed whitespace-pre-wrap">{{ report.notes }}</p>
                </div>
            </div>

            <!-- RIGHT: Sidebar -->
            <div class="space-y-5">
                <!-- Stats -->
                <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-100 dark:border-zinc-800 p-5">
                    <h3 class="text-xs font-bold text-gray-400 dark:text-zinc-500 uppercase tracking-widest mb-4">Estadísticas</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-gray-50 dark:bg-zinc-950 rounded-xl p-3 text-center border border-gray-100 dark:border-zinc-800">
                            <div class="text-2xl font-black text-[#264ab3] dark:text-blue-400">{{ tickets.length }}</div>
                            <div class="text-[10px] text-gray-400 dark:text-zinc-500 uppercase tracking-wider mt-1">Total</div>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/10 rounded-xl p-3 text-center border border-green-100 dark:border-green-900/30">
                            <div class="text-2xl font-black text-green-600 dark:text-green-400">{{ completedCount }}</div>
                            <div class="text-[10px] text-gray-400 dark:text-zinc-500 uppercase tracking-wider mt-1">Completados</div>
                        </div>
                        <div class="bg-yellow-50 dark:bg-yellow-900/10 rounded-xl p-3 text-center border border-yellow-100 dark:border-yellow-900/30 col-span-2">
                            <div class="text-2xl font-black text-yellow-600 dark:text-yellow-400">{{ inProgressCount }}</div>
                            <div class="text-[10px] text-gray-400 dark:text-zinc-500 uppercase tracking-wider mt-1">En Proceso</div>
                        </div>
                    </div>
                </div>

                <!-- Client info -->
                <div class="bg-blue-50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-900/30 rounded-2xl p-5">
                    <h3 class="text-xs font-bold text-[#264ab3] dark:text-blue-400 uppercase tracking-widest mb-3 flex items-center">
                        <BuildingOfficeIcon class="h-4 w-4 mr-1.5" />
                        Cliente
                    </h3>
                    <div class="font-bold text-gray-800 dark:text-gray-100 mb-1">{{ report.client?.business_name }}</div>
                    <div class="text-xs text-gray-500 dark:text-zinc-400">{{ report.client?.contact_name }}</div>
                    <div class="text-xs text-gray-400 dark:text-zinc-500">{{ report.client?.email }}</div>
                    <Link :href="route('clients.show', report.client?.id)" class="mt-3 block text-center text-xs font-bold text-[#264ab3] dark:text-blue-400 bg-white dark:bg-zinc-900 border border-blue-100 dark:border-blue-900/40 rounded-xl py-2 hover:bg-[#264ab3] hover:text-white transition-all">
                        Ver Ficha Completa
                    </Link>
                </div>

                <!-- Meta -->
                <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-100 dark:border-zinc-800 p-5 text-sm">
                    <div class="flex items-center gap-2 mb-2 text-gray-400 dark:text-zinc-500">
                        <CalendarIcon class="h-4 w-4" />
                        Creado el {{ new Date(report.created_at).toLocaleDateString('es-MX', { day:'2-digit', month:'long', year:'numeric' }) }}
                    </div>
                    <div class="text-gray-400 dark:text-zinc-500 text-xs">Por: {{ report.creator?.name }}</div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
