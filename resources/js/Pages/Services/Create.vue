<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    availableServices: {
        type: Array,
        default: () => []
    }
});

const form = useForm({
    name: '',
    description: '',
    price: 0,
    renewal_price: 0,
    billing_type: 'unique',
    is_package: false,
    services: [],
    costs: [],
});

const addCost = () => {
    form.costs.push({ title: '', quantity: 1, price: 0 });
};

const removeCost = (index) => {
    form.costs.splice(index, 1);
};

const submit = () => {
    form.post(route('services.store'));
};
</script>

<template>
    <Head title="Añadir Servicio" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Añadir Servicio Base
                </h2>
                <Link
                    :href="route('services.index')"
                    class="btn bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded"
                >
                    Volver
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="container mx-auto">
                <div class="card overflow-hidden bg-white shadow-sm sm:rounded-lg max-w-2xl mx-auto">
                    <div class="p-6 text-gray-900">
                        <form @submit.prevent="submit" class="space-y-6">
                            
                            <!-- Nombre -->
                            <div>
                                <InputLabel for="name" value="Nombre del Servicio" class="font-bold text-gray-700" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    v-model="form.name"
                                    required
                                    autofocus
                                    placeholder="Ej. Diseño de Trípticos"
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <!-- Tipo de Producto -->
                            <div class="mb-4">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" v-model="form.is_package" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-700 font-bold">Es un Paquete (agrupa múltiples servicios)</span>
                                </label>
                            </div>

                            <!-- Servicios Incluidos (Solo si es paquete) -->
                            <div v-if="form.is_package" class="p-4 bg-gray-50 border border-gray-200 rounded-md">
                                <InputLabel value="Servicios Incluidos en el Paquete" class="font-bold text-gray-700 mb-2" />
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    <label v-for="service in availableServices" :key="service.id" class="inline-flex items-center p-2 hover:bg-white rounded">
                                        <input type="checkbox" :value="service.id" v-model="form.services" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <span class="ml-2 text-sm text-gray-700">{{ service.name }}</span>
                                    </label>
                                </div>
                                <InputError class="mt-2" :message="form.errors.services" />
                            </div>

                            <!-- Descripción -->
                            <div>
                                <InputLabel for="description" value="Descripción Default (Opcional)" class="font-bold text-gray-700" />
                                <textarea
                                    id="description"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    v-model="form.description"
                                    rows="3"
                                    placeholder="Ej. Diseño de trípticos para la promoción de Rendimiento Total..."
                                ></textarea>
                                <InputError class="mt-2" :message="form.errors.description" />
                            </div>

                            <!-- Tipo de Cobro & Precio -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6" v-if="$page.props.auth.user.is_admin">
                                <div>
                                    <InputLabel for="billing_type" value="Tipo de Cobro" class="font-bold text-gray-700" />
                                    <select
                                        id="billing_type"
                                        v-model="form.billing_type"
                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm bg-white"
                                        required
                                    >
                                        <option value="unique">Pago Total Único</option>
                                        <option value="monthly">Mensualidad / Iguala</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.billing_type" />
                                </div>

                                <div>
                                    <InputLabel for="price" value="Precio Base ($ MXN)" class="font-bold text-gray-700" />
                                    <div class="relative mt-1">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">$</span>
                                        </div>
                                        <TextInput
                                            id="price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="block w-full pl-7 pr-12 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                            v-model="form.price"
                                            required
                                        />
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.price" />
                                </div>

                                <div>
                                    <InputLabel for="renewal_price" value="Precio de Renovación / Anualidad" class="font-bold text-gray-700" />
                                    <div class="relative mt-1">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">$</span>
                                        </div>
                                        <TextInput
                                            id="renewal_price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="block w-full pl-7 pr-12 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                            v-model="form.renewal_price"
                                            required
                                        />
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.renewal_price" />
                                </div>
                            </div>

                            <!-- Costos Internos -->
                            <div class="p-6 bg-gray-50 border border-gray-200 rounded-xl">
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="font-bold text-gray-800 uppercase tracking-wider text-xs flex items-center">
                                        <span class="bg-red-100 text-red-600 p-1 rounded-md mr-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </span>
                                        Costos Internos (Para medir utilidad)
                                    </h4>
                                    <button 
                                        type="button" 
                                        @click="addCost" 
                                        class="text-xs flex items-center text-red-600 hover:text-red-700 font-bold bg-white border border-red-100 px-3 py-1.5 rounded-lg shadow-sm"
                                    >
                                        <PlusCircleIcon class="h-4 w-4 mr-1" />
                                        Agregar Costo
                                    </button>
                                </div>

                                <div v-if="form.costs.length > 0" class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-gray-400 text-left border-b border-gray-200">
                                                <th class="pb-2 font-semibold">TÍTULO DEL COSTO</th>
                                                <th class="pb-2 font-semibold text-center w-20">CANT.</th>
                                                <th class="pb-2 font-semibold text-right w-32">PRECIO UNIT.</th>
                                                <th class="pb-2 w-10"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr v-for="(cost, index) in form.costs" :key="index" class="group">
                                                <td class="py-3">
                                                    <input 
                                                        type="text" 
                                                        v-model="cost.title" 
                                                        class="w-full border-none focus:ring-0 p-0 text-gray-700 bg-transparent placeholder-gray-300"
                                                        placeholder="Costo por impresión, outsourcing..."
                                                    >
                                                </td>
                                                <td class="py-3 px-2">
                                                    <input 
                                                        type="number" 
                                                        v-model="cost.quantity" 
                                                        class="w-full border-none focus:ring-0 p-0 text-center text-gray-700 bg-transparent"
                                                        min="1"
                                                    >
                                                </td>
                                                <td class="py-3 px-2">
                                                    <div class="flex items-center justify-end">
                                                        <span class="text-gray-400 mr-1">$</span>
                                                        <input 
                                                            type="number" 
                                                            v-model="cost.price" 
                                                            class="w-full border-none focus:ring-0 p-0 text-right text-gray-700 bg-transparent"
                                                            step="0.01"
                                                            min="0"
                                                        >
                                                    </div>
                                                </td>
                                                <td class="py-3 text-right">
                                                    <button @click="removeCost(index)" type="button" class="text-gray-300 hover:text-red-500 transition-colors">
                                                        <TrashIcon class="h-4 w-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr class="font-bold text-gray-800 border-t border-gray-200">
                                                <td class="py-3">TOTAL COSTOS ESTIMADOS:</td>
                                                <td colspan="2" class="py-3 text-right text-red-600">
                                                    ${{ form.costs.reduce((sum, c) => sum + (c.price * c.quantity), 0).toFixed(2) }}
                                                </td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div v-else class="text-center py-6 border-2 border-dashed border-gray-100 rounded-xl">
                                    <p class="text-gray-400 text-sm italic">No hay costos agregados aún.</p>
                                </div>
                            </div>

                            <div class="flex items-center justify-end mt-4 pt-4 border-t border-gray-100">
                                <button
                                    type="submit"
                                    class="btn bg-primary hover:bg-secondary text-white font-bold py-2 px-6 rounded"
                                    :class="{ 'opacity-25': form.processing }"
                                    :disabled="form.processing"
                                >
                                    Guardar Servicio
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
