<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { PlusCircleIcon, TrashIcon, BanknotesIcon, ChevronDownIcon, ChevronUpIcon } from '@heroicons/vue/24/outline';
import Wysiwyg from '@/Components/Wysiwyg.vue';

const props = defineProps({
    services: Array,
    quote: Object,
    clients: Array,
});

const today = new Date().toISOString().split('T')[0];

const form = useForm({
    client_name: props.quote.client_name,
    contact_name: props.quote.contact_name || '',
    email: props.quote.email || '',
    phone: props.quote.phone || '',
    issue_date: props.quote.issue_date,
    valid_until: props.quote.valid_until || '',
    duration: props.quote.duration || '',
    notes: props.quote.notes || '',
    include_payment_terms: props.quote.include_payment_terms === 1 || props.quote.include_payment_terms === true,
    is_multiple_choice: props.quote.is_multiple_choice === 1 || props.quote.is_multiple_choice === true,
    items: props.quote.items ? props.quote.items.map(i => ({
        ...i,
        costs: i.costs ? i.costs.map(c => ({...c})) : []
    })) : [],
});

const expandedItems = ref([]);

const toggleCosts = (index) => {
    if (expandedItems.value.includes(index)) {
        expandedItems.value = expandedItems.value.filter(i => i !== index);
    } else {
        expandedItems.value.push(index);
    }
};

const addItemCost = (itemIndex) => {
    if (!form.items[itemIndex].costs) {
        form.items[itemIndex].costs = [];
    }
    form.items[itemIndex].costs.push({ title: '', quantity: 1, price: 0 });
};

const removeItemCost = (itemIndex, costIndex) => {
    form.items[itemIndex].costs.splice(costIndex, 1);
};

// Reactivity for adding items from Library
const selectedServiceId = ref('');
const selectedClientId = ref('');

const onClientSelect = () => {
    if (!selectedClientId.value) return;
    const client = props.clients.find(c => c.id === parseInt(selectedClientId.value));
    if (client) {
        form.client_name = client.business_name;
        form.contact_name = client.contact_name || '';
        form.email = client.email || '';
        form.phone = client.phone || '';
        selectedClientId.value = ''; // Reset after selection
    }
};

const addCustomService = () => {
    form.items.push({
        concept: '',
        description: '',
        unit_price: 0,
        quantity: 1,
        billing_type: 'unique', // Default to unique
        service_id: null,
        costs: [],
    });
};

const addServiceToQuote = () => {
    if (!selectedServiceId.value) return;
    
    const service = props.services.find(s => s.id === parseInt(selectedServiceId.value));
    if (service) {
        let computedDescription = service.description || '';
        if (service.is_package && service.services && service.services.length > 0) {
            const listServices = "Incluye:\n• " + service.services.map(s => s.name).join('\n• ');
            computedDescription = computedDescription ? computedDescription + "\n\n" + listServices : listServices;
        }

        form.items.push({
            // Let the DB assign an ID later, we just need the fields.
            concept: service.name,
            description: computedDescription,
            unit_price: service.price,
            quantity: 1,
            billing_type: service.billing_type,
            service_id: service.id,
            costs: service.costs ? service.costs.map(c => ({ 
                title: c.title, 
                quantity: c.quantity, 
                price: c.price 
            })) : [],
        });
        selectedServiceId.value = ''; // Reset select
    }
};

const removeQuoteItem = (index) => {
    form.items.splice(index, 1);
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(value);
};

// Computeds for dynamic totals
const uniqueTotal = computed(() => {
    return form.items
        .filter(item => item.billing_type === 'unique')
        .reduce((sum, item) => sum + (parseFloat(item.unit_price) * parseInt(item.quantity)), 0);
});

const monthlyTotal = computed(() => {
    return form.items
        .filter(item => item.billing_type === 'monthly')
        .reduce((sum, item) => sum + (parseFloat(item.unit_price) * parseInt(item.quantity)), 0);
});

const submit = () => {
    // Guard preventer if no items
    if (form.items.length === 0) {
        alert('Por favor, agrega al menos un servicio a la cotización.');
        return;
    }
    form.put(route('quotes.update', props.quote.id));
};
</script>

