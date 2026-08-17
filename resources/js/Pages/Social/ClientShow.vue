<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    MegaphoneIcon, PlusIcon, TrashIcon, PaperAirplaneIcon,
    LinkIcon, ChevronLeftIcon, ChevronRightIcon, CalendarDaysIcon, PencilSquareIcon,
    ChatBubbleLeftRightIcon,
} from '@heroicons/vue/24/outline';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    client: { type: Object, required: true },
    accounts: { type: Array, default: () => [] },
    posts: { type: Array, default: () => [] },
    month: { type: String, required: true },
    availableProviders: { type: Array, default: () => [] },
    whatsappNumeros: { type: Array, default: () => [] },
});

const page = usePage();

const puedeWhatsapp = computed(() => {
    const u = page.props.auth?.user;
    return Boolean(u?.is_admin || u?.permissions?.includes('Gestionar WhatsApp'));
});

const colorCalidad = (r) => ({
    GREEN: 'text-emerald-600',
    YELLOW: 'text-amber-600',
    RED: 'text-red-600',
}[r] ?? 'text-gray-400');

const providerLabels = {
    facebook: 'Facebook', instagram: 'Instagram', linkedin: 'LinkedIn', tiktok: 'TikTok', youtube: 'YouTube',
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

// Estado por red. El estado del post es un resumen; cuando una red falla y otra
// no, el detalle solo vive aquí y antes no se mostraba en ningún lado: el
// composer decía "encolado" y el fallo se quedaba mudo en la base.
const targetStatusColors = {
    pending:    'bg-gray-100 text-gray-600 border-gray-200',
    publishing: 'bg-blue-50 text-blue-700 border-blue-200',
    published:  'bg-emerald-50 text-emerald-700 border-emerald-200',
    failed:     'bg-rose-50 text-rose-700 border-rose-300',
};
const targetStatusLabels = {
    pending: 'pendiente', publishing: 'publicando', published: 'publicado', failed: 'falló',
};

function failedTargets(post) {
    return (post.targets || []).filter(t => t.status === 'failed' && t.error_message);
}

const connectedProviders = computed(() => props.accounts.map(a => a.provider));

function connect(provider) {
    window.location.href = route('social.oauth.redirect', { provider, client: props.client.id });
}
function disconnect(account) {
    if (!confirm(`¿Desconectar ${account.name} (${account.provider})?`)) return;
    router.delete(route('social.accounts.disconnect', account.id));
}
function deletePost(post) {
    if (!confirm('¿Eliminar este post?')) return;
    router.delete(route('social.posts.destroy', [props.client.id, post.id]));
}
function publishNow(post) {
    if (!confirm('¿Publicar este post ahora?')) return;
    router.post(route('social.posts.publishNow', [props.client.id, post.id]));
}

// Calendar logic
const [year, monthNum] = props.month.split('-').map(Number);
const firstDay = new Date(year, monthNum - 1, 1);
const daysInMonth = new Date(year, monthNum, 0).getDate();
const startWeekday = (firstDay.getDay() + 6) % 7; // lunes=0

const calendarCells = computed(() => {
    const cells = [];
    for (let i = 0; i < startWeekday; i++) cells.push(null);
    for (let d = 1; d <= daysInMonth; d++) cells.push(d);
    return cells;
});

const postsByDay = computed(() => {
    const map = {};
    for (const p of props.posts) {
        const dt = p.scheduled_at || p.published_at;
        if (!dt) continue;
        const day = new Date(dt).getDate();
        (map[day] = map[day] || []).push(p);
    }
    return map;
});

function prevMonth() {
    const d = new Date(year, monthNum - 2, 1);
    router.get(route('social.clients.show', props.client.id), { month: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}` });
}
function nextMonth() {
    const d = new Date(year, monthNum, 1);
    router.get(route('social.clients.show', props.client.id), { month: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}` });
}

const monthLabel = new Date(year, monthNum - 1, 1).toLocaleDateString('es', { month: 'long', year: 'numeric' });
const view = ref('calendar'); // calendar | list
</script>

