<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    ArrowPathIcon, PlusIcon, ChevronLeftIcon, ChevronRightIcon,
    CalendarDaysIcon, LinkIcon, TrashIcon, PaperAirplaneIcon,
    PencilSquareIcon, MegaphoneIcon, ChartBarIcon, ArrowTrendingUpIcon,
    ArrowTrendingDownIcon, EyeIcon, HeartIcon, ChatBubbleLeftIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    client:                    { type: Object, required: true },
    contract:                  { type: Object, required: true },
    cycle:                     { type: Object, default: null },
    credits:                   { type: Array,  default: () => [] },
    tickets:                   { type: Array,  default: () => [] },
    history:                   { type: Array,  default: () => [] },
    month:                     { type: String, required: true },
    monthLabel:                { type: String, default: '' },
    prevMonth:                 { type: String, required: true },
    nextMonth:                 { type: String, required: true },
    isFutureMonth:             { type: Boolean, default: false },
    isCurrentMonth:            { type: Boolean, default: false },
    socialAccounts:            { type: Array, default: () => [] },
    socialPosts:               { type: Array, default: () => [] },
    availableSocialProviders:  { type: Array, default: () => [] },
    analytics:                 { type: Object, default: () => ({ has_data:false, kpis:{}, previous:{}, delta:{}, per_provider:{}, top_posts:[], followers_trend:{} }) },
});

const activeTab = ref('production');

// --- Month navigation ----------------------------------------------------
function gotoMonth(month) {
    router.get(route('recurring.clients.show', props.client.id), { month }, {
        preserveScroll: true,
        preserveState: false,
    });
}

// --- Social calendar -----------------------------------------------------
const providerLabels = {
    facebook: 'Facebook', instagram: 'Instagram', linkedin: 'LinkedIn',
    tiktok: 'TikTok', youtube: 'YouTube',
};
const providerColors = {
    facebook:  'bg-blue-100 text-blue-800 border-blue-200',
    instagram: 'bg-pink-100 text-pink-700 border-pink-200',
    linkedin:  'bg-sky-100 text-sky-800 border-sky-200',
    tiktok:    'bg-zinc-900 text-white border-zinc-900',
    youtube:   'bg-red-100 text-red-700 border-red-200',
};
const statusColors = {
    draft:      'bg-gray-100 text-gray-700',
    scheduled:  'bg-amber-100 text-amber-700',
    publishing: 'bg-blue-100 text-blue-700',
    published:  'bg-emerald-100 text-emerald-700',
    partial:    'bg-orange-100 text-orange-700',
    failed:     'bg-rose-100 text-rose-700',
    canceled:   'bg-zinc-200 text-zinc-700',
};
const connectedProviders = computed(() => props.socialAccounts.map(a => a.provider));

const [calYear, calMonthNum] = props.month.split('-').map(Number);
const calFirstDay = new Date(calYear, calMonthNum - 1, 1);
const calDaysInMonth = new Date(calYear, calMonthNum, 0).getDate();
const calStartWeekday = (calFirstDay.getDay() + 6) % 7; // lunes=0

const calendarCells = computed(() => {
    const cells = [];
    for (let i = 0; i < calStartWeekday; i++) cells.push(null);
    for (let d = 1; d <= calDaysInMonth; d++) cells.push(d);
    return cells;
});
const postsByDay = computed(() => {
    const map = {};
    for (const p of props.socialPosts) {
        const dt = p.scheduled_at || p.published_at || p.created_at;
        if (!dt) continue;
        const day = new Date(dt).getDate();
        (map[day] = map[day] || []).push(p);
    }
    return map;
});

function connectProvider(provider) {
    window.location.href = route('social.oauth.redirect', { provider, client: props.client.id });
}
function disconnectAccount(account) {
    if (!confirm(`¿Desconectar ${account.name} (${account.provider})?`)) return;
    router.delete(route('social.accounts.disconnect', account.id), { preserveScroll: true });
}
function deletePost(post) {
    if (!confirm('¿Eliminar este post?')) return;
    router.delete(route('social.posts.destroy', [props.client.id, post.id]), { preserveScroll: true });
}
function publishNow(post) {
    if (!confirm('¿Publicar este post ahora?')) return;
    router.post(route('social.posts.publishNow', [props.client.id, post.id]), {}, { preserveScroll: true });
}

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

