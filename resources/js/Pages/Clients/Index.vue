<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    TrashIcon, 
    PencilSquareIcon, 
    EnvelopeIcon, 
    CheckBadgeIcon, 
    EyeIcon, 
    EyeSlashIcon, 
    XMarkIcon, 
    MagnifyingGlassIcon, 
    ArchiveBoxArrowDownIcon, 
    ArrowRightStartOnRectangleIcon,
    BarsArrowDownIcon,
    BarsArrowUpIcon,
    ArrowsUpDownIcon
} from '@heroicons/vue/24/outline';
import { ref, computed, nextTick } from 'vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    clients: Array,
    activeServices: Array,
});

const form = useForm({});

const searchQuery = ref('');
const currentTab = ref('servicios'); // 'servicios', 'activos' or 'historicos'
const sortBy = ref('created_at');
const sortOrder = ref('desc');

const filteredClients = computed(() => {
    let list = [...(props.clients || [])]; // Create a copy to avoid mutating props
    
    // Filter by tab
    if (currentTab.value === 'activos') {
        list = list.filter(c => !c.is_historical);
    } else {
        list = list.filter(c => c.is_historical);
    }
    
    // Filter by search
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(c => 
            (c.business_name && c.business_name.toLowerCase().includes(q)) ||
            (c.contact_name && c.contact_name.toLowerCase().includes(q)) ||
            (c.email && c.email.toLowerCase().includes(q))
        );
    }

    // Sort logic
    list.sort((a, b) => {
        let valA, valB;
        
        if (sortBy.value === 'name') {
            valA = a.business_name?.toLowerCase() || '';
            valB = b.business_name?.toLowerCase() || '';
        } else if (sortBy.value === 'renewal') {
            valA = a.next_renewal_date || (sortOrder.value === 'asc' ? '9999-12-31' : '0000-00-00');
            valB = b.next_renewal_date || (sortOrder.value === 'asc' ? '9999-12-31' : '0000-00-00');
        } else if (sortBy.value === 'amount') {
            valA = parseFloat(a.renewal_amount) || 0;
            valB = parseFloat(b.renewal_amount) || 0;
        } else { // created_at
            valA = a.created_at;
            valB = b.created_at;
        }
        
        if (valA < valB) return sortOrder.value === 'asc' ? -1 : 1;
        if (valA > valB) return sortOrder.value === 'asc' ? 1 : -1;
        return 0;
    });
    
    return list;
});

const filteredServices = computed(() => {
    let list = [...(props.activeServices || [])];
    
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(s => 
            (s.service_name && s.service_name.toLowerCase().includes(q)) ||
            (s.client && s.client.business_name && s.client.business_name.toLowerCase().includes(q))
        );
    }
    
    return list;
});

const toggleHistorical = (id, isHistorical) => {
    const action = isHistorical ? 'restaurar' : 'mover a históricos';
    if (confirm(`¿Estás seguro de ${action} este cliente?`)) {
        form.post(route('clients.toggleHistorical', id), {
            preserveScroll: true
        });
    }
};

const deleteClient = (id) => {
    if (confirm('¿Estás seguro de que deseas eliminar este cliente? Esta acción no se puede deshacer.')) {
        form.delete(route('clients.destroy', id), {
            preserveScroll: true
        });
    }
};

const renewClient = (id) => {
    if (confirm('¿Confirmas que el cliente ha pagado su renovación? El sistema sumará automáticamente 1 año completo a su fecha de vigencia.')) {
        form.post(route('clients.renew', id), {
            preserveScroll: true
        });
    }
};

const formatCurrency = (value) => {
    if (!value) return '$0.00';
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(value);
};

const formatDate = (dateString) => {
    if (!dateString) return '---';
    // Laravel might send full ISO "2026-03-03T00:00:00.000000Z"
    const cleanDate = dateString.split('T')[0];
    const date = new Date(cleanDate + 'T00:00:00');
    return date.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
};

