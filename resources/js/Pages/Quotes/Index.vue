<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { EyeIcon, PencilSquareIcon, TrashIcon, DocumentTextIcon, LinkIcon, CheckCircleIcon, UserPlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    quotes: Array,
});

const form = useForm({});
const page = usePage();
const flashMessage = computed(() => page.props.flash?.message);

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('es-MX', { year: 'numeric', month: 'short', day: 'numeric'}).format(date);
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(value || 0);
};

const calculateQuoteProfit = (quote) => {
    let totalProfit = 0;
    if (quote.items) {
        quote.items.forEach(item => {
            let itemCostPerUnit = 0;
            if (item.costs) {
                itemCostPerUnit = item.costs.reduce((sum, c) => sum + (Number(c.price) * Number(c.quantity)), 0);
            }
            totalProfit += (Number(item.unit_price) - itemCostPerUnit) * Number(item.quantity);
        });
    }
    return totalProfit;
};
const deleteQuote = (id) => {
    if (confirm('¿Estás seguro de que deseas eliminar esta cotización?')) {
        form.delete(route('quotes.destroy', id), {
            preserveScroll: true,
        });
    }
};

const generateContract = (id) => {
    form.post(route('quotes.generateContract', id), {
        preserveScroll: true,
    });
};

const copyContractLink = (token) => {
    // Generate absolute URL manually because route() might return path
    const url = window.location.origin + '/contratodeservicio/' + token;
    navigator.clipboard.writeText(url).then(() => {
        alert('Enlace copiado al portapapeles: ' + url);
    });
};

const changeStatus = (id, newStatus, quote) => {
    if (confirm(`¿Marcar cotización como ${newStatus}?`)) {
        router.post(route('quotes.status', id), { status: newStatus }, {
            preserveScroll: true,
            onSuccess: () => {
                if (newStatus === 'Aceptada') {
                    // Redirect to create client with pre-filled details
                    const servicesArr = quote.items ? quote.items.map(item => item.concept) : [];
                    const params = new URLSearchParams({
                        quote_id: quote.id,
                        business_name: quote.client_name || '',
                        contact_name: quote.contact_name || '',
                        email: quote.email || '',
                        phone: quote.phone || '',
                        package_services: servicesArr.join(' + '),
                    }).toString();
                    window.location.href = route('clients.create') + '?' + params;
                }
            }
        });
    }
};
</script>

