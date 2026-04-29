<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
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
    report:  Object,
    clients: Array,
});

// Derive a default date range from period_month/year if no snapshot dates
const pad  = (n) => String(n).padStart(2, '0');
const year  = props.report.period_year  ?? new Date().getFullYear();
const month = props.report.period_month ?? (new Date().getMonth() + 1);
const lastDay = new Date(year, month, 0).getDate();
const defaultFrom = `${year}-${pad(month)}-01`;
const defaultTo   = `${year}-${pad(month)}-${lastDay}`;

const form = useForm({
    title:        props.report.title        ?? '',
    period_month: props.report.period_month ?? (new Date().getMonth() + 1),
    period_year:  props.report.period_year  ?? new Date().getFullYear(),
    summary:      props.report.summary      ?? '',
    notes:        props.report.notes        ?? '',
    date_from:    defaultFrom,
    date_to:      defaultTo,
});

// Preview of tickets for the selected range
const previewTickets = ref([]);
const loadingPreview = ref(false);

const fetchPreview = async () => {
    if (!form.date_from || !form.date_to) {
        previewTickets.value = [];
        return;
    }
    loadingPreview.value = true;
    try {
        const url = route('clients.tickets-json', props.report.client_id)
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

// Auto-update period_month/year from date_from
watch(() => form.date_from, (val) => {
    if (!val) return;
    const d = new Date(val + 'T00:00:00');
    form.period_month = d.getMonth() + 1;
    form.period_year  = d.getFullYear();
});

// Re-fetch when dates change
watch([() => form.date_from, () => form.date_to], fetchPreview);

// Load initial preview on mount
fetchPreview();

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
    form.put(route('reports.update', props.report.id));
};
</script>

<template>
    <Head :title="`Editar: ${report.title}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center">
                <Link :href="route('reports.show', report.id)" class="mr-4 p-2 bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-zinc-800 hover:border-[#264ab3] text-gray-500 hover:text-[#264ab3] transition-all">
                    <ChevronLeftIcon class="h-5 w-5" />
                </Link>
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center">
                        <DocumentChartBarIcon class="h-7 w-7 mr-2 text-[#264ab3] dark:text-blue-400" />
                        Editar Reporte
                    </h2>
                    <p class="text-sm text-gray-400 dark:text-zinc-500 mt-0.5 truncate max-w-md">{{ report.title }}</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- LEFT: Main form -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Meta -->
                    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-100 dark:border-zinc-800 p-6">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 text-sm mb-5 uppercase tracking-wider">Información del Reporte</h3>

                        <!-- Client (read-only) -->
                        <div class="mb-4 p-3 rounded-xl bg-gray-50 dark:bg-zinc-950 border border-gray-100 dark:border-zinc-800 flex items-center gap-3">
                            <span class="text-xs text-gray-400 dark:text-zinc-500 shrink-0">Cliente</span>
                            <span class="font-bold text-gray-800 dark:text-gray-100 text-sm">{{ report.client?.business_name }}</span>
                            <span class="ml-auto text-[10px] text-gray-400 dark:text-zinc-600 italic">Solo lectura</span>
                        </div>

                        <div class="space-y-4">
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

                            <!-- Title -->
                            <div>
                                <InputLabel value="Título del Reporte" />
                                <TextInput
                                    v-model="form.title"
                                    type="text"
                                    class="mt-1 w-full border-gray-200 dark:border-zinc-800 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-xl dark:bg-zinc-950"
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

                        <!-- Loading -->
                        <div v-if="loadingPreview" class="p-12 text-center text-gray-400 dark:text-zinc-500">
                            <div class="animate-spin h-6 w-6 border-2 border-[#264ab3] border-t-transparent rounded-full mx-auto mb-2"></div>
                            <p class="text-sm">Buscando tickets...</p>
                        </div>

                        <!-- Empty -->
                        <div v-else-if="previewTickets.length === 0" class="p-12 text-center text-gray-300 dark:text-zinc-700">
                            <CalendarDaysIcon class="h-10 w-10 mx-auto mb-2 opacity-50" />
                            <p class="text-sm">No hay tickets en este rango de fechas</p>
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
                                    </div>
                                    <div class="text-[10px] text-gray-400 dark:text-zinc-500 mt-0.5">
                                        #{{ ticket.id }} · {{ ticket.priority }}
                                        <span v-if="ticket.assigned"> · {{ ticket.assigned.name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="previewTickets.length > 0" class="px-5 py-3 bg-blue-50 dark:bg-blue-900/10 border-t border-blue-100 dark:border-blue-900/30">
                            <p class="text-xs text-[#264ab3] dark:text-blue-400">
                                ✓ Al guardar, el reporte se actualizará con estos tickets automáticamente.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Sidebar -->
                <div class="space-y-4">
                    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-100 dark:border-zinc-800 p-5 sticky top-6">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 text-xs uppercase tracking-wider mb-4">Resumen</h3>

                        <div class="space-y-3 text-sm mb-6">
                            <div>
                                <span class="text-xs text-gray-400 dark:text-zinc-500 block">Cliente</span>
                                <span class="font-bold text-gray-800 dark:text-gray-100">{{ report.client?.business_name }}</span>
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
                            :disabled="form.processing || !form.date_from || !form.date_to"
                        >
                            Guardar Cambios
                        </PrimaryButton>

                        <Link :href="route('reports.show', report.id)" class="block mt-3 text-center text-sm text-gray-400 dark:text-zinc-500 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                            Cancelar
                        </Link>
                    </div>
                </div>

            </form>
        </div>
    </AuthenticatedLayout>
</template>