const daysUntilRenewal = (dateString) => {
    if (!dateString) return null;
    const cleanDate = dateString.split('T')[0];
    const targetDate = new Date(cleanDate + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const diffTime = targetDate - today;
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
};

const getRenewalBadgeClass = (days) => {
    if (days === null) return 'bg-gray-100 dark:bg-zinc-800 text-gray-800 dark:text-zinc-300';
    if (days < 0) return 'bg-red-100 dark:bg-rose-900/40 text-red-800 dark:text-rose-300'; // Expirado
    if (days <= 30) return 'bg-orange-100 dark:bg-amber-900/40 text-orange-800 dark:text-amber-300'; // Próximo a vencer
    return 'bg-green-100 dark:bg-emerald-900/40 text-green-800 dark:text-emerald-300'; // Vigente normal
};

</script>

<template>
    <Head title="Directorio de Clientes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">
                    Directorio de Clientes
                </h2>
                    <div class="flex space-x-3" v-if="$page.props.auth.user.is_admin || $page.props.auth.user.permissions.includes('Crear Clientes')">
                        <Link
                            :href="route('clients.import')"
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded shadow-md transition-colors flex items-center"
                        >
                            Importar Excel/CSV
                        </Link>
                        <Link
                            :href="route('clients.create')"
                            class="btn bg-[#264ab3] hover:bg-[#193074] text-white font-bold py-2 px-4 rounded shadow-md transition-colors"
                        >
                            + Nuevo Cliente
                        </Link>
                    </div>
            </div>
        </template>

        <div class="py-12">
            <div class="container mx-auto">
                <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-sm sm:rounded-lg card border border-gray-100 dark:border-zinc-800">
                    <!-- Search and Tabs -->
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-zinc-800 flex flex-col xl:flex-row justify-between items-center gap-4 bg-white dark:bg-zinc-900">
                        <div class="flex space-x-1 p-1 bg-gray-100 dark:bg-zinc-950 rounded-lg shrink-0">
                            <button 
                                @click="currentTab = 'servicios'"
                                :class="['px-4 py-1.5 text-sm font-semibold rounded-md transition-all', currentTab === 'servicios' ? 'bg-white dark:bg-zinc-900 shadow-sm text-[#264ab3] dark:text-blue-400' : 'text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-gray-200']"
                            >
                                Servicios Activos
                            </button>
                            <button 
                                @click="currentTab = 'activos'"
                                :class="['px-4 py-1.5 text-sm font-semibold rounded-md transition-all', currentTab === 'activos' ? 'bg-white dark:bg-zinc-900 shadow-sm text-[#264ab3] dark:text-blue-400' : 'text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-gray-200']"
                            >
                                Clientes Activos
                            </button>
                            <button 
                                @click="currentTab = 'historicos'"
                                :class="['px-4 py-1.5 text-sm font-semibold rounded-md transition-all', currentTab === 'historicos' ? 'bg-white dark:bg-zinc-900 shadow-sm text-[#264ab3] dark:text-blue-400' : 'text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-gray-200']"
                            >
                                Históricos
                            </button>
                        </div>
                        
                        <div class="flex flex-col md:flex-row items-center gap-3 w-full xl:w-auto">
                            <!-- Sorting -->
                            <div class="flex items-center space-x-2 bg-gray-50 dark:bg-zinc-950 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-zinc-800">
                                <span class="text-xs font-bold text-gray-400 dark:text-zinc-500 uppercase tracking-wider">Ordenar:</span>
                                <select 
                                    v-model="sortBy"
                                    class="bg-transparent border-none text-sm font-bold text-[#264ab3] focus:ring-0 py-0 pl-1 pr-8"
                                >
                                    <option value="created_at">Fecha Registro</option>
                                    <option value="name">Alfabético</option>
                                    <option value="renewal">Fecha Renovación</option>
                                    <option value="amount">Monto</option>
                                </select>
                                <button 
                                    @click="sortOrder = sortOrder === 'asc' ? 'desc' : 'asc'"
                                    class="p-1 hover:bg-white dark:hover:bg-zinc-900 rounded transition-all text-[#264ab3] dark:text-blue-400"
                                    :title="sortOrder === 'asc' ? 'Ascendente' : 'Descendente'"
                                >
                                    <BarsArrowDownIcon v-if="sortOrder === 'desc'" class="h-5 w-5" />
                                    <BarsArrowUpIcon v-else class="h-5 w-5" />
                                </button>
                            </div>

                            <!-- Search -->
                            <div class="relative w-full md:w-72">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <MagnifyingGlassIcon class="h-4 w-4 text-gray-400" />
                                </div>
                                <input 
                                    v-model="searchQuery"
                                    type="text" 
                                    placeholder="Buscar cliente..." 
                                    class="w-full pl-9 pr-3 py-2 border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 text-gray-700 dark:text-gray-200 rounded-lg text-sm focus:ring-[#264ab3] focus:border-[#264ab3] shadow-sm"
                                />
                            </div>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto" v-if="currentTab !== 'servicios'">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-zinc-950 border-b border-gray-100 dark:border-zinc-800 text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase">
                                    <th class="p-4" style="width: 25%">Empresa / Contacto</th>
                                    <th class="p-4 text-center" style="width: 15%" v-if="$page.props.auth.user.is_admin">Servicios Activos</th>
                                    <th class="p-4 text-center">Dominios/Hosting</th>
                                    <th class="p-4 text-center" style="width: 15%">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr 
                                    v-for="client in filteredClients" 
                                    :key="client.id" 
                                    class="border-b border-gray-50 dark:border-zinc-800/50 hover:bg-gray-50 dark:hover:bg-zinc-800/30 transition"
                                >
                                    <td class="p-4">
                                        <div class="font-bold text-[#264ab3] dark:text-blue-400">{{ client.business_name }}</div>
                                        <div class="text-sm text-gray-500 dark:text-zinc-400 mt-1 flex flex-col space-y-1">
                                            <span v-if="client.contact_name" class="flex items-center">
                                                <span class="mr-1">👤</span> {{ client.contact_name }}
                                            </span>
                                            <span v-if="client.email" class="flex items-center text-gray-400 dark:text-zinc-500">
                                                <EnvelopeIcon class="w-3 h-3 justify-center mr-1"/>
                                                <a :href="'mailto:'+client.email" class="hover:underline">{{ client.email }}</a>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-center" v-if="$page.props.auth.user.is_admin">
                                        <div class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                            {{ client.active_services_count || 0 }} activos
                                        </div>
                                    </td>
                                    <td class="p-4 text-center text-sm text-gray-600 dark:text-zinc-400">
                                        <div v-if="client.domain_names" class="font-medium truncate max-w-[150px] mx-auto text-gray-700 dark:text-gray-200" :title="client.domain_names">
                                            🌐 {{ client.domain_names }}
                                        </div>
                                        <div v-if="client.domain_provider" class="text-xs text-gray-400 dark:text-zinc-500">Prov: {{ client.domain_provider }}</div>
                                        <div v-if="!client.domain_names && !client.domain_provider" class="text-gray-300 dark:text-zinc-800 italic">No especificado</div>
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="flex justify-center items-center space-x-3">
                                            <Link 
                                                :href="route('clients.show', client.id)"
                                                class="text-blue-500 hover:text-blue-700 transition"
                                                title="Ver Expediente de Cliente"
                                            >
                                                <EyeIcon class="h-5 w-5" />
                                            </Link>
                                            <Link 
                                                v-if="$page.props.auth.user.is_admin || $page.props.auth.user.permissions.includes('Editar Clientes')"
                                                :href="route('clients.edit', client.id)" 
                                                class="text-blue-500 hover:text-blue-700 transition"
                                                title="Editar Cliente"
                                            >
                                                <PencilSquareIcon class="h-5 w-5" />
                                            </Link>
                                            <button 
                                                v-if="client.next_renewal_date && $page.props.auth.user.is_admin"
                                                @click="renewClient(client.id)" 
                                                class="text-green-500 hover:text-green-700 transition"
                                                title="Marcar como Pagado (+1 Año)"
                                            >
                                                <CheckBadgeIcon class="h-5 w-5" />
                                            </button>
                                            <button 
                                                v-if="$page.props.auth.user.is_admin || $page.props.auth.user.permissions.includes('Eliminar Clientes')"
                                                @click="deleteClient(client.id)" 
                                                class="text-red-400 hover:text-red-600 transition"
                                                title="Eliminar Cliente"
                                            >
                                                <TrashIcon class="h-5 w-5" />
                                            </button>
                                            <button 
                                                v-if="$page.props.auth.user.is_admin"
                                                @click="toggleHistorical(client.id, client.is_historical)" 
                                                class="text-orange-400 hover:text-orange-600 transition ml-2 border-l border-gray-200 dark:border-zinc-800 pl-3"
                                                :title="client.is_historical ? 'Restaurar a Activos' : 'Mover a Históricos'"
                                            >
                                                <ArchiveBoxArrowDownIcon v-if="!client.is_historical" class="h-5 w-5" />
                                                <ArrowRightStartOnRectangleIcon v-else class="h-5 w-5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredClients.length === 0">
                                    <td colspan="5" class="p-8 text-center text-gray-500 dark:text-zinc-500 italic">
                                        No se encontraron clientes registrados en la base de datos. ¡Registra el primero!
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="overflow-x-auto" v-else>
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-zinc-950 border-b border-gray-100 dark:border-zinc-800 text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase">
                                    <th class="p-4" style="width: 25%">Cliente</th>
                                    <th class="p-4" style="width: 25%">Servicio</th>
                                    <th class="p-4" style="width: 20%" v-if="$page.props.auth.user.is_admin">Renovación</th>
                                    <th class="p-4 text-center" v-if="$page.props.auth.user.is_admin">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr 
                                    v-for="service in filteredServices" 
                                    :key="service.id" 
                                    class="border-b border-gray-50 dark:border-zinc-800/50 hover:bg-gray-50 dark:hover:bg-zinc-800/30 transition"
                                >
                                    <td class="p-4 text-[#264ab3] dark:text-blue-400 font-bold">
                                        <Link :href="route('clients.show', service.client_id)" class="hover:underline">
                                            {{ service.client?.business_name || 'Desconocido' }}
                                        </Link>
                                    </td>
                                    <td class="p-4 font-medium text-gray-800 dark:text-gray-200">
                                        {{ service.service_name }}
                                        <div class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">
                                            {{ service.billing_type === 'unique' ? 'Pago Único' : (service.billing_type === 'monthly' ? 'Iguala Mensual' : 'Anualidad') }}
                                        </div>
                                    </td>
                                    <td class="p-4" v-if="$page.props.auth.user.is_admin">
                                        <div v-if="service.renewal_date" class="font-medium text-gray-800 dark:text-gray-200">
                                            {{ formatDate(service.renewal_date) }}
                                        </div>
                                        <div v-else class="text-gray-400 dark:text-zinc-500 text-sm">---</div>
                                        
                                        <div class="mt-1" v-if="service.billing_type !== 'unique'">
                                            <span 
                                                v-if="service.renewal_date"
                                                class="px-2 py-1 text-xs font-semibold rounded-full"
                                                :class="getRenewalBadgeClass(daysUntilRenewal(service.renewal_date))"
                                            >
                                                {{ daysUntilRenewal(service.renewal_date) < 0 ? 'Vencido (' + Math.abs(daysUntilRenewal(service.renewal_date)) + ' días)' : 
                                                   (daysUntilRenewal(service.renewal_date) <= 30 ? 'En ' + daysUntilRenewal(service.renewal_date) + ' días' : 'Vigente') 
                                                }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-center font-bold text-gray-700 dark:text-gray-200" v-if="$page.props.auth.user.is_admin">
                                        {{ service.renewal_amount ? formatCurrency(service.renewal_amount) : '---' }}
                                    </td>
                                </tr>
                                <tr v-if="filteredServices.length === 0">
                                    <td colspan="4" class="p-8 text-center text-gray-500 dark:text-zinc-500 italic">
                                        No se encontraron servicios activos.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