<template>
    <Head :title="`Social — ${client.business_name}`" />
    <AuthenticatedLayout>
        <div class="p-6 max-w-7xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <Link :href="route('social.index')" class="text-xs text-primary hover:underline">← Social</Link>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2 mt-1">
                        <MegaphoneIcon class="w-6 h-6 text-primary" />
                        {{ client.business_name }}
                    </h1>
                </div>
                <Link :href="route('social.posts.create', client.id)"
                    class="inline-flex items-center gap-2 rounded-md bg-primary hover:bg-secondary text-white px-4 py-2 text-sm font-medium">
                    <PlusIcon class="w-4 h-4" /> Nuevo post
                </Link>
            </div>

            <!-- Connected accounts -->
            <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-5">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-zinc-800 pb-3 mb-4">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <LinkIcon class="w-5 h-5 text-primary" /> Cuentas conectadas
                    </h3>
                </div>

                <div v-if="!accounts.length" class="text-sm text-gray-500 italic mb-3">
                    Aún no hay cuentas conectadas. Vincula una red para empezar a publicar.
                </div>
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
                    <div v-for="a in accounts" :key="a.id"
                        class="flex items-center justify-between border border-gray-200 dark:border-zinc-700 rounded-lg p-3">
                        <div class="flex items-center gap-3">
                            <img v-if="a.avatar_url" :src="a.avatar_url" class="w-9 h-9 rounded-full" />
                            <div v-else class="w-9 h-9 rounded-full bg-gray-200 dark:bg-zinc-700 flex items-center justify-center text-xs font-bold uppercase">
                                {{ a.provider[0] }}
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ a.name }}</p>
                                <span :class="['text-[10px] px-1.5 py-0.5 rounded-full font-medium border', providerColors[a.provider]]">
                                    {{ providerLabels[a.provider] }}
                                </span>
                                <span v-if="a.status !== 'active'" class="ml-1 text-[10px] text-rose-600">({{ a.status }})</span>
                            </div>
                        </div>
                        <button @click="disconnect(a)" class="text-gray-400 hover:text-rose-600" title="Desconectar">
                            <TrashIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 pt-3 border-t border-gray-100 dark:border-zinc-800">
                    <p class="w-full text-xs text-gray-500 mb-1">Conectar otra red:</p>
                    <button v-for="p in availableProviders" :key="p" @click="connect(p)"
                        :disabled="connectedProviders.includes(p)"
                        :class="['inline-flex items-center gap-1 rounded-md px-3 py-1.5 text-xs font-medium border',
                                 connectedProviders.includes(p)
                                   ? 'bg-gray-100 text-gray-400 cursor-not-allowed border-gray-200'
                                   : 'bg-white hover:bg-gray-50 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-gray-700 dark:text-zinc-200 border-gray-300 dark:border-zinc-600']">
                        <PlusIcon class="w-3 h-3" /> {{ providerLabels[p] }}
                    </button>
                </div>
            </div>

            <!-- WhatsApp Business. Tarjeta aparte a propósito: no es un
                 proveedor más de la fila de arriba. Aquellos son un redirect
                 de OAuth que devuelve una cuenta; este es Embedded Signup, que
                 devuelve una WABA con varios números y su propio ciclo de vida. -->
            <div
                v-if="puedeWhatsapp"
                class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-5"
            >
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-zinc-800 pb-3 mb-4">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <ChatBubbleLeftRightIcon class="w-5 h-5 text-[#25D366]" /> WhatsApp Business
                    </h3>
                    <Link
                        :href="route('whatsapp.connect.show', client.id)"
                        class="text-xs font-bold text-primary hover:text-secondary"
                    >
                        {{ whatsappNumeros.length ? 'Administrar →' : 'Conectar →' }}
                    </Link>
                </div>

                <p v-if="!whatsappNumeros.length" class="text-sm text-gray-500 italic">
                    Sin número conectado. El cliente autoriza su cuenta desde una pantalla
                    aparte, iniciando sesión con su Facebook.
                </p>

                <ul v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <li
                        v-for="n in whatsappNumeros"
                        :key="n.id"
                        class="border border-gray-200 dark:border-zinc-700 rounded-lg p-3"
                    >
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-100">
                            {{ n.display_phone_number }}
                            <span v-if="n.verified_name" class="font-normal text-gray-500">· {{ n.verified_name }}</span>
                        </p>
                        <p class="text-xs mt-0.5">
                            <span :class="colorCalidad(n.quality_rating)">
                                Calidad: {{ n.quality_rating ?? 'sin dato' }}
                            </span>
                            <span v-if="n.account_status === 'revoked'" class="ml-1 text-rose-600">
                                · acceso revocado
                            </span>
                            <span v-else-if="!n.is_active" class="ml-1 text-gray-400">· inactivo</span>
                        </p>
                    </li>
                </ul>
            </div>

            <!-- Calendar / list toggle -->
            <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-5">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-zinc-800 pb-3 mb-4">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <CalendarDaysIcon class="w-5 h-5 text-primary" />
                        <span class="capitalize">{{ monthLabel }}</span>
                    </h3>
                    <div class="flex items-center gap-2">
                        <div class="flex rounded-md overflow-hidden border border-gray-200 dark:border-zinc-700 text-xs">
                            <button @click="view = 'calendar'"
                                :class="['px-3 py-1', view === 'calendar' ? 'bg-primary text-white' : 'bg-white dark:bg-zinc-800 text-gray-600']">
                                Calendario
                            </button>
                            <button @click="view = 'list'"
                                :class="['px-3 py-1', view === 'list' ? 'bg-primary text-white' : 'bg-white dark:bg-zinc-800 text-gray-600']">
                                Lista
                            </button>
                        </div>
                        <button @click="prevMonth" class="p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-zinc-800">
                            <ChevronLeftIcon class="w-4 h-4" />
                        </button>
                        <button @click="nextMonth" class="p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-zinc-800">
                            <ChevronRightIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Calendar view -->
                <div v-if="view === 'calendar'" class="overflow-x-auto">
                    <div class="grid grid-cols-7 gap-1 text-xs text-gray-500 uppercase font-medium pb-2">
                        <div v-for="d in ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom']" :key="d" class="text-center">{{ d }}</div>
                    </div>
                    <div class="grid grid-cols-7 gap-1">
                        <div v-for="(cell, i) in calendarCells" :key="i"
                             class="min-h-[100px] rounded-md border border-gray-100 dark:border-zinc-800 p-1 text-xs"
                             :class="cell ? 'bg-gray-50/40 dark:bg-zinc-900' : 'bg-transparent'">
                            <template v-if="cell">
                                <div class="font-semibold text-gray-500 mb-1">{{ cell }}</div>
                                <Link v-for="p in (postsByDay[cell] || [])" :key="p.id"
                                      :href="route('social.posts.edit', [client.id, p.id])"
                                      class="block truncate px-1 py-0.5 mb-0.5 rounded text-[10px]"
                                      :class="statusColors[p.status]">
                                    {{ p.title || (p.body ? p.body.slice(0, 24) : 'Sin título') }}
                                </Link>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- List view -->
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-zinc-950/40 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Fecha</th>
                                <th class="px-3 py-2 text-left">Contenido</th>
                                <th class="px-3 py-2 text-left">Redes</th>
                                <th class="px-3 py-2 text-left">Estado</th>
                                <th class="px-3 py-2 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <tr v-if="!posts.length">
                                <td colspan="5" class="px-3 py-8 text-center text-gray-400 italic">Sin posts este mes.</td>
                            </tr>
                            <tr v-for="p in posts" :key="p.id" class="hover:bg-gray-50 dark:hover:bg-zinc-800/40">
                                <td class="px-3 py-2 text-gray-600 dark:text-zinc-300 whitespace-nowrap">
                                    {{ p.scheduled_at ? new Date(p.scheduled_at).toLocaleString('es') : '—' }}
                                </td>
                                <td class="px-3 py-2 max-w-xs">
                                    <p class="font-medium text-gray-800 dark:text-gray-100 truncate">{{ p.title || 'Sin título' }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ p.body }}</p>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex flex-wrap gap-1">
                                        <component v-for="t in p.targets" :key="t.id"
                                            :is="t.platform_url ? 'a' : 'span'"
                                            :href="t.platform_url || undefined"
                                            :target="t.platform_url ? '_blank' : undefined"
                                            rel="noopener"
                                            :title="t.error_message || (t.platform_url ? 'Ver publicación' : null)"
                                            :class="['inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-full font-medium border',
                                                     targetStatusColors[t.status] || 'bg-gray-100 text-gray-600 border-gray-200',
                                                     t.platform_url ? 'hover:underline' : '']">
                                            <span :class="['w-1.5 h-1.5 rounded-full', providerColors[t.provider]]"></span>
                                            {{ providerLabels[t.provider] || t.provider }}
                                            <span class="opacity-70">· {{ targetStatusLabels[t.status] || t.status }}</span>
                                        </component>
                                    </div>

                                    <!-- El motivo del fallo: sin esto hay que ir a la base de datos. -->
                                    <p v-for="t in failedTargets(p)" :key="'e' + t.id"
                                       class="mt-1 text-[10px] text-rose-700 dark:text-rose-400 break-words"
                                       :title="t.error_message">
                                        <span class="font-semibold">{{ providerLabels[t.provider] || t.provider }}:</span>
                                        {{ t.error_message.length > 140 ? t.error_message.slice(0, 140) + '…' : t.error_message }}
                                    </p>
                                </td>
                                <td class="px-3 py-2">
                                    <span :class="['text-[10px] px-2 py-0.5 rounded-full font-semibold', statusColors[p.status]]">
                                        {{ p.status }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <button v-if="['draft','scheduled','failed','partial'].includes(p.status)"
                                        @click="publishNow(p)" class="p-1 text-emerald-600 hover:text-emerald-700" title="Publicar ya">
                                        <PaperAirplaneIcon class="w-4 h-4" />
                                    </button>
                                    <Link :href="route('social.posts.edit', [client.id, p.id])" class="p-1 text-gray-500 hover:text-primary" title="Editar">
                                        <PencilSquareIcon class="w-4 h-4 inline" />
                                    </Link>
                                    <button v-if="p.status !== 'published'" @click="deletePost(p)"
                                        class="p-1 text-gray-500 hover:text-rose-600" title="Eliminar">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