<template>
    <Head title="Editar Cotización" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Editar Cotización #{{ quote.id }}
                </h2>
                <Link
                    :href="route('quotes.index')"
                    class="btn bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded"
                >
                    Atrás
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="container mx-auto">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- SECTION 1: HEADER -->
                    <div class="card bg-white shadow-sm sm:rounded-lg">
                        <div class="border-b border-gray-200 pb-4 mb-4">
                            <h3 class="text-lg font-bold text-[#264ab3]">Paso 1: Información General</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="md:col-span-1">
                                <div class="flex justify-between items-center">
                                    <InputLabel for="client_search" value="Buscar Cliente Registrado" class="font-bold text-[#264ab3]" />
                                    <span class="text-xs text-gray-400 italic">Opcional</span>
                                </div>
                                <select 
                                    id="client_search"
                                    v-model="selectedClientId" 
                                    @change="onClientSelect"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md text-sm"
                                >
                                    <option value="">-- Seleccionar cliente --</option>
                                    <option v-for="client in clients" :key="client.id" :value="client.id">
                                        {{ client.business_name }} ({{ client.contact_name }})
                                    </option>
                                </select>
                            </div>

                            <div class="md:col-span-2">
                                <!-- Spacer or other info if needed -->
                            </div>

                            <div class="md:col-span-1">
                                <InputLabel for="client_name" value="Empresa / Negocio" class="font-bold text-gray-700" />
                                <TextInput
                                    id="client_name"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                    v-model="form.client_name"
                                    required
                                    placeholder="Ej. Trazamapas"
                                />
                                <InputError class="mt-2" :message="form.errors.client_name" />
                            </div>

                            <div class="md:col-span-1">
                                <InputLabel for="contact_name" value="Nombre del Contacto" class="font-bold text-gray-700" />
                                <TextInput
                                    id="contact_name"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                    v-model="form.contact_name"
                                    placeholder="Opcional. Ej: Juan Pérez"
                                />
                                <InputError class="mt-2" :message="form.errors.contact_name" />
                            </div>

                            <div class="md:col-span-1">
                                <InputLabel for="email" value="Correo Electrónico (Email)" class="font-bold text-gray-700" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                    v-model="form.email"
                                    placeholder="Opcional."
                                />
                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>

                            <div class="md:col-span-1">
                                <InputLabel for="phone" value="Teléfono" class="font-bold text-gray-700" />
                                <TextInput
                                    id="phone"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                    v-model="form.phone"
                                    placeholder="Opcional."
                                />
                                <InputError class="mt-2" :message="form.errors.phone" />
                            </div>
                            
                            <div>
                                <InputLabel for="issue_date" value="Fecha de Emisión" class="font-bold text-gray-700" />
                                <TextInput
                                    id="issue_date"
                                    type="date"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md bg-gray-50"
                                    v-model="form.issue_date"
                                    required
                                    :max="today"
                                />
                                <InputError class="mt-2" :message="form.errors.issue_date" />
                            </div>
                            
                            <div>
                                <InputLabel for="valid_until" value="Válido Hasta" class="font-bold text-gray-700" />
                                <TextInput
                                    id="valid_until"
                                    type="date"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                    v-model="form.valid_until"
                                />
                                <InputError class="mt-2" :message="form.errors.valid_until" />
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: ITEMS BUILDER -->
                    <div class="card bg-white shadow-sm sm:rounded-lg">
                        <div class="border-b border-gray-200 pb-4 mb-4 flex flex-col md:flex-row md:justify-between md:items-center space-y-4 md:space-y-0">
                            <div>
                                <h3 class="text-lg font-bold text-[#264ab3]">Paso 2: Servicios a Cotizar</h3>
                                <div class="mt-2 flex items-center space-x-4">
                                    <span class="text-xs font-semibold uppercase text-gray-400">Modalidad:</span>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" v-model="form.is_multiple_choice" class="sr-only peer">
                                        <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#264ab3]"></div>
                                        <span class="ms-3 text-sm font-medium" :class="form.is_multiple_choice ? 'text-[#264ab3] font-bold' : 'text-gray-500'">
                                            {{ form.is_multiple_choice ? 'Opciones (Elegir una)' : 'Normal (Suma Total)' }}
                                        </span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Dropdown to select existing services -->
                            <div class="flex items-center space-x-2">
                                <button 
                                    type="button" 
                                    @click="addCustomService" 
                                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-md shadow-sm transition inline-flex items-center font-semibold border border-gray-300"
                                >
                                    <PlusCircleIcon class="h-5 w-5 mr-1 text-gray-500" />
                                    <span>Servicio Custom</span>
                                </button>

                                <div class="h-8 w-px bg-gray-200 mx-1"></div>

                                <select 
                                    v-model="selectedServiceId" 
                                    class="border-gray-300 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-md shadow-sm sm:text-sm text-gray-700 w-64"
                                >
                                    <option value="" disabled>-- Catálogo de Servicios --</option>
                                    <option v-for="srv in services" :key="srv.id" :value="srv.id">
                                        {{ srv.name }} ({{ srv.billing_type === 'unique' ? 'Único' : 'Mensual' }})
                                    </option>
                                </select>
                                <button 
                                    type="button" 
                                    @click="addServiceToQuote" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md shadow-sm transition inline-flex items-center font-semibold"
                                    :disabled="!selectedServiceId"
                                    :class="{'opacity-50 cursor-not-allowed': !selectedServiceId}"
                                >
                                    <PlusCircleIcon class="h-5 w-5 mr-1" />
                                    <span>Agregar del Catálogo</span>
                                </button>
                            </div>
                        </div>

                        <!-- Build Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse min-w-max">
                                <thead>
                                    <tr class="bg-gray-50 border-b">
                                        <th class="p-3 uppercase font-medium text-xs text-gray-600 w-1/4">Concepto</th>
                                        <th class="p-3 uppercase font-medium text-xs text-gray-600 w-1/4">Descripción</th>
                                        <th class="p-3 uppercase font-medium text-xs text-gray-600 w-32">Cobro</th>
                                        <th class="p-3 uppercase font-medium text-xs text-gray-600 w-24">Cant.</th>
                                        <th class="p-3 uppercase font-medium text-xs text-gray-600 w-32">Precio Uni.</th>
                                        <th class="p-3 uppercase font-medium text-xs text-gray-600 w-24">Utilidad</th>
                                        <th class="p-3 uppercase font-medium text-xs text-gray-600 text-center w-24">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="(item, index) in form.items" :key="index">
                                        <tr class="border-b group">
                                        <!-- Concept -->
                                        <td class="p-2 align-top">
                                            <TextInput 
                                                v-model="item.concept" 
                                                class="w-full text-sm font-semibold !border-transparent !bg-transparent group-hover:!border-gray-300 focus:!bg-white px-2 py-1" 
                                                required 
                                            />
                                        </td>
                                        <!-- Desc -->
                                        <td class="p-2 align-top">
                                            <textarea 
                                                v-model="item.description" 
                                                rows="2" 
                                                class="w-full text-sm text-gray-600 border-transparent bg-transparent group-hover:border-gray-300 focus:bg-white focus:border-indigo-500 focus:ring-indigo-500 rounded-md px-2 py-1 resize-y" 
                                            ></textarea>
                                        </td>
                                        <!-- Type -->
                                        <td class="p-2 align-top text-sm">
                                            <select 
                                                v-model="item.billing_type" 
                                                class="w-full text-xs border-transparent bg-transparent group-hover:border-gray-300 focus:bg-white focus:ring-[#264ab3] rounded-md px-1 py-1 font-semibold"
                                                :class="item.billing_type === 'unique' ? 'text-purple-700' : 'text-green-700'"
                                            >
                                                <option value="unique">Único</option>
                                                <option value="monthly">Mensual</option>
                                            </select>
                                        </td>
                                        <!-- Quantity -->
                                        <td class="p-2 align-top">
                                            <TextInput 
                                                v-model="item.quantity" 
                                                type="number" 
                                                min="1" 
                                                class="w-full text-center text-sm border-gray-300 px-2 py-1" 
                                                required 
                                            />
                                        </td>
                                        <!-- Price -->
                                        <td class="p-2 align-top">
                                            <div class="relative">
                                                <span class="absolute left-2 top-2 text-gray-500 text-sm">$</span>
                                                <TextInput 
                                                    v-model="item.unit_price" 
                                                    type="number" 
                                                    step="0.01" 
                                                    class="w-full text-right text-sm border-gray-300 pl-5 pr-2 py-1" 
                                                    required 
                                                />
                                            </div>
                                        </td>
                                        <!-- Utilidad -->
                                        <td class="p-2 align-top text-xs font-bold text-center text-center">
                                            <div class="flex flex-col items-center">
                                                <span :class="(item.unit_price - (item.costs ? item.costs.reduce((sum, c) => sum + (c.price * c.quantity), 0) : 0)) >= 0 ? 'text-green-600' : 'text-red-600'">
                                                    {{ formatCurrency((item.unit_price - (item.costs ? item.costs.reduce((sum, c) => sum + (c.price * c.quantity), 0) : 0)) * item.quantity) }}
                                                </span>
                                                <button 
                                                    type="button" 
                                                    @click="toggleCosts(index)"
                                                    class="mt-1 text-[10px] uppercase flex items-center hover:underline"
                                                    :class="expandedItems.includes(index) ? 'text-blue-600' : 'text-gray-400'"
                                                >
                                                    <BanknotesIcon class="h-3 w-3 mr-1" />
                                                    Costos
                                                    <component :is="expandedItems.includes(index) ? ChevronUpIcon : ChevronDownIcon" class="h-3 w-3 ml-0.5" />
                                                </button>
                                            </div>
                                        </td>
                                        <!-- Actions -->
                                        <td class="p-2 align-top text-center pt-3">
                                            <button @click="removeQuoteItem(index)" type="button" class="text-red-400 hover:text-red-600 transition" title="Eliminar Concepto">
                                                <TrashIcon class="h-5 w-5 mx-auto" />
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Internal Costs Row -->
                                    <tr v-if="expandedItems.includes(index)" class="bg-red-50/30 border-b">
                                        <td colspan="7" class="p-4">
                                            <div class="max-w-3xl mx-auto bg-white border border-red-100 rounded-lg p-3 shadow-sm">
                                                <div class="flex justify-between items-center mb-2">
                                                    <span class="text-[10px] font-bold text-red-700 uppercase tracking-wider">Desglose de Costos Internos - {{ item.concept || 'Servicio sin nombre' }}</span>
                                                    <button 
                                                        type="button" 
                                                        @click="addItemCost(index)" 
                                                        class="text-[10px] bg-red-50 text-red-600 px-2 py-1 rounded border border-red-100 font-bold hover:bg-red-100 flex items-center"
                                                    >
                                                        <PlusCircleIcon class="h-3 w-3 mr-1" />
                                                        Añadir Concepto de Costo
                                                    </button>
                                                </div>

                                                <div v-if="item.costs && item.costs.length > 0" class="space-y-2">
                                                    <div v-for="(cost, cIndex) in item.costs" :key="cIndex" class="grid grid-cols-12 gap-2 items-center">
                                                        <div class="col-span-6">
                                                            <input 
                                                                type="text" 
                                                                v-model="cost.title" 
                                                                class="w-full text-[11px] border-gray-200 rounded p-1" 
                                                                placeholder="Ej. Impresiones, outsourcing..."
                                                            >
                                                        </div>
                                                        <div class="col-span-2">
                                                            <input 
                                                                type="number" 
                                                                v-model="cost.quantity" 
                                                                class="w-full text-[11px] border-gray-200 rounded p-1 text-center" 
                                                                placeholder="Cant"
                                                            >
                                                        </div>
                                                        <div class="col-span-3 flex items-center">
                                                            <span class="text-[10px] text-gray-400 mr-1">$</span>
                                                            <input 
                                                                type="number" 
                                                                v-model="cost.price" 
                                                                class="w-full text-[11px] border-gray-200 rounded p-1 text-right" 
                                                                placeholder="Precio"
                                                            >
                                                        </div>
                                                        <div class="col-span-1 text-right">
                                                            <button @click="removeItemCost(index, cIndex)" type="button" class="text-gray-300 hover:text-red-500">
                                                                <TrashIcon class="h-3 w-3" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="pt-2 border-t border-red-50 flex justify-between items-center text-[11px]">
                                                        <span class="font-bold text-gray-600">TOTAL COSTOS:</span>
                                                        <span class="font-bold text-red-600">{{ formatCurrency(item.costs.reduce((sum, c) => sum + (c.price * c.quantity), 0)) }}</span>
                                                    </div>
                                                </div>
                                                <div v-else class="text-center py-2 text-[10px] text-gray-400 italic">
                                                    No hay costos registrados para este ítem. Se considera utilidad 100%.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    </template>
                                    <tr v-if="form.items.length === 0">
                                        <td colspan="7" class="p-8 text-center text-gray-400 italic">
                                            Selecciona un servicio del menú arriba y añádelo a la cotización...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- SECTION 3: NOTES AND TOTALS -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Notes -->
                        <div class="card bg-white shadow-sm sm:rounded-lg">
                            <div class="mb-4">
                                <InputLabel for="duration" value="Duración / Compromiso" class="font-bold text-gray-700" />
                                <TextInput
                                    id="duration"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                    v-model="form.duration"
                                    placeholder="Ej. Compromiso a 6 meses"
                                />
                                <InputError class="mt-2" :message="form.errors.duration" />
                            </div>
                            <InputLabel for="notes" value="Notas Generales" class="font-bold text-gray-700 mb-2 block" />
                            <Wysiwyg
                                id="notes"
                                v-model="form.notes"
                                placeholder="Escribe notas adicionales, exclusiones o detalles del pago..."
                            />
                            <InputError class="mt-2" :message="form.errors.notes" />
                            
                            <!-- Payment Terms Checkbox -->
                            <div class="mt-4 flex items-center">
                                <input 
                                    type="checkbox" 
                                    id="include_payment_terms" 
                                    v-model="form.include_payment_terms"
                                    class="rounded border-gray-300 text-[#264ab3] shadow-sm focus:ring-[#264ab3]"
                                />
                                <label for="include_payment_terms" class="ml-2 block text-sm text-gray-700">
                                    Añadir nota automática: <strong>50% Anticipo y 50% al entregar</strong> (sólo para <em>Pagos Únicos</em>)
                                </label>
                            </div>
                        </div>
                        
                        <!-- Totals Widget -->
                        <div class="card bg-gray-50 shadow-sm sm:rounded-lg border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 border-b border-gray-200 pb-3 mb-3">Resumen Inteligente</h3>
                            
                            <div class="space-y-4">
                                <div class="flex justify-between items-center text-gray-600 bg-white p-3 rounded shadow-sm border border-purple-100">
                                    <span class="font-medium text-purple-800">Inversión Inicial / Pago Único:</span>
                                    <span v-if="!form.is_multiple_choice" class="text-xl font-bold text-gray-900">{{ formatCurrency(uniqueTotal) }}</span>
                                    <span v-else class="text-sm font-semibold text-purple-600 uppercase italic">Ver opciones en lista</span>
                                </div>
                                
                                <div class="flex justify-between items-center text-gray-600 bg-white p-3 rounded shadow-sm border border-green-100">
                                    <span class="font-medium text-green-800">Inversión Mensual / Iguala:</span>
                                    <span v-if="!form.is_multiple_choice" class="text-xl font-bold text-gray-900">{{ formatCurrency(monthlyTotal) }}</span>
                                    <span v-else class="text-sm font-semibold text-green-600 uppercase italic">Ver opciones en lista</span>
                                </div>

                                <div v-if="form.is_multiple_choice" class="p-3 bg-blue-50 border border-blue-100 rounded text-xs text-blue-700">
                                    <p><strong>Nota:</strong> En esta modalidad, los precios no se suman. El cliente podrá elegir una de las opciones presentadas.</p>
                                </div>
                            </div>

                            <div class="mt-6 pt-4 border-t border-gray-200 text-right flex justify-end space-x-3">
                                <a
                                    :href="route('quotes.show', quote.id)"
                                    target="_blank"
                                    class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-6 rounded shadow-lg transition-colors inline-flex items-center"
                                >
                                    Ver PDF
                                </a>
                                <button
                                    type="submit"
                                    class="btn bg-[#264ab3] hover:bg-[#193074] text-white font-bold py-3 px-8 rounded shadow-lg"
                                    :class="{ 'opacity-50': form.processing }"
                                    :disabled="form.processing || form.items.length === 0"
                                >
                                    Actualizar Cotización
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
