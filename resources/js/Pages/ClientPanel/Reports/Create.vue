<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import {
    DocumentChartBarIcon,
    ChevronLeftIcon,
    CalendarDaysIcon,
    CheckBadgeIcon,
    BuildingOfficeIcon,
} from '@heroicons/vue/24/outline';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    client: Object,
});

// Default: current month
const now   = new Date();
const pad   = (n) => String(n).padStart(2, '0');
const defFrom = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`;
const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
const defTo   = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${lastDay}`;

const form = useForm({
    title:        '',
    period_month: now.getMonth() + 1,
    period_year:  now.getFullYear(),
    summary:      '',
    date_from:    defFrom,
    date_to:      defTo,
});

// Auto-generate title when dates change
const autoTitle = computed(() => {
    if (!form.date_from || !form.date_to) return '';
    const from = new Date(form.date_from + 'T00:00:00');
    const to   = new Date(form.date_to   + 'T00:00:00');
    const months = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    const fromStr = `${from.getDate()} ${months[from.getMonth()]}`;
    const toStr   = `${to.getDate()} ${months[to.getMonth()]} ${to.getFullYear()}`;
    return `Reporte ${props.client?.business_name} · ${fromStr} – ${toStr}`;
});

watch(autoTitle, (val) => { form.title = val; }, { immediate: true });

// Auto-update period from date_from
watch(() => form.date_from, (val) => {
    if (!val) return;
    const d = new Date(val + 'T00:00:00');
    form.period_month = d.getMonth() + 1;
    form.period_year  = d.getFullYear();
});

// Preview tickets
const previewTickets = ref([]);
const loadingPreview = ref(false);

const fetchPreview = async () => {
    if (!form.date_from || !form.date_to) { previewTickets.value = []; return; }
    loadingPreview.value = true;
    try {
        const url = route('client.my-tickets') + `?from=${form.date_from}&to=${form.date_to}`;
        const res = await fetch(url);
        previewTickets.value = await res.json();
    } catch (e) {
        previewTickets.value = [];
    } finally {
        loadingPreview.value = false;
    }
};

watch([() => form.date_from, () => form.date_to], fetchPreview);
fetchPreview();

const completedCount = computed(() =>
    previewTickets.value.filter(t => t.status === 'Completados').length
);

const statusColors = {
    'Nuevos':      'bg-blue-100 text-blue-700',
    'En Proceso':  'bg-yellow-100 text-yellow-700',
    'En Revisión': 'bg-purple-100 text-purple-700',
    'Ajustes':     'bg-orange-100 text-orange-700',
    'Completados': 'bg-green-100 text-green-700',
};

const submit = () => form.post(route('client.reports.store'));
</script>

