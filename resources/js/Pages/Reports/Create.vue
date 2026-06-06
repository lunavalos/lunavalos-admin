<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import {
    DocumentChartBarIcon,
    ChevronLeftIcon,
    TicketIcon,
    CalendarDaysIcon,
    CheckBadgeIcon,
} from '@heroicons/vue/24/outline';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    clients: Array,
});

const currentYear  = new Date().getFullYear();
const currentMonth = new Date().getMonth() + 1;

// Default: first and last day of current month
const defaultFrom = `${currentYear}-${String(currentMonth).padStart(2,'0')}-01`;
const lastDay = new Date(currentYear, currentMonth, 0).getDate();
const defaultTo = `${currentYear}-${String(currentMonth).padStart(2,'0')}-${lastDay}`;

const months = [
    { value: 1, label: 'Enero' }, { value: 2, label: 'Febrero' },
    { value: 3, label: 'Marzo' }, { value: 4, label: 'Abril' },
    { value: 5, label: 'Mayo' }, { value: 6, label: 'Junio' },
    { value: 7, label: 'Julio' }, { value: 8, label: 'Agosto' },
    { value: 9, label: 'Septiembre' }, { value: 10, label: 'Octubre' },
    { value: 11, label: 'Noviembre' }, { value: 12, label: 'Diciembre' },
];

const form = useForm({
    client_id:    null,
    title:        '',
    period_month: currentMonth,
    period_year:  currentYear,
    summary:      '',
    notes:        '',
    date_from:    defaultFrom,
    date_to:      defaultTo,
    pdf_options: {
        show_assigned:   true,
        show_dates:      true,
        show_duration:   true,
        show_status_log: false,
    },
});

// Searchable client combobox state
const clientSearch       = ref('');
const clientDropdownOpen = ref(false);
const clientComboRef     = ref(null);