<template>
    <Head title="Cotizaciones" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Historial de Cotizaciones
                </h2>
                <Link
                    :href="route('quotes.create')"
                    class="btn bg-primary hover:bg-secondary text-white font-bold py-2 px-4 rounded"
                >
                    Nueva Cotización
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="container mx-auto">
                <div v-if="flashMessage" class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                  <span class="block sm:inline">{{ flashMessage }}</span>
                </div>
                
                <div class="card overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 overflow-x-auto">
                        
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600">ID</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600">Cliente</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600">Estado</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600">Total</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600">Utilidad Est.</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600">Emisión</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="quote in quotes" :key="quote.id" class="border-b hover:bg-gray-50 transition">
                                    <td class="p-3 font-semibold text-gray-500">#{{ quote.id }}</td>
                                    <td class="p-3 font-semibold text-primary">{{ quote.client_name }}</td>
                                    <td class="p-3">
                                        <span v-if="quote.status === 'Aceptada'" class="bg-green-100 text-green-800 text-xs font-semibold px-2 py-1 rounded">Aceptada</span>
                                        <span v-else-if="quote.status === 'Contrato Firmado'" class="bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-1 rounded">Contrato Firmado</span>
                                        <span v-else-if="quote.status === 'Completada'" class="bg-purple-100 text-purple-800 text-xs font-semibold px-2 py-1 rounded">Completada</span>
                                        <span v-else-if="quote.status === 'Rechazada'" class="bg-red-100 text-red-800 text-xs font-semibold px-2 py-1 rounded">Rechazada</span>
                                        <span v-else class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-1 rounded">Pendiente</span>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex flex-col">
                                            <span v-if="quote.total_unique > 0" class="font-bold text-gray-900">{{ formatCurrency(quote.total_unique) }}</span>
                                            <span v-if="quote.total_monthly > 0" class="text-xs text-indigo-600 font-semibold">{{ formatCurrency(quote.total_monthly) }}/mes</span>
                                            <span v-if="quote.total_unique === 0 && quote.total_monthly === 0" class="text-gray-400">-</span>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <span class="font-bold" :class="calculateQuoteProfit(quote) >= 0 ? 'text-green-600' : 'text-red-600'">
                                            {{ formatCurrency(calculateQuoteProfit(quote)) }}
                                        </span>
                                    </td>
                                    <td class="p-3 whitespace-nowrap">{{ formatDate(quote.issue_date) }}</td>
                                    <td class="p-3 text-right space-x-1">
                                        <!-- PDF button -->
                                        <div class="group relative inline-block">
                                            <a
                                                :href="route('quotes.show', quote.id)"
                                                target="_blank"
                                                class="text-[#264ab3] hover:text-[#193074] hover:bg-blue-50 p-2 rounded-full transition-colors inline-flex items-center"
                                            >
                                                <EyeIcon class="w-5 h-5" />
                                            </a>
                                            <span class="absolute bottom-full right-0 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 whitespace-nowrap pointer-events-none transition-opacity z-10">Ver PDF</span>
                                        </div>

                                        <template v-if="quote.status !== 'Completada' && !quote.client">
                                            <!-- Edit button - Hide if signed or has client -->
                                            <div v-if="quote.status !== 'Contrato Firmado' && !quote.client" class="group relative inline-block">
                                                <Link
                                                    :href="route('quotes.edit', quote.id)"
                                                    class="text-gray-600 hover:text-blue-600 hover:bg-blue-50 p-2 rounded-full transition-colors inline-flex items-center"
                                                >
                                                    <PencilSquareIcon class="w-5 h-5" />
                                                </Link>
                                                <span class="absolute bottom-full right-0 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 whitespace-nowrap pointer-events-none transition-opacity z-10">Editar</span>
                                            </div>

                                            <!-- Delete button - Hide if signed or has client -->
                                            <div v-if="quote.status !== 'Contrato Firmado' && !quote.client" class="group relative inline-block">
                                                <button
                                                    @click="deleteQuote(quote.id)"
                                                    class="text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-full transition-colors inline-flex items-center"
                                                >
                                                    <TrashIcon class="w-5 h-5" />
                                                </button>
                                                <span class="absolute bottom-full right-0 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 whitespace-nowrap pointer-events-none transition-opacity z-10">Eliminar</span>
                                            </div>

                                            <!-- Contract Generation / Copy -->
                                            <template v-if="quote.status !== 'Contrato Firmado'">
                                                <div v-if="!quote.contract" class="group relative inline-block">
                                                    <button
                                                        @click="generateContract(quote.id)"
                                                        class="text-purple-500 hover:text-purple-700 hover:bg-purple-50 p-2 rounded-full transition-colors inline-flex items-center"
                                                    >
                                                        <DocumentTextIcon class="w-5 h-5" />
                                                    </button>
                                                    <span class="absolute bottom-full right-0 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 whitespace-nowrap pointer-events-none transition-opacity z-10">Crear Contrato</span>
                                                </div>
                                                <div v-else-if="quote.contract.status === 'pending'" class="group relative inline-block">
                                                    <button
                                                        @click="copyContractLink(quote.contract.token)"
                                                        class="text-teal-500 hover:text-teal-700 hover:bg-teal-50 p-2 rounded-full transition-colors inline-flex items-center"
                                                    >
                                                        <LinkIcon class="w-5 h-5" />
                                                    </button>
                                                    <span class="absolute bottom-full right-0 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 whitespace-nowrap pointer-events-none transition-opacity z-10">Copiar Link</span>
                                                </div>
                                            </template>
                                            
                                            <!-- Actions dropdown/buttons -->
                                            <div v-if="quote.status !== 'Aceptada' && quote.status !== 'Contrato Firmado' && (!quote.contract || quote.contract.status !== 'signed')" class="group relative inline-block">
                                                <button
                                                    @click="changeStatus(quote.id, 'Aceptada', quote)"
                                                    class="text-green-500 hover:text-green-700 hover:bg-green-50 p-2 rounded-full transition-colors inline-flex items-center"
                                                >
                                                    <CheckCircleIcon class="w-5 h-5" />
                                                </button>
                                                <span class="absolute bottom-full right-0 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 whitespace-nowrap pointer-events-none transition-opacity z-10">Forzar Aceptación</span>
                                            </div>
                                            <div v-else-if="(quote.status === 'Aceptada' || quote.status === 'Contrato Firmado' || (quote.contract && quote.contract.status === 'signed')) && !quote.client" class="group relative inline-block">
                                                <Link
                                                    :href="route('clients.create') + '?quote_id=' + quote.id + '&business_name=' + encodeURIComponent(quote.client_name)"
                                                    class="text-blue-500 hover:text-blue-700 hover:bg-blue-50 p-2 rounded-full transition-colors inline-flex items-center"
                                                >
                                                    <UserPlusIcon class="w-5 h-5" />
                                                </Link>
                                                <span class="absolute bottom-full right-0 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 whitespace-nowrap pointer-events-none transition-opacity z-10">Completar Registro</span>
                                            </div>
                                        </template>
                                    </td>
                                </tr>
                                <tr v-if="quotes.length === 0">
                                    <td colspan="5" class="p-4 text-center text-gray-500">
                                        No has generado ninguna cotización aún.
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