<template>
    <Head title="Generar Reporte" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center">
                <Link :href="route('client.reports.index')" class="mr-4 p-2 bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-zinc-800 hover:border-[#264ab3] text-gray-500 hover:text-[#264ab3] transition-all">
                    <ChevronLeftIcon class="h-5 w-5" />
                </Link>
                <div>
                    <h2 class="text-2xl font-black text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <DocumentChartBarIcon class="h-7 w-7 text-[#264ab3] dark:text-blue-400" />
                        Generar Reporte
                    </h2>
                    <p class="text-sm text-gray-400 dark:text-zinc-500 mt-0.5">
                        <BuildingOfficeIcon class="h-3.5 w-3.5 inline mr-1" />
                        {{ client?.business_name }}
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- LEFT: form -->
                <div class="lg:col-span-2 space-y-6">

                    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-100 dark:border-zinc-800 p-6">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 text-xs uppercase tracking-widest mb-5">Rango de Fechas</h3>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <InputLabel value="Fecha de inicio" />
                                <TextInput
                                    v-model="form.date_from"
                                    type="date"
                                    class="mt-1 w-full border-gray-200 dark:border-zinc-800 focus:border-[#264ab3] rounded-xl dark:bg-zinc-950"
                                    required
                                />
                                <InputError class="mt-1" :message="form.errors.date_from" />
                            </div>
                            <div>
                                <InputLabel value="Fecha de fin" />
                                <TextInput
                                    v-model="form.date_to"
                                    type="date"
                                    class="mt-1 w-full border-gray-200 dark:border-zinc-800 focus:border-[#264ab3] rounded-xl dark:bg-zinc-950"
                                    required
                                />
                                <InputError class="mt-1" :message="form.errors.date_to" />
                            </div>
                        </div>

                        <div class="mb-4">
                            <InputLabel value="Título del Reporte" />
                            <TextInput
                                v-model="form.title"
                                type="text"
                                class="mt-1 w-full border-gray-200 dark:border-zinc-800 focus:border-[#264ab3] rounded-xl dark:bg-zinc-950"
                                required
                            />
                        </div>

                        <div>
                            <InputLabel value="Comentarios o contexto adicional (opcional)" />
                            <textarea
                                v-model="form.summary"
                                rows="3"
                                placeholder="¿Hay algo que quieras destacar en este reporte?"
                                class="mt-1 w-full border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 text-gray-700 dark:text-gray-300 rounded-xl shadow-sm focus:border-[#264ab3] focus:ring-[#264ab3] text-sm resize-none"
                            ></textarea>
                        </div>
                    </div>

                    <!-- Preview -->
                    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                        <div class="p-5 border-b border-gray-50 dark:border-zinc-800 flex items-center justify-between">
                            <h3 class="font-bold text-gray-700 dark:text-gray-200 text-xs uppercase tracking-widest flex items-center gap-1.5">
                                <CalendarDaysIcon class="h-4 w-4 text-[#264ab3] dark:text-blue-400" />
                                Actividades en el rango
                            </h3>
                            <span v-if="previewTickets.length" class="text-xs bg-blue-100 dark:bg-blue-900/40 text-[#264ab3] dark:text-blue-400 font-bold px-2.5 py-1 rounded-lg">
                                {{ previewTickets.length }} actividad{{ previewTickets.length !== 1 ? 'es' : '' }}
                            </span>
                        </div>

                        <div v-if="loadingPreview" class="p-12 text-center text-gray-300 dark:text-zinc-700">
                            <div class="animate-spin h-6 w-6 border-2 border-[#264ab3] border-t-transparent rounded-full mx-auto mb-2"></div>
                            <p class="text-sm">Buscando actividades...</p>
                        </div>

                        <div v-else-if="!previewTickets.length" class="p-12 text-center text-gray-300 dark:text-zinc-700">
                            <CalendarDaysIcon class="h-10 w-10 mx-auto mb-2 opacity-50" />
                            <p class="text-sm">No hay actividades en este rango de fechas</p>
                        </div>

                        <div v-else class="divide-y divide-gray-50 dark:divide-zinc-800">
                            <div v-for="ticket in previewTickets" :key="ticket.id" class="flex items-center gap-3 px-5 py-3">
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

                        <div v-if="previewTickets.length" class="px-5 py-3 bg-blue-50 dark:bg-blue-900/10 border-t border-blue-100 dark:border-blue-900/30">
                            <p class="text-xs text-[#264ab3] dark:text-blue-400">
                                ✓ Todas estas actividades se incluirán automáticamente en tu reporte.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: sidebar -->
                <div>
                    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-100 dark:border-zinc-800 p-5 sticky top-6">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 text-xs uppercase tracking-widest mb-4">Resumen</h3>

                        <div class="space-y-3 text-sm mb-6">
                            <div>
                                <span class="text-xs text-gray-400 dark:text-zinc-500 block">Empresa</span>
                                <span class="font-bold text-gray-800 dark:text-gray-100">{{ client?.business_name }}</span>
                            </div>
                            <div>
                                <span class="text-xs text-gray-400 dark:text-zinc-500 block">Periodo</span>
                                <span class="font-bold text-gray-800 dark:text-gray-100 text-xs">
                                    {{ form.date_from || '—' }} → {{ form.date_to || '—' }}
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 pt-2">
                                <div class="bg-gray-50 dark:bg-zinc-950 rounded-xl p-3 text-center border border-gray-100 dark:border-zinc-800">
                                    <div class="text-2xl font-black text-[#264ab3] dark:text-blue-400">{{ previewTickets.length }}</div>
                                    <div class="text-[10px] text-gray-400 dark:text-zinc-500 uppercase mt-0.5">Total</div>
                                </div>
                                <div class="bg-green-50 dark:bg-green-900/10 rounded-xl p-3 text-center border border-green-100 dark:border-green-900/30">
                                    <div class="text-2xl font-black text-green-600 dark:text-green-400">{{ completedCount }}</div>
                                    <div class="text-[10px] text-gray-400 dark:text-zinc-500 uppercase mt-0.5">Completadas</div>
                                </div>
                            </div>
                        </div>

                        <PrimaryButton
                            class="w-full justify-center !bg-[#264ab3] hover:!bg-[#193074] rounded-xl !shadow-none py-3"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing || !form.date_from || !form.date_to"
                        >
                            Generar Reporte
                        </PrimaryButton>

                        <Link :href="route('client.reports.index')" class="block mt-3 text-center text-sm text-gray-400 dark:text-zinc-500 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                            Cancelar
                        </Link>
                    </div>
                </div>

            </form>
        </div>
    </AuthenticatedLayout>
</template>
