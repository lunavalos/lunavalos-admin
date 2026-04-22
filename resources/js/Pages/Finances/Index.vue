<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import {
    DocumentCurrencyDollarIcon,
    CalendarDaysIcon,
    PrinterIcon,
    EnvelopeIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    UserGroupIcon,
    ExclamationTriangleIcon,
    BanknotesIcon,
    ChartBarIcon,
    MagnifyingGlassIcon,
    ArrowDownTrayIcon,
} from '@heroicons/vue/24/outline';

import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';
import { Bar, Doughnut, Line } from 'vue-chartjs';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

const props = defineProps({
    upcomingRenewals:    Array,
    currentRange:        String,
    kpis:                Object,
    monthlyIncomeChart:  Array,
    serviceDistribution: Object,
    costVsIncomeChart:   Array,
    clientStatusChart:   Object,
});

// ── Filtros ───────────────────────────────────────────────────────────────────
const filters = ref([
    { label: 'Próximos 30 días',             value: 'next_30_days' },
    { label: 'Este Mes',                     value: 'this_month' },
    { label: 'Próximo Mes',                  value: 'next_month' },
    { label: 'Recientes y Próximos (Default)', value: 'default' },
    { label: 'Todos los registros',          value: 'all' },
]);

const changeRange = (e) => {
    router.get(route('finances.index'), { range: e.target.value }, { preserveState: true, preserveScroll: true });
};

// ── Búsqueda en tabla ─────────────────────────────────────────────────────────
const search = ref('');
const filteredRenewals = computed(() => {
    if (!search.value) return props.upcomingRenewals;
    const q = search.value.toLowerCase();
    return props.upcomingRenewals.filter(
        (r) => r.business_name?.toLowerCase().includes(q) || r.service_name?.toLowerCase().includes(q)
    );
});

// ── Helpers ───────────────────────────────────────────────────────────────────
const formatCurrency = (amount) => {
    if (!amount) return '$0.00';
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const dateStr = String(dateString).split(' ')[0].split('T')[0];
    const date = new Date(dateStr + 'T12:00:00');
    if (isNaN(date.getTime())) return dateString;
    return new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
};