function parseAccentColor(color) {
    if (!color) return null;
    return color.startsWith('#') ? color : `#${color.replace(/^#/, '')}`;
}

function ticketColor(ticket) {
    const credit = ticket?.deliverable_credit || ticket?.deliverableCredit;
    const service = credit?.contract_service || credit?.contractService;
    return parseAccentColor(service?.color || credit?.color);
}

function ticketPrefix(ticket) {
    const credit = ticket?.deliverable_credit || ticket?.deliverableCredit;
    const service = credit?.contract_service || credit?.contractService;
    return service?.prefix || '';
}

function ticketAccentStyle(ticket) {
    const accent = ticketColor(ticket);
    if (!accent) return {};
    const style = {
        color: accent,
        borderColor: accent,
    };
    if (accent.length === 7) {
        style.backgroundColor = `${accent}22`;
    }
    return style;
}

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
    const label = props.monthLabel || props.month;
    if (!confirm(`¿Abrir/planificar el ciclo de ${label} para este cliente?`)) return;
    router.post(route('recurring.clients.openCycle', props.client.id), { month: props.month });
}

// On-demand deliverable modal
const showModal = ref(false);
const form = useForm({
    title: '',
    priority: 'Media',
    content: '',
    due_date: '',
    deliverable_credit_id: null,
    billing_cycle_id: null,
});
function submitDeliverable() {
    form.billing_cycle_id = props.cycle?.id ?? null;
    form.post(route('recurring.clients.deliverables.store', props.client.id), {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false; form.reset(); },
    });
}

const availableCredits = computed(() => props.credits);

