<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { DocumentCurrencyDollarIcon, CalendarDaysIcon, PrinterIcon, EnvelopeIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    upcomingRenewals: Array,
    currentRange: String,
});

const filters = ref([
    { label: 'Próximos 30 días', value: 'next_30_days' },
    { label: 'Este Mes', value: 'this_month' },
    { label: 'Próximo Mes', value: 'next_month' },
    { label: 'Recientes y Próximos (Por Defecto)', value: 'default' },
    { label: 'Todos los registros', value: 'all' },
]);

const changeRange = (e) => {
    router.get(route('finances.index'), { range: e.target.value }, { preserveState: true, preserveScroll: true });
};

const sendEmail = (item) => {
    if (!item.email) {
        alert(`No hay ningún correo asociado al cliente ${item.business_name}. Ve a editar el cliente y agrega un correo primero.`);
        return;
    }

    if (!confirm(`¿Estás seguro de enviar el aviso de cobro por correo a ${item.email}?`)) return;

    router.post(route('finances.send-receipt', item.client_id), {
        service: item.service_name,
        amount: item.renewal_amount,
        type: item.billing_type
    }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const formatCurrency = (amount) => {
    if (!amount) return '$0.00';
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const dateStr = dateString.split(' ')[0].split('T')[0]; 
    const date = new Date(dateStr + 'T12:00:00');
    
    if (isNaN(date.getTime())) return dateString;

    return new Intl.DateTimeFormat('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    }).format(date);
};

const isExpired = (dateString) => {
    if (!dateString) return false;
    const dateStr = dateString.split(' ')[0].split('T')[0];
    const date = new Date(dateStr + 'T12:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return date < today;
};
</script>

<template>
    <Head title="Finanzas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center flex-wrap gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 flex items-center gap-2">
                    <DocumentCurrencyDollarIcon class="h-6 w-6 text-green-600" />
                    Finanzas & Renovaciones
                </h2>
                <div class="flex items-center space-x-2">
                    <CalendarDaysIcon class="h-5 w-5 text-gray-500" />
                    <select :value="currentRange" @change="changeRange" class="text-sm border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 py-2 shadow-sm">
                        <option v-for="filter in filters" :key="filter.value" :value="filter.value">
                            {{ filter.label }}
                        </option>
                    </select>
                </div>
            </div>
        </template>

        <div class="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm border border-gray-200 rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Cliente</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Servicio / Paquete</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Fecha de Renovación</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Monto</th>
                                <th scope="col" class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-if="upcomingRenewals.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500 text-sm italic">
                                    No se encontraron renovaciones en el rango de fechas seleccionado.
                                </td>
                            </tr>
                            <tr v-for="(item, index) in upcomingRenewals" :key="index" class="hover:bg-green-50/30 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-bold text-gray-800">{{ item.business_name }}</div>
                                    <div class="text-xs text-gray-500 mt-1">{{ item.contact_name }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="item.billing_type === 'monthly' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'">
                                        {{ item.service_name || 'PND' }}
                                        <span v-if="item.billing_type === 'monthly'" class="ml-1 opacity-75 font-semibold">(Mensual)</span>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold" :class="isExpired(item.next_renewal_date) ? 'text-red-600' : 'text-gray-700'">
                                            {{ formatDate(item.next_renewal_date) }}
                                        </span>
                                        <span v-if="isExpired(item.next_renewal_date)" class="text-[10px] bg-red-100 text-red-700 px-1.5 py-0.5 rounded uppercase font-bold tracking-wide">
                                            Vencido
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-bold text-gray-800">
                                            {{ formatCurrency(item.renewal_amount || 0) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <a :href="route('finances.receipt', { client: item.client_id, service: item.service_name, amount: item.renewal_amount, type: item.billing_type })" target="_blank" class="inline-flex items-center justify-center px-3 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150" title="Ver / Imprimir Recibo">
                                            <PrinterIcon class="h-4 w-4 mr-1.5" /> PDF
                                        </a>
                                        <button type="button" @click="sendEmail(item)" class="inline-flex items-center justify-center px-3 py-2 bg-[#264ab3] border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-[#193074] focus:bg-[#193074] active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-[#264ab3] focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm" title="Enviar por Email">
                                            <EnvelopeIcon class="h-4 w-4 mr-1.5" /> Enviar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
