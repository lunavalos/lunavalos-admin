<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    DocumentChartBarIcon,
    PlusIcon,
    ArrowDownTrayIcon,
    PencilSquareIcon,
    TrashIcon,
    BuildingOfficeIcon,
    CalendarIcon,
    TicketIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    reports: Array,
    clients: Array,
});

const search = ref('');
const selectedClient = ref(null);

const filteredReports = computed(() => {
    let r = props.reports;
    if (selectedClient.value) r = r.filter(x => x.client_id === selectedClient.value);
    if (search.value) {
        const s = search.value.toLowerCase();
        r = r.filter(x =>
            x.title.toLowerCase().includes(s) ||
            x.client?.business_name?.toLowerCase().includes(s) ||
            x.period_label?.toLowerCase().includes(s)
        );
    }
    return r;
});

const deleteReport = (id) => {
    if (confirm('¿Eliminar este reporte?')) {
        router.delete(route('reports.destroy', id));
    }
};

const months = [
    'Enero','Febrero','Marzo','Abril','Mayo','Junio',
    'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'
];
const monthName = (m) => months[m - 1] ?? '?';
</script>

<template>
    <Head title="Reportes" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center">
                        <DocumentChartBarIcon class="h-8 w-8 mr-2 text-[#264ab3] dark:text-blue-400" />
                        Reportes Mensuales
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-zinc-400 mt-1">Reportes de actividad generados para cada cliente.</p>
                </div>
                <Link
                    :href="route('reports.create')"
                    class="bg-[#264ab3] hover:bg-[#193074] text-white px-5 py-2.5 rounded-xl font-bold flex items-center transition-all"
                >
                    <PlusIcon class="h-5 w-5 mr-1.5" />
                    Nuevo Reporte
                </Link>
            </div>
        </template>

        <div class="py-8">
            <!-- Filters -->
            <div class="mb-6 flex flex-col sm:flex-row gap-3">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Buscar por título, cliente o periodo..."
                    class="flex-1 border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-gray-700 dark:text-gray-300 rounded-xl shadow-sm focus:border-[#264ab3] focus:ring-[#264ab3] text-sm"
                />
                <select
                    v-model="selectedClient"
                    class="border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-gray-700 dark:text-gray-300 rounded-xl shadow-sm focus:border-[#264ab3] focus:ring-[#264ab3] text-sm min-w-[220px]"
                >
                    <option :value="null">Todos los clientes</option>
                    <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.business_name }}</option>
                </select>
            </div>

            <!-- Empty -->
            <div v-if="filteredReports.length === 0" class="text-center py-24 bg-white dark:bg-zinc-900 rounded-3xl border border-gray-100 dark:border-zinc-800">
                <DocumentChartBarIcon class="h-14 w-14 mx-auto text-gray-200 dark:text-zinc-700 mb-4" />
                <h3 class="text-lg font-bold text-gray-700 dark:text-gray-200">Sin reportes</h3>
                <p class="text-sm text-gray-400 dark:text-zinc-500 mt-1">Crea el primer reporte para un cliente.</p>
            </div>

            <!-- Grid -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <div
                    v-for="report in filteredReports"
                    :key="report.id"
                    class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-md transition-all overflow-hidden group"
                >
                    <!-- Card Top Accent -->
                    <div class="h-1.5 w-full bg-gradient-to-r from-[#264ab3] to-blue-400"></div>

                    <div class="p-5">
                        <!-- Period Badge -->
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-[11px] bg-blue-50 dark:bg-blue-900/30 text-[#264ab3] dark:text-blue-400 font-bold px-2.5 py-1 rounded-lg uppercase tracking-wider flex items-center">
                                <CalendarIcon class="h-3.5 w-3.5 mr-1" />
                                {{ monthName(report.period_month) }} {{ report.period_year }}
                            </span>
                            <span class="text-xs text-gray-300 dark:text-zinc-700 font-bold">#{{ String(report.id).padStart(4,'0') }}</span>
                        </div>

                        <!-- Title -->
                        <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base mb-1 group-hover:text-[#264ab3] dark:group-hover:text-blue-400 transition-colors line-clamp-2">
                            {{ report.title }}
                        </h3>

                        <!-- Client -->
                        <div class="flex items-center text-[11px] text-[#264ab3] dark:text-blue-400 font-bold mb-4">
                            <BuildingOfficeIcon class="h-3.5 w-3.5 mr-1 shrink-0" />
                            {{ report.client?.business_name }}
                        </div>

                        <!-- Ticket count -->
                        <div class="flex items-center text-xs text-gray-400 dark:text-zinc-500 mb-5">
                            <TicketIcon class="h-4 w-4 mr-1.5" />
                            {{ report.tickets_count ?? 0 }} actividad{{ report.tickets_count !== 1 ? 'es' : '' }} incluida{{ report.tickets_count !== 1 ? 's' : '' }}
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            <Link
                                :href="route('reports.show', report.id)"
                                class="flex-1 text-center text-xs font-bold py-2 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-[#264ab3] dark:text-blue-400 hover:bg-[#264ab3] hover:text-white transition-all"
                            >
                                Ver Reporte
                            </Link>
                            <a
                                :href="route('reports.pdf', report.id)"
                                target="_blank"
                                class="p-2 rounded-xl border border-gray-100 dark:border-zinc-800 text-gray-400 dark:text-zinc-500 hover:text-green-600 hover:border-green-200 hover:bg-green-50 dark:hover:bg-green-900/20 transition-all"
                                title="Descargar PDF"
                            >
                                <ArrowDownTrayIcon class="h-4 w-4" />
                            </a>
                            <Link
                                :href="route('reports.edit', report.id)"
                                class="p-2 rounded-xl border border-gray-100 dark:border-zinc-800 text-gray-400 dark:text-zinc-500 hover:text-[#264ab3] hover:border-blue-200 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all"
                                title="Editar"
                            >
                                <PencilSquareIcon class="h-4 w-4" />
                            </Link>
                            <button
                                @click="deleteReport(report.id)"
                                class="p-2 rounded-xl border border-gray-100 dark:border-zinc-800 text-gray-400 dark:text-zinc-500 hover:text-red-500 hover:border-red-100 hover:bg-red-50 dark:hover:bg-rose-900/20 transition-all"
                                title="Eliminar"
                            >
                                <TrashIcon class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
