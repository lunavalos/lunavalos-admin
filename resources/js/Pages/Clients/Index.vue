<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { TrashIcon, PencilSquareIcon, EnvelopeIcon, CheckBadgeIcon, EyeIcon, EyeSlashIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';

const props = defineProps({
    clients: Array,
});

const form = useForm({});

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
    if (days === null) return 'bg-gray-100 text-gray-800';
    if (days < 0) return 'bg-red-100 text-red-800'; // Expirado
    if (days <= 30) return 'bg-orange-100 text-orange-800'; // Próximo a vencer
    return 'bg-green-100 text-green-800'; // Vigente normal
};

const isModalOpen = ref(false);
const selectedClient = ref(null);
const showClientVault = ref(false);

const viewTechnicalDetails = (client) => {
    selectedClient.value = client;
    isModalOpen.value = true;
    showClientVault.value = false;
};

const closeModal = () => {
    isModalOpen.value = false;
    selectedClient.value = null;
    showClientVault.value = false;
};
</script>

<template>
    <Head title="Directorio de Clientes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
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
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg card border border-gray-100">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 border-b text-sm font-semibold text-gray-700 uppercase">
                                    <th class="p-4" style="width: 25%">Empresa / Contacto</th>
                                    <th class="p-4" style="width: 20%" v-if="$page.props.auth.user.is_admin">Renovación</th>
                                    <th class="p-4 text-center" v-if="$page.props.auth.user.is_admin">Monto</th>
                                    <th class="p-4 text-center">Dominios/Hosting</th>
                                    <th class="p-4 text-center" style="width: 15%">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr 
                                    v-for="client in clients" 
                                    :key="client.id" 
                                    class="border-b hover:bg-gray-50 transition"
                                >
                                    <td class="p-4">
                                        <div class="font-bold text-[#264ab3]">{{ client.business_name }}</div>
                                        <div class="text-sm text-gray-500 mt-1 flex flex-col space-y-1">
                                            <span v-if="client.contact_name" class="flex items-center">
                                                <span class="mr-1">👤</span> {{ client.contact_name }}
                                            </span>
                                            <span v-if="client.email" class="flex items-center text-gray-400">
                                                <EnvelopeIcon class="w-3 h-3 justify-center mr-1"/>
                                                <a :href="'mailto:'+client.email" class="hover:underline">{{ client.email }}</a>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="p-4" v-if="$page.props.auth.user.is_admin">
                                        <div v-if="client.next_renewal_date" class="font-medium text-gray-800">
                                            {{ formatDate(client.next_renewal_date) }}
                                        </div>
                                        <div v-else class="text-gray-400 text-sm">Sin fecha designada</div>
                                        
                                        <div class="mt-1">
                                            <span 
                                                v-if="client.next_renewal_date"
                                                class="px-2 py-1 text-xs font-semibold rounded-full"
                                                :class="getRenewalBadgeClass(daysUntilRenewal(client.next_renewal_date))"
                                            >
                                                {{ daysUntilRenewal(client.next_renewal_date) < 0 ? 'Vencido (' + Math.abs(daysUntilRenewal(client.next_renewal_date)) + ' días)' : 
                                                   (daysUntilRenewal(client.next_renewal_date) <= 30 ? 'En ' + daysUntilRenewal(client.next_renewal_date) + ' días' : 'Vigente') 
                                                }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-center font-bold text-gray-700" v-if="$page.props.auth.user.is_admin">
                                        {{ client.renewal_amount ? formatCurrency(client.renewal_amount) : '---' }}
                                    </td>
                                    <td class="p-4 text-center text-sm text-gray-600">
                                        <div v-if="client.domain_names" class="font-medium truncate max-w-[150px] mx-auto" :title="client.domain_names">
                                            🌐 {{ client.domain_names }}
                                        </div>
                                        <div v-if="client.domain_provider" class="text-xs text-gray-400">Prov: {{ client.domain_provider }}</div>
                                        <div v-if="!client.domain_names && !client.domain_provider" class="text-gray-300 italic">No especificado</div>
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="flex justify-center items-center space-x-3">
                                            <button 
                                                @click="viewTechnicalDetails(client)"
                                                class="text-blue-500 hover:text-blue-700 transition"
                                                title="Ver Detalles Técnicos"
                                            >
                                                <EyeIcon class="h-5 w-5" />
                                            </button>
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
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="clients.length === 0">
                                    <td colspan="5" class="p-8 text-center text-gray-500 italic">
                                        No se encontraron clientes registrados en la base de datos. ¡Registra el primero!
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal "Ver Detalles Técnicos" -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModal" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                <h3 class="text-xl leading-6 font-bold text-gray-900 mb-4 pb-2 border-b flex justify-between items-center" id="modal-title">
                                    Activos Técnicos: {{ selectedClient?.business_name }}
                                    <button @click="closeModal" class="text-gray-400 hover:text-gray-500">
                                        <XMarkIcon class="h-6 w-6" />
                                    </button>
                                </h3>
                                
                                <div class="mt-4 space-y-4">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-gray-700 text-sm">
                                        <div><strong class="text-blue-900 block">Dominio(s):</strong> {{ selectedClient?.domain_names || 'N/A' }}</div>
                                        <div><strong class="text-blue-900 block">Prov. Dominio:</strong> {{ selectedClient?.domain_provider || 'N/A' }}</div>
                                        <div><strong class="text-blue-900 block">Prov. Hosting:</strong> {{ selectedClient?.hosting_provider || 'N/A' }}</div>
                                        <div><strong class="text-blue-900 block">Prov. Correos:</strong> {{ selectedClient?.email_provider || 'N/A' }}</div>
                                    </div>
                                    
                                    <div class="mt-6 pt-4 border-t border-gray-100" v-if="selectedClient?.login_credentials || selectedClient?.notes">
                                        <div class="flex justify-between items-center mb-2">
                                            <strong class="text-blue-900">Bóveda de Accesos / Notas:</strong>
                                            <button v-if="selectedClient?.login_credentials" type="button" @click="showClientVault = !showClientVault" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center bg-blue-50 px-2 py-1 rounded">
                                                <template v-if="!showClientVault">
                                                    <EyeIcon class="h-3 w-3 mr-1" /> Revelar
                                                </template>
                                                <template v-else>
                                                    <EyeSlashIcon class="h-3 w-3 mr-1" /> Ocultar
                                                </template>
                                            </button>
                                        </div>
                                        <pre v-if="showClientVault" class="bg-gray-800 text-green-400 p-3 rounded text-sm overflow-x-auto whitespace-pre-wrap">{{ selectedClient?.login_credentials }}</pre>
                                        <pre v-else-if="selectedClient?.login_credentials" class="bg-gray-200 text-transparent select-none p-3 rounded text-sm mt-1">************************
************************</pre>
                                        
                                        <div class="mt-4 bg-yellow-50 p-3 rounded text-sm text-yellow-800 border border-yellow-200" v-if="selectedClient?.notes">
                                            <strong>Notas Generales:</strong><br/>
                                            {{ selectedClient?.notes }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
