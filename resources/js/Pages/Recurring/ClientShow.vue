<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ArrowPathIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    client:   { type: Object, required: true },
    contract: { type: Object, required: true },
    cycle:    { type: Object, default: null },
    credits:  { type: Array,  default: () => [] },
    tickets:  { type: Array,  default: () => [] },
    history:  { type: Array,  default: () => [] },
});

const activeTab = ref('production');

const columns = [
    { key: 'Nuevos',      label: 'Backlog',     color: 'bg-gray-100 dark:bg-zinc-700' },
    { key: 'En Proceso',  label: 'En Proceso',  color: 'bg-blue-100 dark:bg-blue-900/30' },
    { key: 'En Revisión', label: 'En Revisión', color: 'bg-purple-100 dark:bg-purple-900/30' },
    { key: 'Ajustes',     label: 'Ajustes',     color: 'bg-amber-100 dark:bg-amber-900/30' },
    { key: 'Completados', label: 'Completados', color: 'bg-emerald-100 dark:bg-emerald-900/30' },
];

const ticketsByStatus = computed(() => {
    const map = {};
    columns.forEach(c => map[c.key] = []);
    (props.tickets || []).forEach(t => {
        if (map[t.status]) map[t.status].push(t);
    });
    return map;
});

function progressColor(pct) {
    if (pct >= 90) return 'bg-red-500';
    if (pct >= 70) return 'bg-amber-500';
    return 'bg-emerald-500';
}
function progressPct(c) {
    if (c.is_unlimited || c.capacity === 0) return 0;
    return Math.min(100, Math.round((c.consumed / c.capacity) * 100));
}

function openCycle() {
    if (!confirm('¿Abrir el ciclo del mes actual para este cliente?')) return;
    router.post(route('recurring.clients.openCycle', props.client.id));
}

// On-demand deliverable modal
const showModal = ref(false);
const form = useForm({
    title: '',
    priority: 'Media',
    content: '',
    due_date: '',
    deliverable_credit_id: null,
});
function submitDeliverable() {
    form.post(route('recurring.clients.deliverables.store', props.client.id), {
        onSuccess: () => { showModal.value = false; form.reset(); },
    });
}

const onDemandCredits = computed(() =>
    props.credits.filter(c => c.unit_type === 'on_demand_pool' || c.unit_type === 'unlimited')
);
</script>