const isExpired = (dateString) => {
    if (!dateString) return false;
    const dateStr = String(dateString).split(' ')[0].split('T')[0];
    const date = new Date(dateStr + 'T12:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return date < today;
};

const daysUntil = (dateString) => {
    if (!dateString) return null;
    const dateStr = String(dateString).split(' ')[0].split('T')[0];
    const date = new Date(dateStr + 'T12:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return Math.round((date - today) / (1000 * 60 * 60 * 24));
};

const urgencyClass = (dateString) => {
    const d = daysUntil(dateString);
    if (d === null) return '';
    if (d < 0)  return 'text-red-600';
    if (d <= 7) return 'text-orange-500';
    if (d <= 30) return 'text-yellow-500';
    return 'text-green-600';
};

const urgencyDot = (dateString) => {
    const d = daysUntil(dateString);
    if (d === null) return 'bg-gray-400';
    if (d < 0)  return 'bg-red-500';
    if (d <= 7) return 'bg-orange-500';
    if (d <= 30) return 'bg-yellow-400';
    return 'bg-green-500';
};

// ── Enviar email ──────────────────────────────────────────────────────────────
const sendEmail = (item) => {
    if (!item.email) {
        alert(`No hay ningún correo asociado al cliente ${item.business_name}.`);
        return;
    }
    if (!confirm(`¿Estás seguro de enviar el aviso de cobro por correo a ${item.email}?`)) return;
    router.post(
        route('finances.send-receipt', item.client_id),
        { service: item.service_name, amount: item.renewal_amount, type: item.billing_type },
        { preserveScroll: true, preserveState: true }
    );
};

// ── Exportar CSV ──────────────────────────────────────────────────────────────
const exportCSV = () => {
    const headers = ['Cliente', 'Contacto', 'Servicio', 'Fecha Renovación', 'Monto', 'Tipo'];
    const rows = filteredRenewals.value.map((r) => [
        `"${r.business_name}"`,
        `"${r.contact_name}"`,
        `"${r.service_name}"`,
        formatDate(r.next_renewal_date),
        r.renewal_amount || 0,
        r.billing_type,
    ]);
    const csv = [headers, ...rows].map((r) => r.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `renovaciones_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};

// ── Animación de KPIs (counter) ───────────────────────────────────────────────
const animatedKpis = ref({
    income_this_month:    0,
    income_next_30:       0,
    annual_projected:     0,
    active_clients:       0,
    overdue_clients:      0,
    net_profit_estimated: 0,
});

const animateCounter = (key, target, isCurrency = false) => {
    const duration = 1200;
    const steps    = 60;
    const stepTime = duration / steps;
    let current    = 0;
    const increment = target / steps;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        animatedKpis.value[key] = current;
    }, stepTime);
};

const isDark = ref(false);
let observer = null;

onMounted(() => {
    if (props.kpis) {
        Object.entries(props.kpis).forEach(([key, val]) => {
            animateCounter(key, val);
        });
    }

    isDark.value = document.documentElement.classList.contains('dark');
    observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'class') {
                isDark.value = document.documentElement.classList.contains('dark');
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });
});

onUnmounted(() => {
    if (observer) observer.disconnect();
});

// ─── COLORES base ─────────────────────────────────────────────────────────────
const palette = {
    green:       'rgba(52, 211, 153, 1)',  // emerald-400
    greenFade:   'rgba(52, 211, 153, 0.15)',
    blue:        'rgba(96, 165, 250, 1)',   // blue-400
    blueFade:    'rgba(96, 165, 250, 0.15)',
    red:         'rgba(251, 113, 133, 1)',   // rose-400
    redFade:     'rgba(251, 113, 133, 0.15)',
    orange:      'rgba(251, 146, 60, 1)',    // orange-400
    purple:      'rgba(167, 139, 250, 1)',   // violet-400
    teal:        'rgba(45, 212, 191, 1)',    // teal-400
    yellow:      'rgba(250, 204, 21, 1)',    // yellow-400
};

const multiColors = [
    palette.green, palette.blue, palette.purple,
    palette.orange, palette.teal, palette.yellow,
    palette.red, 'rgba(236, 72, 153, 1)', 'rgba(14, 165, 233, 1)',
];

const chartDefaults = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: isDark.value ? 'rgba(30, 41, 59, 0.97)' : 'rgba(255,255,255,0.97)',
            titleColor: isDark.value ? '#f1f5f9' : '#1e293b',
            bodyColor: isDark.value ? '#cbd5e1' : '#475569',
            borderColor: isDark.value ? '#334155' : '#e2e8f0',
            borderWidth: 1,
            cornerRadius: 8,
            padding: 10,
        },
    },
}));

// ─── Gráfica 1: Ingresos mensuales ───────────────────────────────────────────
const barChartData = computed(() => {
    if (!props.monthlyIncomeChart) return null;
    return {
        labels: props.monthlyIncomeChart.map((m) => m.label),
        datasets: [{
            label: 'Ingresos',
            data:  props.monthlyIncomeChart.map((m) => m.income),
            backgroundColor: props.monthlyIncomeChart.map((m) =>
                m.isCurrent ? palette.green :
                m.isPast    ? 'rgba(34, 197, 94, 0.35)' :
                              'rgba(59, 130, 246, 0.55)'
            ),
            borderRadius: 6,
            borderSkipped: false,
        }],
    };
});

const barChartOptions = computed(() => ({
    ...chartDefaults.value,
    plugins: {
        ...chartDefaults.value.plugins,
        legend: { display: false },
        tooltip: {
            ...chartDefaults.value.plugins.tooltip,
            callbacks: {
                label: (ctx) => ' ' + formatCurrency(ctx.raw),
            },
        },
    },
    scales: {
        x: {
            grid: { color: isDark.value ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.04)' },
            ticks: { color: isDark.value ? '#94a3b8' : '#64748b', font: { size: 11 }, maxRotation: 45 },
        },
        y: {
            grid: { color: isDark.value ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)' },
            ticks: {
                color: isDark.value ? '#94a3b8' : '#64748b',
                font: { size: 11 },
                callback: (v) => formatCurrency(v),
            },
        },
    },
}));

// ─── Gráfica 2: Distribución por servicio ────────────────────────────────────
const doughnutData = computed(() => {
    if (!props.serviceDistribution) return null;
    const keys = Object.keys(props.serviceDistribution);
    return {
        labels: keys,
        datasets: [{
            data:            keys.map((k) => props.serviceDistribution[k]),
            backgroundColor: multiColors.slice(0, keys.length),
            borderWidth: 2,
            borderColor: '#ffffff',
            hoverOffset: 8,
        }],
    };
});

const doughnutOptions = computed(() => ({
    ...chartDefaults.value,
    plugins: {
        ...chartDefaults.value.plugins,
        legend: {
            display: true,
            position: 'bottom',
            labels: { color: isDark.value ? '#cbd5e1' : '#475569', padding: 14, font: { size: 11 }, boxWidth: 12 },
        },
        tooltip: {
            ...chartDefaults.value.plugins.tooltip,
            callbacks: {
                label: (ctx) => `  ${ctx.label}: ${formatCurrency(ctx.raw)}`,
            },
        },
    },
}));

// ─── Gráfica 3: Ingresos vs Costos ───────────────────────────────────────────
const lineChartData = computed(() => {
    if (!props.costVsIncomeChart) return null;
    return {
        labels: props.costVsIncomeChart.map((m) => m.label),
        datasets: [
            {
                label: 'Ingresos',
                data:  props.costVsIncomeChart.map((m) => m.income),
                borderColor: palette.green,
                backgroundColor: palette.greenFade,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: palette.green,
                pointRadius: 4,
            },
            {
                label: 'Costos Servicios',
                data:  props.costVsIncomeChart.map((m) => m.costsServices),
                borderColor: palette.orange,
                backgroundColor: 'rgba(249, 115, 22, 0.10)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: palette.orange,
                pointRadius: 3,
            },
            {
                label: 'Nómina',
                data:  props.costVsIncomeChart.map((m) => m.costsPayroll),
                borderColor: palette.red,
                backgroundColor: palette.redFade,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: palette.red,
                pointRadius: 3,
            },
            {
                label: 'Ganancia',
                data:  props.costVsIncomeChart.map((m) => m.profit),
                borderColor: palette.blue,
                backgroundColor: 'transparent',
                tension: 0.4,
                fill: false,
                borderDash: [5, 4],
                pointBackgroundColor: palette.blue,
                pointRadius: 3,
            },
        ],
    };
});

const lineChartOptions = computed(() => ({
    ...chartDefaults.value,
    plugins: {
        ...chartDefaults.value.plugins,
        legend: {
            display: true,
            position: 'top',
            labels: { color: isDark.value ? '#cbd5e1' : '#475569', padding: 16, font: { size: 11 }, boxWidth: 12 },
        },
        tooltip: {
            ...chartDefaults.value.plugins.tooltip,
            callbacks: {
                label: (ctx) => `  ${ctx.dataset.label}: ${formatCurrency(ctx.raw)}`,
            },
        },
    },
    scales: {
        x: {
            grid: { color: isDark.value ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.04)' },
            ticks: { color: isDark.value ? '#94a3b8' : '#64748b', font: { size: 11 } },
        },
        y: {
            grid: { color: isDark.value ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)' },
            ticks: {
                color: isDark.value ? '#94a3b8' : '#64748b',
                font: { size: 11 },
                callback: (v) => formatCurrency(v),
            },
        },
    },
}));

// ─── Gráfica 4: Estado de clientes ───────────────────────────────────────────
const statusChartData = computed(() => {
    if (!props.clientStatusChart) return null;
    const keys = Object.keys(props.clientStatusChart);
    return {
        labels: keys,
        datasets: [{
            data: keys.map((k) => props.clientStatusChart[k]),
            backgroundColor: [palette.green, palette.yellow, palette.red],
            borderWidth: 2,
            borderColor: '#ffffff',
            hoverOffset: 8,
        }],
    };
});

const statusChartOptions = computed(() => ({
    ...chartDefaults,
    plugins: {
        ...chartDefaults.plugins,
        legend: {
            display: true,
            position: 'bottom',
            labels: { color: '#475569', padding: 14, font: { size: 11 }, boxWidth: 12 },
        },
    },
}));
</script>

<template>
    <Head title="Finanzas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100 flex items-center gap-2">
                <DocumentCurrencyDollarIcon class="h-6 w-6 text-green-600 dark:text-teal-400" />
                Finanzas &amp; Renovaciones
            </h2>
        </template>

        <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            <!-- ── KPI CARDS ───────────────────────────────────────────────── -->
            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4">

                <!-- Ingresos del mes -->
                <div class="bg-gradient-to-br from-emerald-400 to-emerald-500 rounded-2xl p-5 text-white shadow-lg col-span-1">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-semibold uppercase tracking-wider opacity-80">Este mes</span>
                        <BanknotesIcon class="h-5 w-5 opacity-70" />
                    </div>
                    <div class="text-2xl font-extrabold leading-tight">
                        {{ formatCurrency(animatedKpis.income_this_month) }}
                    </div>
                    <div class="text-xs opacity-70 mt-1">Ingresos del mes actual</div>
                </div>

                <!-- Próximos 30 días -->
                <div class="bg-gradient-to-br from-blue-400 to-blue-500 rounded-2xl p-5 text-white shadow-lg col-span-1">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-semibold uppercase tracking-wider opacity-80">30 días</span>
                        <CalendarDaysIcon class="h-5 w-5 opacity-70" />
                    </div>
                    <div class="text-2xl font-extrabold leading-tight">
                        {{ formatCurrency(animatedKpis.income_next_30) }}
                    </div>
                    <div class="text-xs opacity-70 mt-1">Cobros próximos</div>
                </div>

                <!-- Anual proyectado -->
                <div class="bg-gradient-to-br from-violet-400 to-violet-500 rounded-2xl p-5 text-white shadow-lg col-span-1">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-semibold uppercase tracking-wider opacity-80">Anual</span>
                        <ArrowTrendingUpIcon class="h-5 w-5 opacity-70" />
                    </div>
                    <div class="text-2xl font-extrabold leading-tight">
                        {{ formatCurrency(animatedKpis.annual_projected) }}
                    </div>
                    <div class="text-xs opacity-70 mt-1">Ingreso proyectado</div>
                </div>

                <!-- Clientes activos -->
                <div class="bg-gradient-to-br from-teal-400 to-cyan-500 rounded-2xl p-5 text-white shadow-lg col-span-1">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-semibold uppercase tracking-wider opacity-80">Activos</span>
                        <UserGroupIcon class="h-5 w-5 opacity-70" />
                    </div>
                    <div class="text-2xl font-extrabold leading-tight">
                        {{ Math.round(animatedKpis.active_clients) }}
                    </div>
                    <div class="text-xs opacity-70 mt-1">Clientes al corriente</div>
                </div>

                <!-- Vencidos -->
                <div class="bg-gradient-to-br from-rose-400 to-rose-500 rounded-2xl p-5 text-white shadow-lg col-span-1">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-semibold uppercase tracking-wider opacity-80">Vencidos</span>
                        <ExclamationTriangleIcon class="h-5 w-5 opacity-70" />
                    </div>
                    <div class="text-2xl font-extrabold leading-tight">
                        {{ Math.round(animatedKpis.overdue_clients) }}
                    </div>
                    <div class="text-xs opacity-70 mt-1">Requieren atención</div>
                </div>

                <!-- Ganancia neta estimada -->
                <div
                    class="rounded-2xl p-5 text-white shadow-lg col-span-1"
                    :class="kpis?.net_profit_estimated >= 0
                        ? 'bg-gradient-to-br from-orange-400 to-amber-500'
                        : 'bg-gradient-to-br from-zinc-500 to-zinc-600'"
                >
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-semibold uppercase tracking-wider opacity-80">Ganancia</span>
                        <ChartBarIcon class="h-5 w-5 opacity-70" />
                    </div>
                    <div class="text-2xl font-extrabold leading-tight">
                        {{ formatCurrency(animatedKpis.net_profit_estimated) }}
                    </div>
                    <div class="text-xs opacity-70 mt-1">Neta estimada (anual)</div>
                    <div v-if="kpis?.monthly_payroll" class="text-[10px] opacity-60 mt-1.5 border-t border-white/20 pt-1.5">
                        Nómina: {{ formatCurrency(kpis.monthly_payroll) }}/mes
                    </div>
                </div>
            </div>

            <!-- ── GRÁFICAS row 1 ─────────────────────────────────────────── -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

                <!-- Gráfica 1: Ingresos mensuales (ocupa 2 columnas) -->
                <div class="xl:col-span-2 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h3 class="text-gray-800 dark:text-gray-100 font-bold text-sm">Ingresos por Mes</h3>
                            <p class="text-gray-400 dark:text-zinc-500 text-xs mt-0.5">Últimos 12 meses + Proyección 6 meses</p>
                        </div>
                        <span class="text-xs bg-green-50 dark:bg-emerald-900/20 text-green-700 dark:text-emerald-400 border border-green-200 dark:border-emerald-800 rounded-full px-3 py-1 font-medium">
                            Renovaciones
                        </span>
                    </div>
                    <div class="h-64">
                        <Bar v-if="barChartData" :data="barChartData" :options="barChartOptions" />
                    </div>
                    <div class="flex items-center gap-4 mt-4 text-xs text-gray-500">
                        <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-green-300"></span> Histórico</span>
                        <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-green-500"></span> Mes actual</span>
                        <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-blue-400"></span> Proyectado</span>
                    </div>
                </div>

                <!-- Gráfica 2: Distribución por servicio -->
                <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm">
                    <div class="mb-5">
                        <h3 class="text-gray-800 dark:text-gray-100 font-bold text-sm">Ingresos por Servicio</h3>
                        <p class="text-gray-400 dark:text-zinc-500 text-xs mt-0.5">Distribución de facturación</p>
                    </div>
                    <div class="h-64">
                        <Doughnut v-if="doughnutData" :data="doughnutData" :options="doughnutOptions" />
                    </div>
                </div>
            </div>

            <!-- ── GRÁFICAS row 2 ─────────────────────────────────────────── -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

                <!-- Gráfica 3: Ingresos vs Costos (2 columnas) -->
                <div class="xl:col-span-2 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h3 class="text-gray-800 dark:text-gray-100 font-bold text-sm">Ingresos vs Costos Internos</h3>
                            <p class="text-gray-400 dark:text-zinc-500 text-xs mt-0.5">Servicios internos + Nómina vs Ingresos (últimos 12 meses)</p>
                        </div>
                        <span v-if="kpis?.monthly_payroll" class="text-xs bg-red-50 dark:bg-rose-900/20 text-red-600 dark:text-rose-400 border border-red-200 dark:border-rose-800 rounded-full px-3 py-1 font-medium whitespace-nowrap">
                            Nómina: {{ formatCurrency(kpis.monthly_payroll) }}/mes
                        </span>
                    </div>
                    <div class="h-64">
                        <Line v-if="lineChartData" :data="lineChartData" :options="lineChartOptions" />
                    </div>
                </div>

                <!-- Gráfica 4: Estado de clientes -->
                <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm">
                    <div class="mb-5">
                        <h3 class="text-gray-800 dark:text-gray-100 font-bold text-sm">Estado de Clientes</h3>
                        <p class="text-gray-400 dark:text-zinc-500 text-xs mt-0.5">Al corriente · Por vencer · Vencidos</p>
                    </div>
                    <div class="h-64">
                        <Doughnut v-if="statusChartData" :data="statusChartData" :options="statusChartOptions" />
                    </div>
                </div>
            </div>

            <!-- ── TABLA DE RENOVACIONES ───────────────────────────────────── -->
            <div class="bg-white dark:bg-zinc-900 shadow-sm border border-gray-200 dark:border-zinc-800 rounded-2xl overflow-hidden">

                <!-- Barra superior de tabla -->
                <div class="px-6 py-4 border-b border-gray-100 dark:border-zinc-800 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-bold text-gray-800 dark:text-gray-100 text-sm flex items-center gap-2">
                        <CalendarDaysIcon class="h-4 w-4 text-green-600 dark:text-teal-400" />
                        Detalle de Renovaciones
                        <span class="ml-1 text-xs bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-zinc-400 rounded-full px-2 py-0.5">
                            {{ filteredRenewals.length }} registro{{ filteredRenewals.length !== 1 ? 's' : '' }}
                        </span>
                    </h3>
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Filtro de rango de fechas -->
                        <div class="flex items-center gap-1.5">
                            <CalendarDaysIcon class="h-3.5 w-3.5 text-gray-400 dark:text-zinc-500 shrink-0" />
                            <select
                                :value="currentRange"
                                @change="changeRange"
                                class="text-xs border border-gray-300 dark:border-zinc-600 rounded-lg focus:ring-green-500 focus:border-green-500 py-2 px-2 shadow-sm bg-white dark:bg-zinc-800 text-gray-700 dark:text-gray-200"
                            >
                                <option v-for="filter in filters" :key="filter.value" :value="filter.value">
                                    {{ filter.label }}
                                </option>
                            </select>
                        </div>
                        <!-- Buscador -->
                        <div class="relative">
                            <MagnifyingGlassIcon class="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-gray-400 dark:text-zinc-500" />
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Buscar cliente o servicio…"
                                class="pl-8 pr-3 py-2 text-xs border border-gray-300 dark:border-zinc-600 rounded-lg focus:ring-green-500 focus:border-green-500 w-52 bg-white dark:bg-zinc-800 text-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-zinc-500"
                            />
                        </div>
                        <!-- Exportar CSV -->
                        <button
                            @click="exportCSV"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-600 dark:text-zinc-300 bg-gray-100 dark:bg-zinc-800 hover:bg-gray-200 dark:hover:bg-zinc-600 rounded-lg transition"
                            title="Exportar a CSV"
                        >
                            <ArrowDownTrayIcon class="h-3.5 w-3.5" />
                            CSV
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-zinc-800">
                        <thead class="bg-gray-50 dark:bg-zinc-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Cliente</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Servicio</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Fecha Renovación</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Días</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Monto</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-zinc-900 divide-y divide-gray-100 dark:divide-zinc-800">
                            <tr v-if="filteredRenewals.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center text-gray-400 text-sm italic">
                                    No se encontraron renovaciones para el rango o búsqueda seleccionada.
                                </td>
                            </tr>
                            <tr
                                v-for="(item, index) in filteredRenewals"
                                :key="index"
                                class="hover:bg-green-50/40 dark:hover:bg-emerald-900/10 transition-colors"
                            >
                                <!-- Indicador de urgencia -->
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-block w-2.5 h-2.5 rounded-full"
                                        :class="urgencyDot(item.next_renewal_date)"
                                        :title="isExpired(item.next_renewal_date) ? 'Vencido' : `${daysUntil(item.next_renewal_date)} días restantes`"
                                    ></span>
                                </td>

                                <!-- Cliente -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-bold text-gray-800 dark:text-gray-200 text-sm">{{ item.business_name }}</div>
                                    <div class="text-xs text-gray-400 dark:text-zinc-500 mt-0.5">{{ item.contact_name }}</div>
                                </td>

                                <!-- Servicio -->
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        :class="item.billing_type === 'monthly'
                                            ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400'
                                            : 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400'"
                                    >
                                        {{ item.service_name || 'PND' }}
                                        <span v-if="item.billing_type === 'monthly'" class="ml-1 opacity-75">(Mensual)</span>
                                    </span>
                                </td>

                                <!-- Fecha -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-sm font-semibold"
                                            :class="isExpired(item.next_renewal_date) ? 'text-red-600 dark:text-rose-400' : 'text-gray-700 dark:text-gray-300'"
                                        >
                                            {{ formatDate(item.next_renewal_date) }}
                                        </span>
                                        <span
                                            v-if="isExpired(item.next_renewal_date)"
                                            class="text-[10px] bg-red-100 dark:bg-rose-900/40 text-red-700 dark:text-rose-400 px-1.5 py-0.5 rounded-full font-bold uppercase"
                                        >
                                            Vencido
                                        </span>
                                    </div>
                                </td>

                                <!-- Días restantes -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="text-sm font-bold tabular-nums"
                                        :class="urgencyClass(item.next_renewal_date)"
                                    >
                                        <template v-if="daysUntil(item.next_renewal_date) !== null">
                                            {{ daysUntil(item.next_renewal_date) < 0
                                                ? `${Math.abs(daysUntil(item.next_renewal_date))}d atrás`
                                                : `${daysUntil(item.next_renewal_date)}d` }}
                                        </template>
                                        <template v-else>—</template>
                                    </span>
                                </td>

                                <!-- Monto -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-bold text-gray-800 dark:text-gray-100">
                                        {{ formatCurrency(item.renewal_amount || 0) }}
                                    </span>
                                </td>

                                <!-- Acciones -->
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="flex items-center justify-end space-x-2">
                                        <a
                                            :href="route('finances.receipt', {
                                                client:  item.client_id,
                                                service: item.service_name,
                                                amount:  item.renewal_amount,
                                                type:    item.billing_type
                                            })"
                                            target="_blank"
                                            class="inline-flex items-center justify-center px-3 py-2 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-lg font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-zinc-600 transition"
                                            title="Ver / Imprimir Recibo"
                                        >
                                            <PrinterIcon class="h-4 w-4 mr-1" />PDF
                                        </a>
                                        <button
                                            type="button"
                                            @click="sendEmail(item)"
                                            class="inline-flex items-center justify-center px-3 py-2 bg-[#264ab3] border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-[#193074] transition shadow-sm"
                                            title="Enviar por Email"
                                        >
                                            <EnvelopeIcon class="h-4 w-4 mr-1" />Enviar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer de tabla con total -->
                <div v-if="filteredRenewals.length > 0" class="px-6 py-3 bg-gray-50 dark:bg-zinc-900/80 border-t border-gray-100 dark:border-zinc-800 flex items-center justify-between text-xs text-gray-500 dark:text-zinc-400">
                    <span>Total en la vista actual:</span>
                    <span class="font-bold text-gray-800 dark:text-gray-100 text-sm">
                        {{ formatCurrency(filteredRenewals.reduce((s, r) => s + (parseFloat(r.renewal_amount) || 0), 0)) }}
                    </span>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
