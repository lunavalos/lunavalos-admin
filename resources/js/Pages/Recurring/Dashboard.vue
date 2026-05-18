<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ArrowPathIcon, ExclamationTriangleIcon, ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    rows: { type: Array, default: () => [] },
});

const hasData = computed(() => props.rows.length > 0);

function progressColor(pct) {
    if (pct >= 90) return 'bg-red-500';
    if (pct >= 70) return 'bg-amber-500';
    return 'bg-emerald-500';
}
function progressPct(s) {
    if (s.is_unlimited || s.capacity === 0) return 0;
    return Math.min(100, Math.round((s.consumed / s.capacity) * 100));
}
</script>

<template>
    <Head title="Clientes Recurrentes" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-zinc-100">
                Clientes Recurrentes
            </h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-6 flex items-center justify-between">
                    <p class="text-sm text-gray-600 dark:text-zinc-400">
                        Vista general de clientes con plan mensual. Cada tarjeta muestra el progreso
                        de los entregables del ciclo activo.
                    </p>
                </div>

                <div v-if="!hasData"
                    class="rounded-lg border border-dashed border-gray-300 dark:border-zinc-700 p-12 text-center">
                    <ClipboardDocumentCheckIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-zinc-100">
                        Aún no hay clientes recurrentes
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">
                        Configura los entregables mensuales en un contrato para que aparezca aquí.
                    </p>
                </div>

                <div v-else class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="row in rows" :key="row.contract_id"
                        class="rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-sm hover:shadow-md transition-shadow">
                        <!-- Header -->
                        <div class="border-b border-gray-200 dark:border-zinc-700 p-4">
                            <div class="flex items-start justify-between">
                                <div class="min-w-0">
                                    <Link :href="route('recurring.clients.show', row.client_id)"
                                        class="block truncate text-base font-semibold text-gray-900 dark:text-zinc-100 hover:text-[#264ab3] dark:hover:text-blue-400">
                                        {{ row.client_name }}
                                    </Link>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-zinc-400">
                                        Contrato {{ row.contract_number }}
                                    </p>
                                </div>
                                <span v-if="row.cycle_label"
                                    class="ml-2 inline-flex items-center gap-1 rounded-full bg-blue-50 dark:bg-blue-900/30 px-2 py-1 text-xs font-medium text-blue-700 dark:text-blue-300">
                                    <ArrowPathIcon class="h-3 w-3" />
                                    {{ row.cycle_label }}
                                </span>
                                <span v-else
                                    class="ml-2 inline-flex items-center gap-1 rounded-full bg-amber-50 dark:bg-amber-900/30 px-2 py-1 text-xs font-medium text-amber-700 dark:text-amber-300">
                                    <ExclamationTriangleIcon class="h-3 w-3" />
                                    Sin ciclo activo
                                </span>
                            </div>
                        </div>

                        <!-- Servicios -->
                        <div class="p-4 space-y-3">
                            <div v-if="row.services.length === 0" class="text-sm text-gray-500 dark:text-zinc-400">
                                Aún no se ha abierto el ciclo de este mes.
                            </div>
                            <div v-for="svc in row.services" :key="svc.id">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-medium text-gray-700 dark:text-zinc-300">
                                        <span class="inline-block rounded bg-gray-100 dark:bg-zinc-700 px-1.5 py-0.5 mr-1 font-mono text-[10px]">
                                            {{ svc.prefix }}
                                        </span>
                                        {{ svc.name }}
                                    </span>
                                    <span class="text-gray-500 dark:text-zinc-400">
                                        <template v-if="svc.is_unlimited">{{ svc.tickets_count }} / ∞</template>
                                        <template v-else>{{ svc.consumed }} / {{ svc.capacity }}</template>
                                    </span>
                                </div>
                                <div v-if="!svc.is_unlimited" class="mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-zinc-700 overflow-hidden">
                                    <div :class="['h-full transition-all', progressColor(progressPct(svc))]"
                                        :style="{ width: progressPct(svc) + '%' }"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer stats -->
                        <div class="border-t border-gray-200 dark:border-zinc-700 px-4 py-3 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-3">
                                <span v-if="row.totals.overdue > 0" class="inline-flex items-center gap-1 text-red-600 dark:text-red-400">
                                    🔴 {{ row.totals.overdue }} vencidos
                                </span>
                                <span v-if="row.totals.in_review > 0" class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400">
                                    🟡 {{ row.totals.in_review }} en revisión
                                </span>
                                <span v-if="row.totals.overdue === 0 && row.totals.in_review === 0"
                                    class="text-emerald-600 dark:text-emerald-400">
                                    ✓ Todo en orden
                                </span>
                            </div>
                            <Link :href="route('recurring.clients.show', row.client_id)"
                                class="font-medium text-[#264ab3] dark:text-blue-400 hover:underline">
                                Ver detalle →
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