<template>
    <Head :title="`Recurrente — ${client.business_name}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <Link :href="route('recurring.index')"
                        class="text-xs text-gray-500 dark:text-zinc-400 hover:text-[#264ab3]">
                        ← Clientes Recurrentes
                    </Link>
                    <h2 class="mt-1 text-xl font-semibold text-gray-800 dark:text-zinc-100">
                        {{ client.business_name }}
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-zinc-400">
                        Contrato {{ contract.contract_number }} ·
                        Mensualidad ${{ Number(contract.monthly_amount || 0).toLocaleString() }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span v-if="cycle"
                        class="inline-flex items-center gap-1 rounded-full bg-blue-50 dark:bg-blue-900/30 px-3 py-1 text-xs font-medium text-blue-700 dark:text-blue-300">
                        <ArrowPathIcon class="h-3 w-3" /> Ciclo {{ cycle.label }}
                    </span>
                    <button v-else @click="openCycle"
                        class="inline-flex items-center gap-1 rounded-md bg-[#264ab3] px-3 py-1.5 text-xs font-medium text-white hover:bg-[#1e3a8a]">
                        <ArrowPathIcon class="h-4 w-4" /> Abrir ciclo del mes
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Resumen de créditos -->
                <div v-if="credits.length"
                    class="mb-6 rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
                    <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-zinc-200">
                        Resumen del ciclo
                    </h3>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div v-for="c in credits" :key="c.id" class="rounded-md border border-gray-100 dark:border-zinc-700 p-3">
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-medium text-gray-800 dark:text-zinc-100">
                                    <span class="inline-block rounded bg-gray-100 dark:bg-zinc-700 px-1.5 py-0.5 mr-1 font-mono text-[10px]">
                                        {{ c.prefix }}
                                    </span>
                                    {{ c.name }}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-zinc-400">
                                    <template v-if="c.is_unlimited">∞ ilimitado</template>
                                    <template v-else>{{ c.consumed }} / {{ c.capacity }}</template>
                                </span>
                            </div>
                            <div v-if="!c.is_unlimited" class="mt-2 h-1.5 rounded-full bg-gray-100 dark:bg-zinc-700 overflow-hidden">
                                <div :class="['h-full transition-all', progressColor(progressPct(c))]"
                                    :style="{ width: progressPct(c) + '%' }"></div>
                            </div>
                            <div class="mt-2 flex gap-2 text-[10px] text-gray-500 dark:text-zinc-400">
                                <span>Backlog: {{ c.by_status.Nuevos }}</span>
                                <span>· En proceso: {{ c.by_status['En Proceso'] }}</span>
                                <span>· Listo: {{ c.by_status.Completados }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="border-b border-gray-200 dark:border-zinc-700 mb-4">
                    <nav class="flex gap-4 text-sm">
                        <button @click="activeTab = 'production'"
                            :class="[activeTab === 'production'
                                ? 'border-[#264ab3] text-[#264ab3] dark:text-blue-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700',
                                'border-b-2 px-1 py-2 font-medium']">
                            Producción
                        </button>
                        <button @click="activeTab = 'history'"
                            :class="[activeTab === 'history'
                                ? 'border-[#264ab3] text-[#264ab3] dark:text-blue-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700',
                                'border-b-2 px-1 py-2 font-medium']">
                            Historial de ciclos
                        </button>
                        <button disabled title="Próximamente"
                            class="border-b-2 border-transparent px-1 py-2 font-medium text-gray-300 dark:text-zinc-600 cursor-not-allowed">
                            Calendario (próx.)
                        </button>
                        <button disabled title="Próximamente"
                            class="border-b-2 border-transparent px-1 py-2 font-medium text-gray-300 dark:text-zinc-600 cursor-not-allowed">
                            Analytics (próx.)
                        </button>
                    </nav>
                </div>

                <!-- Tab: Producción (Kanban filtrado) -->
                <div v-if="activeTab === 'production'">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-xs text-gray-500 dark:text-zinc-400">
                            Tablero limitado a entregables del ciclo actual.
                        </p>
                        <button v-if="cycle" @click="showModal = true"
                            class="inline-flex items-center gap-1 rounded-md bg-[#264ab3] px-3 py-1.5 text-xs font-medium text-white hover:bg-[#1e3a8a]">
                            <PlusIcon class="h-4 w-4" /> Nuevo entregable
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-5">
                        <div v-for="col in columns" :key="col.key"
                            class="rounded-md border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 flex flex-col min-h-[200px]">
                            <div :class="['rounded-t-md px-3 py-2 text-xs font-semibold text-gray-700 dark:text-zinc-200', col.color]">
                                {{ col.label }}
                                <span class="ml-1 text-[10px] text-gray-500 dark:text-zinc-400">
                                    ({{ ticketsByStatus[col.key].length }})
                                </span>
                            </div>
                            <div class="p-2 space-y-2 flex-1">
                                <Link v-for="t in ticketsByStatus[col.key]" :key="t.id"
                                    :href="route('tickets.show', t.id)"
                                    class="block rounded border border-gray-100 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-900 p-2 hover:border-[#264ab3] hover:shadow-sm transition">
                                    <div class="flex items-center justify-between text-[10px] text-gray-500 dark:text-zinc-400 mb-1">
                                        <span class="font-mono">{{ t.code || `#${t.id}` }}</span>
                                        <span :class="{
                                            'text-red-500': t.priority === 'Urgente',
                                            'text-amber-500': t.priority === 'Alta',
                                        }">{{ t.priority }}</span>
                                    </div>
                                    <div class="text-xs text-gray-800 dark:text-zinc-100 line-clamp-2">{{ t.title }}</div>
                                    <div v-if="t.assigned" class="mt-1 text-[10px] text-gray-500 dark:text-zinc-400">
                                        → {{ t.assigned.name }}
                                    </div>
                                </Link>
                                <div v-if="ticketsByStatus[col.key].length === 0"
                                    class="text-[10px] text-gray-400 dark:text-zinc-600 text-center py-4">
                                    —
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Historial -->
                <div v-else-if="activeTab === 'history'">
                    <div class="rounded-md border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                            <thead class="bg-gray-50 dark:bg-zinc-900">
                                <tr class="text-xs text-gray-500 dark:text-zinc-400 uppercase tracking-wide">
                                    <th class="px-4 py-2 text-left">Ciclo</th>
                                    <th class="px-4 py-2 text-left">Estado</th>
                                    <th class="px-4 py-2 text-right">Tickets</th>
                                    <th class="px-4 py-2 text-right">Completados</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-zinc-700 text-sm">
                                <tr v-for="h in history" :key="h.id">
                                    <td class="px-4 py-2 text-gray-800 dark:text-zinc-100">{{ h.label }}</td>
                                    <td class="px-4 py-2">
                                        <span :class="[
                                            'inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium',
                                            h.status === 'active'
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                                : 'bg-gray-100 text-gray-600 dark:bg-zinc-700 dark:text-zinc-300'
                                        ]">
                                            {{ h.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-right text-gray-700 dark:text-zinc-300">{{ h.tickets_count }}</td>
                                    <td class="px-4 py-2 text-right text-gray-700 dark:text-zinc-300">{{ h.completed_count }}</td>
                                </tr>
                                <tr v-if="!history.length">
                                    <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-zinc-400">
                                        Aún no hay ciclos cerrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: nuevo entregable -->
        <div v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
            @click.self="showModal = false">
            <div class="w-full max-w-md rounded-lg bg-white dark:bg-zinc-800 p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-800 dark:text-zinc-100 mb-4">
                    Nuevo entregable
                </h3>
                <form @submit.prevent="submitDeliverable" class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-zinc-300">Título</label>
                        <input v-model="form.title" type="text" required
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 text-sm" />
                        <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-zinc-300">Prioridad</label>
                            <select v-model="form.priority"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 text-sm">
                                <option>Baja</option><option>Media</option><option>Alta</option><option>Urgente</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-zinc-300">Fecha límite</label>
                            <input v-model="form.due_date" type="date"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 text-sm" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-zinc-300">
                            Descontar de bolsa (opcional)
                        </label>
                        <select v-model="form.deliverable_credit_id"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 text-sm">
                            <option :value="null">— No descontar (excedente) —</option>
                            <option v-for="c in onDemandCredits" :key="c.id" :value="c.id">
                                {{ c.name }} ({{ c.is_unlimited ? '∞' : `${c.remaining}/${c.capacity}` }})
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-zinc-300">Descripción</label>
                        <textarea v-model="form.content" rows="3"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 text-sm"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showModal = false"
                            class="rounded-md border border-gray-300 dark:border-zinc-600 px-3 py-1.5 text-sm">Cancelar</button>
                        <button type="submit" :disabled="form.processing"
                            class="rounded-md bg-[#264ab3] px-3 py-1.5 text-sm text-white hover:bg-[#1e3a8a] disabled:opacity-50">
                            Crear
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
