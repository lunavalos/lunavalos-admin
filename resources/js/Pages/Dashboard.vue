<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { 
    CurrencyDollarIcon, 
    UserGroupIcon, 
    ChartBarIcon,
    BriefcaseIcon,
    DocumentTextIcon,
    ChevronRightIcon,
    PlusIcon,
    InboxIcon,
    HomeIcon,
    CalendarIcon,
    Square3Stack3DIcon,
    CheckBadgeIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({})
    },
    lists: {
        type: Object,
        default: () => ({})
    },
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(value || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString.split('T')[0] + 'T00:00:00');
    return date.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
};

// Ticket creation logic
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Wysiwyg from '@/Components/Wysiwyg.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const expandedItems = ref({});
const isCreateModalOpen = ref(false);
const createForm = useForm({
    title: '',
    priority: 'Media',
    content: '',
    files: [],
});

const openCreateModal = () => {
    isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
    isCreateModalOpen.value = false;
    createForm.reset();
};

const submitCreate = () => {
    createForm.post(route('tickets.store'), {
        onSuccess: () => closeCreateModal(),
    });
};
</script>

<template>
    <Head title="Panel de Inicio" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Resumen General
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                <!-- Vista de Administrador -->
                <template v-if="$page.props.auth.user.is_admin">
                    <!-- Stats Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-[#264ab3] transition hover:shadow-md">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-blue-100 text-[#264ab3] mr-4">
                                    <UserGroupIcon class="h-6 w-6" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 truncate">Total Clientes</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ stats.clients_count || 0 }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-green-500 transition hover:shadow-md">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                                    <CurrencyDollarIcon class="h-6 w-6" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 truncate">Renovaciones (Mes)</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(stats.monthly_revenue) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-purple-500 transition hover:shadow-md">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                                    <ChartBarIcon class="h-6 w-6" />
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-gray-500 truncate mb-1">Cotizaciones (Únicos)</p>
                                    <p class="text-xl font-bold text-gray-900">{{ formatCurrency(stats.projected_unique) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-teal-500 transition hover:shadow-md">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-teal-100 text-teal-600 mr-4">
                                    <ChartBarIcon class="h-6 w-6" />
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-gray-500 truncate mb-1">Cotizaciones (Mensuales)</p>
                                    <p class="text-xl font-bold text-gray-900">{{ formatCurrency(stats.projected_monthly) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Listas Grid Admin -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        
                        <!-- Cotizaciones Pendientes -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                                <h3 class="font-bold text-gray-800 flex items-center">
                                    <DocumentTextIcon class="h-5 w-5 mr-2 text-purple-500" />
                                    Cotizaciones Pendientes
                                </h3>
                                <span class="bg-purple-100 text-purple-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">{{ lists.pending_quotes?.length || 0 }}</span>
                            </div>
                            <div class="p-4">
                                <div v-if="lists.pending_quotes?.length === 0" class="text-sm text-gray-500 text-center py-4">No hay cotizaciones pendientes de aprobación.</div>
                                <ul v-else class="divide-y divide-gray-100 max-h-60 overflow-y-auto">
                                    <li v-for="quote in lists.pending_quotes" :key="quote.id" class="py-3 items-center">
                                        <div class="flex justify-between items-start">
                                            <Link :href="route('quotes.edit', quote.id)" class="text-sm font-medium text-gray-900 hover:text-purple-600 hover:underline line-clamp-1 pr-2">Cotización para {{ quote.client_name }}</Link>
                                            <div class="flex flex-col items-end">
                                                <span class="text-xs text-gray-500 whitespace-nowrap">{{ formatDate(quote.created_at) }}</span>
                                                <a :href="route('quotes.show', quote.id)" target="_blank" class="text-xs text-red-500 hover:text-red-700 hover:underline mt-1 font-medium whitespace-nowrap">Ver PDF</a>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1"><BriefcaseIcon class="h-3 w-3 inline mr-1" />Expira: {{ formatDate(quote.valid_until) }}</p>
                                        <div class="mt-2">
                                            <Link :href="route('quotes.index')" class="text-purple-600 text-xs hover:underline font-semibold">Ver Cotizaciones →</Link>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Tickets Recientes -->
                        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="p-5 border-b border-gray-50 flex justify-between items-center bg-white">
                                <h3 class="text-base font-bold text-gray-800 flex items-center">
                                    <InboxIcon class="h-6 w-6 mr-2 text-blue-500" />
                                    Tickets Recientes
                                </h3>
                                <Link :href="route('tickets.index')" class="text-xs font-bold text-blue-600 hover:underline px-3 py-1 bg-blue-50 rounded-full">Ver todos</Link>
                            </div>
                            <div class="p-2">
                                <div v-if="!lists.recent_tickets || lists.recent_tickets.length === 0" class="p-8 text-center">
                                    <InboxIcon class="h-10 w-10 text-gray-200 mx-auto mb-2" />
                                    <p class="text-gray-400 text-sm">No hay tickets recientes.</p>
                                </div>
                                <div v-else class="divide-y divide-gray-50 max-h-[400px] overflow-y-auto">
                                    <Link 
                                        v-for="ticket in lists.recent_tickets" 
                                        :key="ticket.id"
                                        :href="route('tickets.show', ticket.id)"
                                        class="p-4 flex items-center hover:bg-gray-50 transition-colors group"
                                    >
                                        <div class="flex-1 min-w-0 pr-4">
                                            <div class="flex items-center space-x-2 mb-1">
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-tighter" :class="{
                                                    'bg-blue-100 text-blue-700': ticket.status === 'Nuevos',
                                                    'bg-yellow-100 text-yellow-700': ticket.status === 'En Proceso',
                                                    'bg-purple-100 text-purple-700': ticket.status === 'En Revisión',
                                                    'bg-orange-100 text-orange-700': ticket.status === 'Ajustes',
                                                    'bg-green-100 text-green-700': ticket.status === 'Completados',
                                                }">
                                                    {{ ticket.status }}
                                                </span>
                                                <span class="text-xs text-gray-400 font-medium">#{{ ticket.id }}</span>
                                                <span v-if="ticket.creator" class="text-xs text-gray-500 font-bold truncate">/ {{ ticket.creator?.name }}</span>
                                                <span v-if="ticket.assigned" class="text-xs text-blue-500 font-bold truncate">• Asignado: {{ ticket.assigned?.name }}</span>
                                            </div>
                                            <h4 class="text-sm font-bold text-gray-900 group-hover:text-blue-600 transition-colors truncate">
                                                {{ ticket.title }}
                                            </h4>
                                            <div class="flex items-center mt-2 text-[11px] text-gray-500 space-x-3 font-medium">
                                                <span class="flex items-center">
                                                    <CalendarIcon class="h-3.5 w-3.5 mr-1 opacity-70" />
                                                    {{ formatDate(ticket.created_at) }}
                                                </span>
                                                <span class="flex items-center">
                                                    <ChatBubbleOvalLeftEllipsisIcon class="h-3.5 w-3.5 mr-1 opacity-70" />
                                                    {{ ticket.messages?.length || 0 }} mensajes
                                                </span>
                                            </div>
                                        </div>
                                        <div class="shrink-0">
                                            <div class="p-2 rounded-xl bg-gray-50 group-hover:bg-blue-100 transition-colors">
                                                <ChevronRightIcon class="h-5 w-5 text-gray-400 group-hover:text-blue-600" />
                                            </div>
                                        </div>
                                    </Link>
                                </div>
                            </div>
                        </div>

                    </div>
                </template>

                <!-- Vista de Cliente -->
                <template v-else-if="$page.props.auth.user.is_client">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <!-- Bienvenida y Acción Rápida -->
                        <div class="md:col-span-1">
                            <div class="bg-[#264ab3] rounded-3xl p-8 text-white shadow-xl relative overflow-hidden h-full">
                                <div class="relative z-10">
                                    <h3 class="text-2xl font-bold mb-2">¡Hola, {{ $page.props.auth.user.name.split(' ')[0] }}!</h3>
                                    <p class="text-blue-100 mb-8 opacity-80">¿Necesitas ayuda con algún servicio o tienes un nuevo requerimiento?</p>
                                    
                                    <button 
                                        @click="openCreateModal" 
                                        class="inline-flex items-center bg-white text-[#264ab3] px-6 py-3 rounded-2xl font-bold hover:shadow-lg transition-all"
                                    >
                                        <PlusIcon class="h-5 w-5 mr-2" />
                                        Crear Ticket
                                    </button>
                                </div>
                                <!-- Decorative element -->
                                <div class="absolute -bottom-10 -right-10 w-40 h-40 bg-blue-400/20 rounded-full blur-3xl"></div>
                            </div>
                        </div>

                        <!-- Lista de Tickets Recientes -->
                        <div class="md:col-span-2">
                            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden h-full flex flex-col">
                                <div class="p-6 border-b border-gray-50 flex justify-between items-center">
                                    <h3 class="text-lg font-bold text-gray-800 flex items-center">
                                        <InboxIcon class="h-6 w-6 mr-2 text-blue-500" />
                                        Mis Tickets Recientes
                                    </h3>
                                    <Link :href="route('tickets.index')" class="text-xs font-bold text-blue-600 hover:underline px-3 py-1 bg-blue-50 rounded-full">Ver todos</Link>
                                </div>

                                <div class="flex-1 overflow-y-auto">
                                    <div v-if="!lists.tickets || lists.tickets.length === 0" class="p-12 text-center">
                                        <InboxIcon class="h-12 w-12 text-gray-200 mx-auto mb-3" />
                                        <p class="text-gray-400 text-sm">No tienes tickets activos en este momento.</p>
                                    </div>
                                    <div v-else class="divide-y divide-gray-50">
                                        <Link 
                                            v-for="ticket in lists.tickets" 
                                            :key="ticket.id"
                                            :href="route('tickets.show', ticket.id)"
                                            class="p-5 flex items-center hover:bg-gray-50 transition-colors group"
                                        >
                                            <div class="flex-1 min-w-0 pr-4">
                                                <div class="flex items-center space-x-2 mb-1">
                                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-tighter" :class="{
                                                        'bg-blue-100 text-blue-700': ticket.status === 'Nuevos',
                                                        'bg-yellow-100 text-yellow-700': ticket.status === 'En Proceso',
                                                        'bg-purple-100 text-purple-700': ticket.status === 'En Revisión',
                                                        'bg-orange-100 text-orange-700': ticket.status === 'Ajustes',
                                                        'bg-green-100 text-green-700': ticket.status === 'Completados',
                                                    }">
                                                        {{ ticket.status }}
                                                    </span>
                                                    <span class="text-xs text-gray-400 font-medium">#{{ ticket.id }}</span>
                                                </div>
                                                <h4 class="text-sm font-bold text-gray-900 group-hover:text-blue-600 transition-colors truncate">
                                                    {{ ticket.title }}
                                                </h4>
                                                <div class="flex items-center mt-2 text-[11px] text-gray-500 space-x-3 font-medium">
                                                    <span class="flex items-center">
                                                        <CalendarIcon class="h-3.5 w-3.5 mr-1 opacity-70" />
                                                        {{ formatDate(ticket.created_at) }}
                                                    </span>
                                                    <span class="flex items-center">
                                                        <ChatBubbleOvalLeftEllipsisIcon class="h-3.5 w-3.5 mr-1 opacity-70" />
                                                        {{ ticket.messages?.length || 0 }} mensajes
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="shrink-0 flex items-center">
                                                <div v-if="ticket.assigned" class="flex flex-col items-center mr-4">
                                                    <div class="h-8 w-8 rounded-full bg-blue-100 text-[#264ab3] flex items-center justify-center text-xs font-bold border border-blue-200">
                                                        {{ ticket.assigned.name.charAt(0) }}
                                                    </div>
                                                </div>
                                                <div class="p-2 rounded-xl bg-gray-50 group-hover:bg-blue-100 transition-colors">
                                                    <ChevronRightIcon class="h-5 w-5 text-gray-400 group-hover:text-blue-600" />
                                                </div>
                                            </div>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Detalles del Plan -->
                        <div v-if="lists.plan_details" class="md:col-span-3">
                            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                                <div class="p-6 border-b border-gray-50 bg-gray-50/50">
                                    <h3 class="text-lg font-bold text-gray-800 flex items-center">
                                        <Square3Stack3DIcon class="h-6 w-6 mr-2 text-[#264ab3]" />
                                        Detalles de mi plan: <span class="ml-2 text-[#264ab3]">{{ lists.plan_details.business_name }}</span>
                                    </h3>
                                </div>
                                <div class="p-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                        <div 
                                            v-for="item in lists.plan_details.items" 
                                            :key="item.id"
                                            class="p-5 rounded-2xl border border-gray-100 bg-gray-50/30 hover:shadow-md transition-shadow relative overflow-hidden group flex flex-col justify-between"
                                        >
                                            <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
                                                <CheckBadgeIcon class="h-8 w-8 text-[#264ab3]" />
                                            </div>
                                            
                                            <div>
                                                <h4 class="font-bold text-gray-900 mb-2 pr-8">{{ item.concept }}</h4>
                                                <div 
                                                    :class="[
                                                        'text-xs text-gray-500 mb-2 prose prose-sm max-w-none prose-p:leading-relaxed whitespace-pre-line overflow-hidden transition-all duration-300 relative',
                                                        expandedItems[item.id] ? 'max-h-[2000px]' : 'max-h-20'
                                                    ]"
                                                >
                                                    <div v-html="item.description || 'Sin descripción adicional.'"></div>
                                                    <div v-if="!expandedItems[item.id] && item.description && item.description.length > 200" class="absolute bottom-0 left-0 w-full h-8 bg-gradient-to-t from-gray-50/80 to-transparent"></div>
                                                </div>
                                                <button 
                                                    v-if="item.description && item.description.length > 200"
                                                    @click="expandedItems[item.id] = !expandedItems[item.id]"
                                                    class="text-[#264ab3] text-[10px] font-bold hover:underline mb-4 block"
                                                >
                                                    {{ expandedItems[item.id] ? 'Ver menos ↑' : 'Ver descripción completa ↓' }}
                                                </button>
                                            </div>

                                            <div class="space-y-3 pt-4 border-t border-gray-100">
                                                <!-- Initial Price / Contract Value -->
                                                <div class="flex items-center justify-between">
                                                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Inversión Inicial</span>
                                                    <span class="text-sm font-bold text-gray-900">
                                                        {{ formatCurrency(item.unit_price || item.initial_price) }}
                                                    </span>
                                                </div>

                                                <!-- Renewal / Annuality -->
                                                <div v-if="item.renewal_price && item.renewal_price > 0" class="flex flex-col space-y-1 bg-orange-50 p-2.5 rounded-xl border border-orange-100">
                                                    <div class="flex items-center justify-between">
                                                        <div class="flex items-center">
                                                            <CalendarIcon class="h-3.5 w-3.5 mr-1.5 text-orange-600" />
                                                            <span class="text-[10px] font-bold uppercase tracking-wider text-orange-700">Anualidad</span>
                                                        </div>
                                                        <span class="text-xs font-bold text-orange-700">
                                                            {{ formatCurrency(item.renewal_price) }}
                                                        </span>
                                                    </div>
                                                    
                                                    <!-- Next Renewal Date -->
                                                    <div v-if="lists.plan_details.next_renewal_date" class="pt-1 flex items-center justify-between border-t border-orange-200/50 mt-1">
                                                        <span class="text-[9px] font-medium text-orange-600 italic">Vence el:</span>
                                                        <span class="text-[10px] font-bold text-orange-700">{{ formatDate(lists.plan_details.next_renewal_date) }}</span>
                                                    </div>
                                                </div>

                                                <!-- Billing Type Tag (Non-fallback) -->
                                                <div v-if="!item.is_fallback" class="pt-1">
                                                    <span class="text-[9px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-md" :class="item.billing_type === 'monthly' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'">
                                                        {{ item.billing_type === 'monthly' ? 'Iguala Mensual' : 'Pago Único' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </template>

                <!-- Vista para otros roles (Web Dev, Designer, etc) -->
                <template v-else>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl p-12 text-center text-gray-500 border border-gray-100">
                        <div class="p-4 rounded-full bg-gray-50 inline-block mb-4">
                            <HomeIcon class="h-12 w-12 text-gray-300" />
                        </div>
                        <h3 class="text-xl font-bold text-gray-800 mb-1">Bienvenido, {{ $page.props.auth.user.name }}</h3>
                        <p class="text-sm">Explora las herramientas disponibles en el menú lateral.</p>
                    </div>
                </template>

            </div>
        </div>

        <!-- NEW TICKET MODAL -->
        <Modal :show="isCreateModalOpen" @close="closeCreateModal" max-width="2xl">
            <div class="p-8">
                <div class="flex items-center justify-between mb-8 border-b border-gray-100 pb-4">
                    <h2 class="text-2xl font-bold text-gray-900 flex items-center">
                        <InboxIcon class="h-7 w-7 mr-3 text-[#264ab3]" />
                        Nuevo Ticket
                    </h2>
                    <button @click="closeCreateModal" class="text-gray-400 hover:text-gray-600 transition-colors p-2 hover:bg-gray-100 rounded-full">
                        <XMarkIcon class="h-6 w-6" />
                    </button>
                </div>

                <form @submit.prevent="submitCreate">
                    <div class="space-y-6">
                        <div>
                            <InputLabel for="title" value="Título del Ticket" class="font-bold text-gray-700" />
                            <TextInput
                                id="title"
                                type="text"
                                class="mt-1 block w-full border-gray-200 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-2xl shadow-sm"
                                v-model="createForm.title"
                                required
                                autofocus
                                placeholder="Ej: Problema con el acceso a correos o nueva campaña..."
                            />
                            <InputError class="mt-2" :message="createForm.errors.title" />
                        </div>

                        <div>
                            <InputLabel for="priority" value="¿Qué tan urgente es?" class="font-bold text-gray-700" />
                            <select
                                id="priority"
                                class="mt-1 block w-full border-gray-200 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-2xl shadow-sm"
                                v-model="createForm.priority"
                                required
                            >
                                <option value="Baja">Baja (Varios días)</option>
                                <option value="Media">Media (Normal)</option>
                                <option value="Alta">Alta (Próximas 24-48h)</option>
                                <option value="Urgente">Urgente (Crítico)</option>
                            </select>
                            <InputError class="mt-2" :message="createForm.errors.priority" />
                        </div>

                        <div>
                            <InputLabel for="content" value="Descripción detallada" class="font-bold text-gray-700" />
                            <div class="mt-1">
                                <Wysiwyg 
                                    v-model="createForm.content"
                                    placeholder="Explícanos tu requerimiento a detalle..."
                                    class="rounded-2xl"
                                />
                            </div>
                            <InputError class="mt-2" :message="createForm.errors.content" />
                        </div>

                        <!-- Multi-file Upload -->
                        <div class="pt-2">
                            <InputLabel value="Adjuntar Archivos (Opcional)" class="font-bold text-gray-700" />
                            <div class="mt-1 flex flex-col space-y-3">
                                <label class="flex items-center justify-center px-4 py-6 bg-gray-50 border-2 border-dashed border-gray-200 rounded-2xl hover:bg-blue-50 hover:border-[#264ab3] transition-all cursor-pointer group">
                                    <input 
                                        type="file" 
                                        multiple 
                                        @change="e => createForm.files = Array.from(e.target.files)"
                                        class="hidden" 
                                    />
                                    <PaperClipIcon class="h-6 w-6 mr-3 text-gray-400 group-hover:text-[#264ab3]" />
                                    <span class="text-sm text-gray-500 group-hover:text-[#264ab3] font-bold">
                                        Arrastra o selecciona archivos...
                                    </span>
                                </label>
                                
                                <!-- Preview of selected files -->
                                <div v-if="createForm.files.length > 0" class="flex flex-wrap gap-2 pt-2">
                                    <div v-for="(file, idx) in createForm.files" :key="idx" class="flex items-center bg-blue-50 text-blue-700 px-4 py-2 rounded-xl text-[11px] font-bold border border-blue-100">
                                        <PaperClipIcon class="h-3 w-3 mr-2" />
                                        <span class="truncate max-w-[150px]">{{ file.name }}</span>
                                        <button type="button" @click="createForm.files.splice(idx, 1)" class="ml-3 text-red-500 hover:text-red-700">
                                            <XMarkIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <InputError class="mt-2" :message="createForm.errors.files" />
                        </div>
                    </div>

                    <div class="mt-10 flex justify-end space-x-3">
                        <SecondaryButton @click="closeCreateModal" class="rounded-2xl px-8 py-3 !border-gray-200">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton
                            class="rounded-2xl px-10 py-3 !bg-[#264ab3] hover:!bg-[#193074] shadow-lg shadow-blue-200"
                            :class="{ 'opacity-25': createForm.processing }"
                            :disabled="createForm.processing"
                        >
                            Crear Ticket
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