const selectedClientLabel = computed(() => {
    if (!form.client_id) return null;
    return props.clients?.find(c => c.id === form.client_id)?.business_name ?? null;
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
    form.client_id           = client ? client.id : null;
    clientSearch.value       = '';
    clientDropdownOpen.value = false;
};

const clearClient = () => {
    form.client_id           = null;
    clientSearch.value       = '';
    clientDropdownOpen.value = false;
};

const handleClickOutsideClient = (e) => {
    if (clientComboRef.value && !clientComboRef.value.contains(e.target)) {
        clientDropdownOpen.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', handleClickOutsideClient));
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutsideClient));

// Preview of tickets that will be included
const previewTickets  = ref([]);
const loadingPreview  = ref(false);

const fetchPreview = async () => {
    if (!form.client_id || !form.date_from || !form.date_to) {
        previewTickets.value = [];
        return;
    }
    loadingPreview.value = true;
    try {
        const url = route('clients.tickets-json', form.client_id)
            + `?from=${form.date_from}&to=${form.date_to}`;
        const res = await fetch(url);
        previewTickets.value = await res.json();
    } catch (e) {
        console.error(e);
        previewTickets.value = [];
    } finally {
        loadingPreview.value = false;
    }
};

// Re-fetch whenever client or dates change
watch([() => form.client_id, () => form.date_from, () => form.date_to], fetchPreview);

// Auto-suggest title from client + date range
watch([() => form.client_id, () => form.date_from, () => form.date_to], () => {
    const client = props.clients?.find(c => c.id === form.client_id);
    if (!client || !form.date_from || !form.date_to) return;
    const from = new Date(form.date_from + 'T00:00:00').toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
    const to   = new Date(form.date_to   + 'T00:00:00').toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
    form.title = `Reporte de Actividades – ${client.business_name} – ${from} al ${to}`;
});

// Auto-set period_month/year from date_from
watch(() => form.date_from, (val) => {
    if (!val) return;
    const d = new Date(val + 'T00:00:00');
    form.period_month = d.getMonth() + 1;
    form.period_year  = d.getFullYear();
});

const statusColors = {
    'Nuevos':      'bg-blue-100 text-blue-700',
    'En Proceso':  'bg-yellow-100 text-yellow-700',
    'En Revisión': 'bg-purple-100 text-purple-700',
    'Ajustes':     'bg-orange-100 text-orange-700',
    'Completados': 'bg-green-100 text-green-700',
};

const completedCount = computed(() =>
    previewTickets.value.filter(t => t.status === 'Completados').length
);

const submit = () => {
    form.post(route('reports.store'));
};
</script>

<template>
    <Head title="Nuevo Reporte" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center">
                <Link :href="route('reports.index')" class="mr-4 p-2 bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-zinc-800 hover:border-[#264ab3] text-gray-500 hover:text-[#264ab3] transition-all">
                    <ChevronLeftIcon class="h-5 w-5" />
                </Link>
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center">
                        <DocumentChartBarIcon class="h-7 w-7 mr-2 text-[#264ab3] dark:text-blue-400" />
                        Nuevo Reporte de Actividades
                    </h2>
                    <p class="text-sm text-gray-400 dark:text-zinc-500 mt-0.5">Selecciona un cliente y un rango de fechas para generar el reporte.</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- LEFT: Main form -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Client, Date Range & Meta -->
                    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-100 dark:border-zinc-800 p-6">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 text-sm mb-5 uppercase tracking-wider">Información del Reporte</h3>

                        <div class="space-y-4">
                            <!-- Client -->
                            <div>
                                <InputLabel value="Cliente" />
                                <div ref="clientComboRef" class="relative">
                                    <button
                                        type="button"
                                        @click="openClientDropdown"
                                        class="mt-1 w-full flex items-center justify-between border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 text-gray-700 dark:text-gray-300 rounded-xl shadow-sm px-4 py-3 text-sm text-left focus:outline-none focus:border-[#264ab3] focus:ring-1 focus:ring-[#264ab3] transition"
                                    >
                                        <span :class="!selectedClientLabel ? 'text-gray-400 dark:text-zinc-500 italic' : ''">
                                            {{ selectedClientLabel ?? 'Seleccionar cliente...' }}
                                        </span>
                                        <svg class="h-4 w-4 text-gray-400 dark:text-zinc-500 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                        </svg>
                                    </button>

                                    <div v-if="clientDropdownOpen" class="absolute z-50 mt-2 w-full bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-2xl shadow-xl overflow-hidden">
                                        <div class="px-4 py-3">
                                            <input
                                                v-model="clientSearch"
                                                type="text"
                                                autofocus
                                                placeholder="Buscar cliente..."
                                                class="w-full border border-[#264ab3] dark:border-blue-500 bg-white dark:bg-zinc-950 text-gray-800 dark:text-gray-200 rounded-xl shadow-sm px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#264ab3] transition"
                                            />
                                        </div>

                                        <div class="border-t border-gray-100 dark:border-zinc-800"></div>

                                        <button
                                            type="button"
                                            @click="clearClient"
                                            class="w-full text-left px-4 py-2.5 text-sm text-gray-500 dark:text-zinc-400 italic hover:bg-gray-50 dark:hover:bg-zinc-800 transition"
                                        >
                                            Limpiar selección
                                        </button>
                                        <div class="border-t border-gray-100 dark:border-zinc-800"></div>

                                        <div class="max-h-64 overflow-y-auto">
                                            <button
                                                v-for="client in filteredClients"
                                                :key="client.id"
                                                type="button"
                                                @click="selectClient(client)"
                                                class="w-full text-left px-4 py-3 text-sm text-gray-700 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:text-[#264ab3] dark:hover:text-blue-400 transition flex items-center justify-between"
                                                :class="form.client_id === client.id ? 'bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 font-semibold' : ''"
                                            >
                                                {{ client.business_name }}
                                                <svg v-if="form.client_id === client.id" class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                                </svg>
                                            </button>

                                            <div v-if="filteredClients.length === 0" class="px-4 py-6 text-center text-sm text-gray-400 dark:text-zinc-500 italic">
                                                No se encontraron clientes
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <InputError class="mt-1" :message="form.errors.client_id" />
                            </div>

                            <!-- Date Range -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="Fecha de inicio" />
                                    <TextInput
                                        v-model="form.date_from"
                                        type="date"
                                        class="mt-1 w-full border-gray-200 dark:border-zinc-800 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-xl dark:bg-zinc-950"
                                        required
                                    />
                                    <InputError class="mt-1" :message="form.errors.date_from" />
                                </div>
                                <div>
                                    <InputLabel value="Fecha de fin" />
                                    <TextInput
                                        v-model="form.date_to"
                                        type="date"
                                        class="mt-1 w-full border-gray-200 dark:border-zinc-800 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-xl dark:bg-zinc-950"
                                        required
                                    />
                                    <InputError class="mt-1" :message="form.errors.date_to" />
                                </div>
                            </div>

                            <!-- Title (auto-filled) -->
                            <div>
                                <InputLabel value="Título del Reporte" />
                                <TextInput
                                    v-model="form.title"
                                    type="text"
                                    class="mt-1 w-full border-gray-200 dark:border-zinc-800 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-xl dark:bg-zinc-950"
                                    placeholder="Se genera automáticamente..."
                                    required
                                />
                                <InputError class="mt-1" :message="form.errors.title" />
                            </div>

                            <!-- Summary -->
                            <div>
                                <InputLabel value="Resumen Ejecutivo (aparece en el PDF)" />
                                <textarea
                                    v-model="form.summary"
                                    rows="4"
                                    placeholder="Escribe un resumen del periodo, logros principales, etc..."
                                    class="mt-1 w-full border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 text-gray-700 dark:text-gray-300 rounded-xl shadow-sm focus:border-[#264ab3] focus:ring-[#264ab3] text-sm resize-none"
                                ></textarea>
                            </div>

                            <!-- Notes -->
                            <div>
                                <InputLabel value="Observaciones Internas (opcional)" />
                                <textarea
                                    v-model="form.notes"
                                    rows="3"
                                    placeholder="Notas internas, pendientes, comentarios para el equipo..."
                                    class="mt-1 w-full border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 text-gray-700 dark:text-gray-300 rounded-xl shadow-sm focus:border-[#264ab3] focus:ring-[#264ab3] text-sm resize-none"
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Opciones del PDF -->
                    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-100 dark:border-zinc-800 p-6">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 text-sm mb-1 uppercase tracking-wider">Opciones del PDF</h3>
                        <p class="text-xs text-gray-400 dark:text-zinc-500 mb-4">Selecciona qué columnas y secciones incluir en el reporte descargable.</p>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-100 dark:border-zinc-800 hover:bg-gray-50 dark:hover:bg-zinc-800/50 cursor-pointer transition">
                                <input type="checkbox" v-model="form.pdf_options.show_assigned"
                                    class="mt-0.5 rounded border-gray-300 dark:border-zinc-600 text-[#264ab3] focus:ring-[#264ab3]" />
                                <div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200 block">Responsable</span>
                                    <span class="text-xs text-gray-400 dark:text-zinc-500">Quién tiene asignado cada ticket</span>
                                </div>
                            </label>
                            <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-100 dark:border-zinc-800 hover:bg-gray-50 dark:hover:bg-zinc-800/50 cursor-pointer transition">
                                <input type="checkbox" v-model="form.pdf_options.show_dates"
                                    class="mt-0.5 rounded border-gray-300 dark:border-zinc-600 text-[#264ab3] focus:ring-[#264ab3]" />
                                <div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200 block">Inicio / Finalización</span>
                                    <span class="text-xs text-gray-400 dark:text-zinc-500">Fechas de inicio y cierre del trabajo</span>
                                </div>
                            </label>
                            <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-100 dark:border-zinc-800 hover:bg-gray-50 dark:hover:bg-zinc-800/50 cursor-pointer transition">
                                <input type="checkbox" v-model="form.pdf_options.show_duration"
                                    class="mt-0.5 rounded border-gray-300 dark:border-zinc-600 text-[#264ab3] focus:ring-[#264ab3]" />
                                <div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200 block">Duración</span>
                                    <span class="text-xs text-gray-400 dark:text-zinc-500">Tiempo total de resolución</span>
                                </div>
                            </label>
                            <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-100 dark:border-zinc-800 hover:bg-gray-50 dark:hover:bg-zinc-800/50 cursor-pointer transition">
                                <input type="checkbox" v-model="form.pdf_options.show_status_log"
                                    class="mt-0.5 rounded border-gray-300 dark:border-zinc-600 text-[#264ab3] focus:ring-[#264ab3]" />
                                <div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200 block">Bitácora de cambios</span>
                                    <span class="text-xs text-gray-400 dark:text-zinc-500">Historial de movimientos de estatus</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Ticket Preview -->
                    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                        <div class="p-5 border-b border-gray-50 dark:border-zinc-800 flex items-center justify-between">
                            <h3 class="font-bold text-gray-700 dark:text-gray-200 text-sm uppercase tracking-wider flex items-center">
                                <CalendarDaysIcon class="h-4 w-4 mr-1.5 text-[#264ab3] dark:text-blue-400" />
                                Tickets en el rango seleccionado
                            </h3>
                            <span v-if="previewTickets.length > 0" class="text-xs bg-blue-100 dark:bg-blue-900/40 text-[#264ab3] dark:text-blue-400 font-bold px-2.5 py-1 rounded-lg">
                                {{ previewTickets.length }} ticket{{ previewTickets.length !== 1 ? 's' : '' }}
                            </span>
                        </div>

                        <!-- No client selected -->
                        <div v-if="!form.client_id" class="p-12 text-center text-gray-300 dark:text-zinc-700">
                            <TicketIcon class="h-10 w-10 mx-auto mb-2 opacity-50" />
                            <p class="text-sm">Selecciona un cliente para previsualizar los tickets</p>
                        </div>

                        <!-- Loading -->
                        <div v-else-if="loadingPreview" class="p-12 text-center text-gray-400 dark:text-zinc-500">
                            <div class="animate-spin h-6 w-6 border-2 border-[#264ab3] border-t-transparent rounded-full mx-auto mb-2"></div>
                            <p class="text-sm">Buscando tickets...</p>
                        </div>

                        <!-- Empty -->
                        <div v-else-if="previewTickets.length === 0" class="p-12 text-center text-gray-300 dark:text-zinc-700">
                            <CalendarDaysIcon class="h-10 w-10 mx-auto mb-2 opacity-50" />
                            <p class="text-sm">No hay tickets en este rango de fechas</p>
                            <p class="text-xs mt-1 text-gray-400 dark:text-zinc-600">Se incluyen tickets creados en el período</p>
                        </div>

                        <!-- List -->
                        <div v-else class="divide-y divide-gray-50 dark:divide-zinc-800">
                            <div
                                v-for="ticket in previewTickets"
                                :key="ticket.id"
                                class="flex items-center gap-3 px-5 py-3"
                            >
                                <CheckBadgeIcon class="h-4 w-4 text-emerald-500 shrink-0" />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-semibold text-sm text-gray-800 dark:text-gray-100 truncate">{{ ticket.title }}</span>
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full shrink-0" :class="statusColors[ticket.status]">
                                            {{ ticket.status }}
                                        </span>
                                        <span v-if="ticket.source_type === 'recurring'" class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300 shrink-0">
                                            Recurrente
                                        </span>
                                    </div>
                                    <div class="text-[10px] text-gray-400 dark:text-zinc-500 mt-0.5">
                                        #{{ ticket.id }} · {{ ticket.priority }}
                                        <span v-if="ticket.assigned"> · {{ ticket.assigned.name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer note -->
                        <div v-if="previewTickets.length > 0" class="px-5 py-3 bg-blue-50 dark:bg-blue-900/10 border-t border-blue-100 dark:border-blue-900/30">
                            <p class="text-xs text-[#264ab3] dark:text-blue-400">
                                ✓ Todos estos tickets se incluirán automáticamente al crear el reporte.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Summary sidebar -->
                <div class="space-y-4">
                    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-100 dark:border-zinc-800 p-5 sticky top-6">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 text-xs uppercase tracking-wider mb-4">Resumen</h3>

                        <div class="space-y-3 text-sm mb-6">
                            <div>
                                <span class="text-xs text-gray-400 dark:text-zinc-500 block">Cliente</span>
                                <span class="font-bold text-gray-800 dark:text-gray-100">
                                    {{ clients?.find(c => c.id === form.client_id)?.business_name ?? '—' }}
                                </span>
                            </div>
                            <div>
                                <span class="text-xs text-gray-400 dark:text-zinc-500 block">Rango</span>
                                <span class="font-bold text-gray-800 dark:text-gray-100 text-xs">
                                    {{ form.date_from || '—' }} → {{ form.date_to || '—' }}
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 pt-2">
                                <div class="bg-gray-50 dark:bg-zinc-950 rounded-xl p-3 text-center border border-gray-100 dark:border-zinc-800">
                                    <div class="text-2xl font-black text-[#264ab3] dark:text-blue-400">{{ previewTickets.length }}</div>
                                    <div class="text-[10px] text-gray-400 dark:text-zinc-500 uppercase tracking-wider mt-0.5">Total</div>
                                </div>
                                <div class="bg-green-50 dark:bg-green-900/10 rounded-xl p-3 text-center border border-green-100 dark:border-green-900/30">
                                    <div class="text-2xl font-black text-green-600 dark:text-green-400">{{ completedCount }}</div>
                                    <div class="text-[10px] text-gray-400 dark:text-zinc-500 uppercase tracking-wider mt-0.5">Completados</div>
                                </div>
                            </div>
                        </div>

                        <PrimaryButton
                            class="w-full justify-center !bg-[#264ab3] hover:!bg-[#193074] rounded-xl !shadow-none py-3"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing || !form.client_id || !form.date_from || !form.date_to"
                        >
                            Crear Reporte
                        </PrimaryButton>

                        <Link :href="route('reports.index')" class="block mt-3 text-center text-sm text-gray-400 dark:text-zinc-500 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                            Cancelar
                        </Link>
                    </div>
                </div>

            </form>
        </div>
    </AuthenticatedLayout>
</template>