// ---- Analytics ----
function fmt(n) {
    const v = Number(n || 0);
    if (v >= 1_000_000) return (v / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (v >= 1_000)     return (v / 1_000).toFixed(1).replace(/\.0$/, '') + 'k';
    return v.toLocaleString();
}

const kpiCards = computed(() => {
    const k = props.analytics?.kpis || {};
    const d = props.analytics?.delta || {};
    return [
        { label: 'Posts',         value: fmt(k.posts),        icon: MegaphoneIcon,      delta: d.posts },
        { label: 'Impresiones',   value: fmt(k.impressions),  icon: EyeIcon,            delta: d.impressions },
        { label: 'Interacciones', value: fmt(k.interactions), icon: HeartIcon,          delta: null },
        { label: 'Engagement',    value: ((Number(k.engagement) || 0) * 100).toFixed(1) + '%', icon: ChatBubbleLeftIcon, delta: d.engagement },
    ];
});

const perProviderRows = computed(() => {
    const obj = props.analytics?.per_provider || {};
    return Object.entries(obj).map(([provider, row]) => ({ provider, ...row }));
});

const followersTrendRows = computed(() => {
    const obj = props.analytics?.followers_trend || {};
    return Object.entries(obj).map(([provider, row]) => ({ provider, ...row }));
});

const syncingAnalytics = ref(false);
function syncAnalytics() {
    syncingAnalytics.value = true;
    router.post(route('recurring.clients.syncAnalytics', props.client.id), {}, {
        preserveScroll: true,
        onFinish: () => { syncingAnalytics.value = false; },
    });
}
</script>

<template>
    <Head :title="`Recurrente — ${client.business_name}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <Link v-if="!$page.props.auth.user.is_client" :href="route('recurring.index')"
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
                <div class="flex items-center gap-2 flex-wrap">
                    <!-- Month switcher -->
                    <div class="inline-flex items-center gap-1 rounded-md border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
                        <button @click="gotoMonth(prevMonth)" class="p-1.5 hover:bg-gray-100 dark:hover:bg-zinc-700 rounded-l-md" title="Mes anterior">
                            <ChevronLeftIcon class="h-4 w-4" />
                        </button>
                        <span class="px-3 py-1 text-xs font-medium text-gray-700 dark:text-zinc-200 capitalize min-w-[110px] text-center">
                            {{ monthLabel || month }}
                        </span>
                        <button @click="gotoMonth(nextMonth)" class="p-1.5 hover:bg-gray-100 dark:hover:bg-zinc-700 rounded-r-md" title="Mes siguiente">
                            <ChevronRightIcon class="h-4 w-4" />
                        </button>
                    </div>

                    <span v-if="cycle"
                        class="inline-flex items-center gap-1 rounded-full bg-blue-50 dark:bg-blue-900/30 px-3 py-1 text-xs font-medium text-blue-700 dark:text-blue-300 capitalize">
                        <ArrowPathIcon class="h-3 w-3" />
                        Ciclo {{ cycle.label }} · {{ cycle.status }}
                    </span>
                    <button v-else-if="!$page.props.auth.user.is_client" @click="openCycle"
                        class="inline-flex items-center gap-1 rounded-md bg-[#264ab3] px-3 py-1.5 text-xs font-medium text-white hover:bg-[#1e3a8a] capitalize">
                        <ArrowPathIcon class="h-4 w-4" />
                        {{ isFutureMonth ? `Planificar ciclo ${monthLabel}` : `Abrir ciclo ${monthLabel}` }}
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
                        <div v-for="c in credits" :key="c.id"
                            :style="parseAccentColor(c.color) ? { borderLeft: `4px solid ${parseAccentColor(c.color)}` } : {}"
                            class="rounded-md border border-gray-100 dark:border-zinc-700 p-3">
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
                        <button @click="activeTab = 'calendar'"
                            :class="[activeTab === 'calendar'
                                ? 'border-[#264ab3] text-[#264ab3] dark:text-blue-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700',
                                'border-b-2 px-1 py-2 font-medium inline-flex items-center gap-1']">
                            <CalendarDaysIcon class="h-4 w-4" />
                            Redes sociales
                            <span v-if="socialPosts.length"
                                class="ml-1 rounded-full bg-gray-100 dark:bg-zinc-700 px-1.5 text-[10px] text-gray-600 dark:text-zinc-300">
                                {{ socialPosts.length }}
                            </span>
                        </button>
                        <button @click="activeTab = 'analytics'"
                            :class="[activeTab === 'analytics'
                                ? 'border-[#264ab3] text-[#264ab3] dark:text-blue-300'
                                : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-zinc-400 dark:hover:text-zinc-200',
                                'border-b-2 px-1 py-2 font-medium inline-flex items-center gap-1']">
                            <ChartBarIcon class="h-4 w-4" /> Analytics
                        </button>
                    </nav>
                </div>

                <!-- Tab: Producción (Kanban filtrado) -->
                <div v-if="activeTab === 'production'">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-xs text-gray-500 dark:text-zinc-400">
                            Tablero limitado a entregables del ciclo actual.
                        </p>
                        <button v-if="cycle && !$page.props.auth.user.is_client" @click="showModal = true"
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
                                        <span class="flex items-center gap-2">
                                            <span v-if="ticketPrefix(t)"
                                                :style="ticketAccentStyle(t)"
                                                class="rounded-full border px-2 py-0.5 font-mono text-[10px] uppercase">
                                                {{ ticketPrefix(t) }}
                                            </span>
                                            <span class="font-mono">{{ t.code || `#${t.id}` }}</span>
                                        </span>
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

                <!-- Tab: Redes sociales (cuentas + calendario del mes) -->
                <div v-else-if="activeTab === 'calendar'" class="space-y-4">
                    <!-- Cuentas conectadas -->
                    <div class="rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-zinc-700 pb-3 mb-3">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-zinc-200 flex items-center gap-2">
                                <LinkIcon class="h-4 w-4 text-[#264ab3]" /> Cuentas conectadas
                            </h3>
                            <Link v-if="!$page.props.auth.user.is_client" :href="route('social.clients.show', client.id)"
                                class="text-[11px] text-[#264ab3] hover:underline">
                                Vista social completa →
                            </Link>
                        </div>

                        <div v-if="!socialAccounts.length" class="text-xs text-gray-500 italic mb-3">
                            Aún no hay cuentas conectadas. Vincula una red para empezar a programar publicaciones.
                        </div>
                        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 mb-3">
                            <div v-for="a in socialAccounts" :key="a.id"
                                class="flex items-center justify-between border border-gray-200 dark:border-zinc-700 rounded-md p-2">
                                <div class="flex items-center gap-2">
                                    <img v-if="a.avatar_url" :src="a.avatar_url" class="w-8 h-8 rounded-full" />
                                    <div v-else class="w-8 h-8 rounded-full bg-gray-200 dark:bg-zinc-700 flex items-center justify-center text-[10px] font-bold uppercase">
                                        {{ a.provider[0] }}
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium text-gray-800 dark:text-zinc-100">{{ a.name }}</p>
                                        <span :class="['text-[10px] px-1.5 py-0.5 rounded-full font-medium border', providerColors[a.provider]]">
                                            {{ providerLabels[a.provider] }}
                                        </span>
                                        <span v-if="a.status !== 'active'" class="ml-1 text-[10px] text-rose-600">({{ a.status }})</span>
                                    </div>
                                </div>
                                <button v-if="!$page.props.auth.user.is_client" @click="disconnectAccount(a)" class="text-gray-400 hover:text-rose-600" title="Desconectar">
                                    <TrashIcon class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>

                        <div v-if="!$page.props.auth.user.is_client" class="flex flex-wrap items-center gap-2 pt-2 border-t border-gray-100 dark:border-zinc-700">
                            <span class="text-[11px] text-gray-500">Conectar otra red  :)  :</span>
                            <button v-for="p in availableSocialProviders" :key="p"
                                @click="connectProvider(p)"
                                :disabled="connectedProviders.includes(p)"
                                :class="['inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-[11px] font-medium border',
                                         connectedProviders.includes(p)
                                           ? 'bg-gray-100 text-gray-400 cursor-not-allowed border-gray-200'
                                           : 'bg-white hover:bg-gray-50 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-gray-700 dark:text-zinc-200 border-gray-300 dark:border-zinc-600']">
                                <PlusIcon class="w-3 h-3" /> {{ providerLabels[p] }}
                            </button>
                        </div>
                    </div>

                    <!-- Calendario mensual de posts -->
                    <div class="rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-zinc-700 pb-3 mb-3">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-zinc-200 flex items-center gap-2">
                                <MegaphoneIcon class="h-4 w-4 text-[#264ab3]" />
                                Calendario · <span class="capitalize">{{ monthLabel || month }}</span>
                            </h3>
                            <Link v-if="!$page.props.auth.user.is_client" :href="route('social.posts.create', client.id)"
                                class="inline-flex items-center gap-1 rounded-md bg-[#264ab3] px-3 py-1.5 text-xs font-medium text-white hover:bg-[#1e3a8a]">
                                <PlusIcon class="w-4 h-4" /> Nuevo post
                            </Link>
                        </div>

                        <p v-if="isFutureMonth" class="mb-2 text-[11px] text-amber-600 dark:text-amber-400">
                            Estás planificando un mes futuro. Los posts programados se publicarán automáticamente cuando llegue la fecha.
                        </p>

                        <div class="overflow-x-auto">
                            <div class="grid grid-cols-7 gap-1 text-[10px] text-gray-500 uppercase font-medium pb-2">
                                <div v-for="d in ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom']" :key="d" class="text-center">{{ d }}</div>
                            </div>
                            <div class="grid grid-cols-7 gap-1">
                                <div v-for="(cell, i) in calendarCells" :key="i"
                                     class="min-h-[90px] rounded-md border border-gray-100 dark:border-zinc-700 p-1 text-xs"
                                     :class="cell ? 'bg-gray-50/40 dark:bg-zinc-900' : 'bg-transparent'">
                                    <template v-if="cell">
                                        <div class="font-semibold text-gray-500 mb-1">{{ cell }}</div>
                                        <template v-for="p in (postsByDay[cell] || [])" :key="p.id">
                                            <span v-if="$page.props.auth.user.is_client"
                                                  class="block truncate px-1 py-0.5 mb-0.5 rounded text-[10px]"
                                                  :class="statusColors[p.status]"
                                                  :title="p.title || p.body">
                                                {{ p.title || (p.body ? p.body.slice(0, 22) : 'Sin título') }}
                                            </span>
                                            <Link v-else
                                                  :href="route('social.posts.edit', [client.id, p.id])"
                                                  class="block truncate px-1 py-0.5 mb-0.5 rounded text-[10px]"
                                                  :class="statusColors[p.status]"
                                                  :title="p.title || p.body">
                                                {{ p.title || (p.body ? p.body.slice(0, 22) : 'Sin título') }}
                                            </Link>
                                        </template>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Lista compacta -->
                        <div v-if="socialPosts.length" class="mt-4">
                            <h4 class="text-xs font-semibold text-gray-600 dark:text-zinc-300 mb-2">Posts de este mes</h4>
                            <div class="divide-y divide-gray-100 dark:divide-zinc-700 rounded-md border border-gray-100 dark:border-zinc-700">
                                <div v-for="p in socialPosts" :key="p.id"
                                     class="flex items-center gap-3 px-3 py-2 text-xs hover:bg-gray-50 dark:hover:bg-zinc-900/40">
                                    <span class="w-28 text-gray-500 whitespace-nowrap">
                                        {{ p.scheduled_at ? new Date(p.scheduled_at).toLocaleString('es', { dateStyle: 'short', timeStyle: 'short' }) : '—' }}
                                    </span>
                                    <span class="flex-1 truncate text-gray-800 dark:text-zinc-100">
                                        {{ p.title || (p.body ? p.body.slice(0, 60) : 'Sin título') }}
                                    </span>
                                    <span class="flex gap-1">
                                        <span v-for="t in p.targets" :key="t.id"
                                            :class="['text-[10px] px-1.5 py-0.5 rounded-full font-medium border', providerColors[t.provider]]">
                                            {{ t.provider }}
                                        </span>
                                    </span>
                                    <span :class="['text-[10px] px-2 py-0.5 rounded-full font-semibold', statusColors[p.status]]">
                                        {{ p.status }}
                                    </span>
                                    <span v-if="!$page.props.auth.user.is_client" class="flex items-center gap-1">
                                        <button v-if="['draft','scheduled','failed','partial'].includes(p.status)"
                                            @click="publishNow(p)" class="p-1 text-emerald-600 hover:text-emerald-700" title="Publicar ya">
                                            <PaperAirplaneIcon class="w-3.5 h-3.5" />
                                        </button>
                                        <Link :href="route('social.posts.edit', [client.id, p.id])"
                                            class="p-1 text-gray-500 hover:text-[#264ab3]" title="Editar">
                                            <PencilSquareIcon class="w-3.5 h-3.5" />
                                        </Link>
                                        <button v-if="p.status !== 'published'" @click="deletePost(p)"
                                            class="p-1 text-gray-500 hover:text-rose-600" title="Eliminar">
                                            <TrashIcon class="w-3.5 h-3.5" />
                                        </button>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <p v-else class="mt-4 text-center text-xs text-gray-400 italic">
                            Sin posts este mes. Crea uno con "Nuevo post".
                        </p>
                    </div>
                </div>

                <!-- Tab: Analytics -->
                <div v-else-if="activeTab === 'analytics'" class="space-y-4">
                    <!-- Header con acción -->
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-zinc-100 flex items-center gap-2">
                                <ChartBarIcon class="h-4 w-4 text-[#264ab3]" />
                                Métricas de {{ monthLabel }}
                            </h3>
                            <p class="text-[11px] text-gray-500 dark:text-zinc-400 mt-0.5">
                                Datos agregados de las publicaciones del mes. Se actualizan cada 6 h y al sincronizar manualmente.
                            </p>
                        </div>
                        <button v-if="!$page.props.auth.user.is_client" @click="syncAnalytics" :disabled="syncingAnalytics"
                            class="inline-flex items-center gap-1 rounded-md bg-[#264ab3] px-3 py-1.5 text-xs text-white hover:bg-[#1e3a8a] disabled:opacity-50">
                            <ArrowPathIcon :class="['h-3.5 w-3.5', syncingAnalytics && 'animate-spin']" />
                            Sincronizar ahora
                        </button>
                    </div>

                    <!-- Estado vacío -->
                    <div v-if="!analytics.has_data"
                        class="rounded-lg border border-dashed border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-8 text-center">
                        <ChartBarIcon class="mx-auto h-10 w-10 text-gray-300 dark:text-zinc-600" />
                        <p class="mt-3 text-sm font-medium text-gray-700 dark:text-zinc-200">Sin datos aún</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400 max-w-md mx-auto">
                            Cuando publiques contenido y la integración traiga insights, aquí verás
                            impresiones, alcance, engagement y los posts con mejor desempeño.
                        </p>
                    </div>

                    <template v-else>
                        <!-- KPIs -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div v-for="kpi in kpiCards" :key="kpi.label"
                                class="rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-zinc-400">{{ kpi.label }}</span>
                                    <component :is="kpi.icon" class="h-4 w-4 text-[#264ab3]" />
                                </div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-zinc-100">{{ kpi.value }}</div>
                                <div v-if="kpi.delta !== null && kpi.delta !== undefined"
                                    :class="['mt-0.5 text-[11px] font-medium flex items-center gap-0.5',
                                        kpi.delta >= 0 ? 'text-emerald-600' : 'text-rose-600']">
                                    <component :is="kpi.delta >= 0 ? ArrowTrendingUpIcon : ArrowTrendingDownIcon" class="h-3 w-3" />
                                    {{ Math.abs(kpi.delta) }}% vs mes anterior
                                </div>
                                <div v-else class="mt-0.5 text-[11px] text-gray-400 dark:text-zinc-500">— sin comparable —</div>
                            </div>
                        </div>

                        <!-- Por red social -->
                        <div class="rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-zinc-200 border-b border-gray-100 dark:border-zinc-700 pb-2 mb-3">
                                Desempeño por red
                            </h4>
                            <div v-if="!perProviderRows.length" class="text-xs italic text-gray-500">Sin métricas por red este mes.</div>
                            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div v-for="row in perProviderRows" :key="row.provider"
                                    class="rounded-md border border-gray-200 dark:border-zinc-700 p-3">
                                    <div class="flex items-center justify-between mb-2">
                                        <span :class="['text-[11px] px-1.5 py-0.5 rounded-full font-medium border', providerColors[row.provider] || 'bg-gray-100 text-gray-700 border-gray-200']">
                                            {{ providerLabels[row.provider] || row.provider }}
                                        </span>
                                        <span class="text-[11px] text-gray-500">{{ row.posts }} post{{ row.posts === 1 ? '' : 's' }}</span>
                                    </div>
                                    <dl class="grid grid-cols-2 gap-1 text-[11px]">
                                        <dt class="text-gray-500">Impresiones</dt><dd class="text-right font-medium">{{ fmt(row.impressions) }}</dd>
                                        <dt class="text-gray-500">Alcance</dt>    <dd class="text-right font-medium">{{ fmt(row.reach) }}</dd>
                                        <dt class="text-gray-500">Likes</dt>      <dd class="text-right font-medium">{{ fmt(row.likes) }}</dd>
                                        <dt class="text-gray-500">Comentarios</dt><dd class="text-right font-medium">{{ fmt(row.comments) }}</dd>
                                        <dt class="text-gray-500">Compartidos</dt><dd class="text-right font-medium">{{ fmt(row.shares) }}</dd>
                                        <dt class="text-gray-500">Engagement</dt> <dd class="text-right font-medium">{{ (row.avg_engagement * 100).toFixed(1) }}%</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <!-- Top posts -->
                        <div class="rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-zinc-200 border-b border-gray-100 dark:border-zinc-700 pb-2 mb-3">
                                Top 5 publicaciones
                            </h4>
                            <div v-if="!analytics.top_posts.length" class="text-xs italic text-gray-500">Aún no hay posts con métricas.</div>
                            <table v-else class="w-full text-xs">
                                <thead>
                                    <tr class="text-left text-[11px] uppercase tracking-wide text-gray-500 dark:text-zinc-400">
                                        <th class="py-1.5">Post</th>
                                        <th class="py-1.5">Red</th>
                                        <th class="py-1.5 text-right">Impres.</th>
                                        <th class="py-1.5 text-right">Alcance</th>
                                        <th class="py-1.5 text-right">Interacciones</th>
                                        <th class="py-1.5 text-right">Eng.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in analytics.top_posts" :key="p.id"
                                        class="border-t border-gray-100 dark:border-zinc-700">
                                        <td class="py-1.5 pr-2 max-w-[260px]">
                                            <a v-if="p.platform_url" :href="p.platform_url" target="_blank"
                                                class="font-medium text-[#264ab3] hover:underline truncate block">
                                                {{ p.title || (p.body || '').slice(0,60) || 'Sin título' }}
                                            </a>
                                            <span v-else class="font-medium text-gray-800 dark:text-zinc-200 truncate block">
                                                {{ p.title || (p.body || '').slice(0,60) || 'Sin título' }}
                                            </span>
                                        </td>
                                        <td class="py-1.5">
                                            <span :class="['text-[10px] px-1.5 py-0.5 rounded-full font-medium border', providerColors[p.provider] || 'bg-gray-100 text-gray-700 border-gray-200']">
                                                {{ providerLabels[p.provider] || p.provider }}
                                            </span>
                                        </td>
                                        <td class="py-1.5 text-right">{{ fmt(p.impressions) }}</td>
                                        <td class="py-1.5 text-right">{{ fmt(p.reach) }}</td>
                                        <td class="py-1.5 text-right">{{ fmt((p.likes||0) + (p.comments||0) + (p.shares||0) + (p.saves||0)) }}</td>
                                        <td class="py-1.5 text-right">{{ (Number(p.engagement_rate || 0) * 100).toFixed(1) }}%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Crecimiento followers -->
                        <div v-if="followersTrendRows.length"
                            class="rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-zinc-200 border-b border-gray-100 dark:border-zinc-700 pb-2 mb-3">
                                Crecimiento de seguidores
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div v-for="row in followersTrendRows" :key="row.provider"
                                    class="rounded-md border border-gray-200 dark:border-zinc-700 p-3">
                                    <div class="flex items-center justify-between mb-1">
                                        <span :class="['text-[11px] px-1.5 py-0.5 rounded-full font-medium border', providerColors[row.provider] || 'bg-gray-100 text-gray-700 border-gray-200']">
                                            {{ providerLabels[row.provider] || row.provider }}
                                        </span>
                                        <span :class="['text-[11px] font-semibold flex items-center gap-0.5',
                                            row.delta >= 0 ? 'text-emerald-600' : 'text-rose-600']">
                                            <component :is="row.delta >= 0 ? ArrowTrendingUpIcon : ArrowTrendingDownIcon" class="h-3 w-3" />
                                            {{ row.delta >= 0 ? '+' : '' }}{{ row.delta }}
                                        </span>
                                    </div>
                                    <div class="text-xl font-semibold text-gray-900 dark:text-zinc-100">{{ fmt(row.last) }}</div>
                                    <div class="text-[11px] text-gray-500">seguidores actuales (inicio mes: {{ fmt(row.first) }})</div>
                                </div>
                            </div>
                        </div>
                    </template>
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
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 placeholder-gray-400 dark:placeholder-zinc-500 text-sm" />
                        <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-zinc-300">Prioridad</label>
                            <select v-model="form.priority"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 placeholder-gray-400 dark:placeholder-zinc-500 text-sm">
                                <option>Baja</option><option>Media</option><option>Alta</option><option>Urgente</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-zinc-300">Fecha límite</label>
                            <input v-model="form.due_date" type="date"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 placeholder-gray-400 dark:placeholder-zinc-500 text-sm" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-zinc-300">
                            Vincular a entregable (opcional)
                        </label>
                        <select v-model="form.deliverable_credit_id"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 placeholder-gray-400 dark:placeholder-zinc-500 text-sm">
                            <option :value="null">— Sin vincular (excedente) —</option>
                            <option v-for="c in availableCredits" :key="c.id" :value="c.id">
                                {{ c.name }}
                                <template v-if="c.unit_type === 'fixed'"> (cuota fija)</template>
                                <template v-else-if="c.is_unlimited"> (∞)</template>
                                <template v-else> ({{ c.remaining }}/{{ c.capacity }})</template>
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-zinc-300">Descripción</label>
                        <textarea v-model="form.content" rows="3"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 placeholder-gray-400 dark:placeholder-zinc-500 text-sm"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showModal = false"
                            class="rounded-md border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-700 dark:text-zinc-100 hover:bg-gray-50 dark:hover:bg-zinc-600 px-3 py-1.5 text-sm">Cancelar</button>
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
