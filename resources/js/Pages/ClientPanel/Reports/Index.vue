<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    DocumentChartBarIcon,
    PlusIcon,
    ArrowDownTrayIcon,
    EyeIcon,
    CalendarIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    reports: { type: Array, default: () => [] },
});

const months = [
    'Enero','Febrero','Marzo','Abril','Mayo','Junio',
    'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre',
];
const monthName = (m) => months[(m ?? 1) - 1] ?? '?';
</script>

<template>
    <Head title="Mis Reportes" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-black text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <DocumentChartBarIcon class="h-7 w-7 text-[#264ab3] dark:text-blue-400" />
                        Mis Reportes
                    </h2>
                    <p class="text-sm text-gray-400 dark:text-zinc-500 mt-0.5">Historial de reportes de actividad generados</p>
                </div>
                <Link
                    :href="route('client.reports.create')"
                    class="inline-flex items-center gap-2 bg-[#264ab3] hover:bg-[#193074] text-white text-sm font-bold px-5 py-2.5 rounded-xl transition-colors shadow"
                >
                    <PlusIcon class="h-4 w-4" />
                    Generar Reporte
                </Link>
            </div>
        </template>

        <div class="py-8">
            <!-- Empty state -->
            <div v-if="!reports.length" class="text-center py-20">
                <DocumentChartBarIcon class="h-14 w-14 mx-auto text-gray-200 dark:text-zinc-700 mb-4" />
                <p class="text-gray-400 dark:text-zinc-500 font-medium">Aún no tienes reportes generados</p>
                <p class="text-sm text-gray-300 dark:text-zinc-600 mt-1 mb-6">Genera tu primer reporte de actividad</p>
                <Link
                    :href="route('client.reports.create')"
                    class="inline-flex items-center gap-2 bg-[#264ab3] text-white text-sm font-bold px-5 py-2.5 rounded-xl"
                >
                    <PlusIcon class="h-4 w-4" />
                    Generar Reporte
                </Link>
            </div>

            <!-- Report list -->
            <div v-else class="space-y-3">
                <div
                    v-for="report in reports"
                    :key="report.id"
                    class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-100 dark:border-zinc-800 p-5 flex items-center gap-5 hover:border-[#264ab3]/30 transition-colors"
                >
                    <!-- Icon -->
                    <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center shrink-0">
                        <DocumentChartBarIcon class="h-6 w-6 text-[#264ab3] dark:text-blue-400" />
                    </div>

                    <!-- Meta -->
                    <div class="flex-1 min-w-0">
                        <div class="font-bold text-gray-800 dark:text-gray-100 text-sm truncate">{{ report.title }}</div>
                        <div class="flex items-center gap-3 text-xs text-gray-400 dark:text-zinc-500 mt-1">
                            <span class="flex items-center gap-1">
                                <CalendarIcon class="h-3 w-3" />
                                {{ monthName(report.period_month) }} {{ report.period_year }}
                            </span>
                            <span>{{ report.tickets_count ?? 0 }} actividades</span>
                            <span v-if="report.creator">Por {{ report.creator.name }}</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 shrink-0">
                        <Link
                            :href="route('client.reports.show', report.id)"
                            class="p-2 rounded-lg bg-gray-50 dark:bg-zinc-800 hover:bg-blue-50 dark:hover:bg-blue-900/20 text-gray-400 hover:text-[#264ab3] transition-colors"
                            title="Ver reporte"
                        >
                            <EyeIcon class="h-4 w-4" />
                        </Link>
                        <a
                            :href="route('client.reports.pdf', report.id)"
                            target="_blank"
                            class="p-2 rounded-lg bg-gray-50 dark:bg-zinc-800 hover:bg-green-50 dark:hover:bg-green-900/20 text-gray-400 hover:text-green-600 transition-colors"
                            title="Descargar PDF"
                        >
                            <ArrowDownTrayIcon class="h-4 w-4" />
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
