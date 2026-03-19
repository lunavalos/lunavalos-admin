<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    services: Array,
});

const form = useForm({});
const page = usePage();
const flashMessage = computed(() => page.props.flash?.message);

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(value);
};

const calculateProfit = (service) => {
    const totalCosts = service.costs ? service.costs.reduce((sum, c) => sum + (Number(c.price) * Number(c.quantity)), 0) : 0;
    return service.price - totalCosts;
};

const deleteService = (id) => {
    if (confirm('¿Estás seguro de que deseas eliminar este servicio?')) {
        form.delete(route('services.destroy', id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Servicios Base" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Catálogo de Servicios
                </h2>
                <Link
                    v-if="$page.props.auth.user.is_admin || $page.props.auth.user.permissions.includes('Crear Servicios')"
                    :href="route('services.create')"
                    class="btn bg-primary hover:bg-secondary text-white font-bold py-2 px-4 rounded"
                >
                    Añadir Servicio
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
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600">Tipo</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600">Nombre del Servicio</th>

                                    <th class="p-3 uppercase font-medium text-sm text-gray-600 text-center" v-if="$page.props.auth.user.is_admin">Tipo Cobro</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600 text-right" v-if="$page.props.auth.user.is_admin">Precio</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600 text-right" v-if="$page.props.auth.user.is_admin">Utilidad Est.</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600 text-right" v-if="$page.props.auth.user.is_admin || $page.props.auth.user.permissions.includes('Editar Servicios') || $page.props.auth.user.permissions.includes('Eliminar Servicios')">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="service in services" :key="service.id" class="border-b hover:bg-gray-50 transition">
                                    <td class="p-3">{{ service.id }}</td>
                                    <td class="p-3">
                                        <span v-if="service.is_package" class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded border border-blue-200">📦 Paquete</span>
                                        <span v-else class="text-xs text-gray-500">Servicio</span>
                                    </td>
                                    <td class="p-3 font-semibold text-primary">
                                        {{ service.name }}
                                        <div v-if="service.is_package && service.services.length > 0" class="text-xs font-normal text-gray-500 mt-1">
                                            Incluye: {{ service.services.map(s => s.name).join(', ') }}
                                        </div>
                                    </td>

                                    <td class="p-3 text-center" v-if="$page.props.auth.user.is_admin">
                                        <span v-if="service.billing_type === 'unique'" class="inline-block bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded">Pago Único</span>
                                        <span v-else class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Mensualidad</span>
                                    </td>
                                    <td class="p-3 text-right font-medium" v-if="$page.props.auth.user.is_admin">
                                        {{ formatCurrency(service.price) }}
                                    </td>
                                    <td class="p-3 text-right font-bold" v-if="$page.props.auth.user.is_admin" :class="calculateProfit(service) >= 0 ? 'text-green-600' : 'text-red-600'">
                                        {{ formatCurrency(calculateProfit(service)) }}
                                    </td>
                                    <td class="p-3 text-right space-x-1" v-if="$page.props.auth.user.is_admin || $page.props.auth.user.permissions.includes('Editar Servicios') || $page.props.auth.user.permissions.includes('Eliminar Servicios')">
                                        <div class="group relative inline-block">
                                            <Link
                                                v-if="$page.props.auth.user.is_admin || $page.props.auth.user.permissions.includes('Editar Servicios')"
                                                :href="route('services.edit', service.id)"
                                                class="text-gray-600 hover:text-blue-600 hover:bg-blue-50 p-2 rounded-full transition-colors inline-flex items-center"
                                            >
                                                <PencilSquareIcon class="w-5 h-5" />
                                            </Link>
                                            <span class="absolute bottom-full right-0 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 whitespace-nowrap pointer-events-none transition-opacity z-10">Editar</span>
                                        </div>
                                        <div class="group relative inline-block">
                                            <button
                                                v-if="$page.props.auth.user.is_admin || $page.props.auth.user.permissions.includes('Eliminar Servicios')"
                                                @click="deleteService(service.id)"
                                                class="text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-full transition-colors inline-flex items-center"
                                            >
                                                <TrashIcon class="w-5 h-5" />
                                            </button>
                                            <span class="absolute bottom-full right-0 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 whitespace-nowrap pointer-events-none transition-opacity z-10">Eliminar</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="services.length === 0">
                                    <td colspan="6" class="p-4 text-center text-gray-500">
                                        No se han agregado servicios a tu catálogo.
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
