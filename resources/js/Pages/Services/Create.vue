<script setup>
import { useMoney } from '@/Composables/useMoney';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    availableServices: {
        type: Array,
        default: () => []
    },
    addonCategories: {
        type: Object,
        default: () => ({}),
    },
    maxPaymentMonths: {
        type: Number,
        default: 24,
    },
});

const { fmt: _fmt, options: currencyOptions, defaultCurrency } = useMoney();
const fmt = (n, c) => _fmt(n, c);

const form = useForm({
    name: '',
    description: '',
    price: 0,
    renewal_price: 0,
    currency: defaultCurrency.value,
    billing_type: 'unique',
    is_package: false,
    required_addon_categories: [],
    payment_plan_months: 1,
    services: [],
    costs: [],
    features: [],
});

// El significado del Precio Base cambia con el Tipo de Cobro: en `annual` es
// el gasto fuerte inicial (desarrollo), no lo que el cliente paga cada año.
const priceHint = computed(() => ({
    unique:  'Pago único total del servicio.',
    monthly: 'Lo que el cliente paga cada mes.',
    annual:  'Pago inicial único por el desarrollo/puesta en marcha. Se puede diferir con el plan de pago de abajo. No se vuelve a cobrar.',
}[form.billing_type] ?? ''));

const renewalHint = computed(() => ({
    monthly: 'Anualidad que se cobra UNA vez al año, aparte de la mensualidad (dominio, hosting, licencias), a partir del año 2. Aparece en la cotización. Déjalo en 0 si no aplica.',
    annual:  'Anualidad: lo que el cliente paga cada año a partir del año 2 (dominio, hosting, soporte, mantenimiento).',
}[form.billing_type] ?? ''));

const addCost = () => form.costs.push({ title: '', quantity: 1, price: 0 });
const removeCost = (index) => form.costs.splice(index, 1);
const addFeature = () => form.features.push({ label: '', sort_order: form.features.length });
const removeFeature = (index) => form.features.splice(index, 1);

const bundleDetail = computed(() =>
    props.availableServices.filter(s => form.services.includes(s.id))
);
const bundlePriceTotal = computed(() =>
    bundleDetail.value.reduce((acc, s) => acc + Number(s.price || 0), 0)
);
const bundleCostTotal = computed(() =>
    bundleDetail.value.reduce((acc, s) => {
        const sub = (s.costs || []).reduce((a, c) => a + Number(c.price || 0) * Number(c.quantity || 1), 0);
        return acc + sub;
    }, 0)
);
const ownCostTotal = computed(() =>
    form.costs.reduce((a, c) => a + Number(c.price || 0) * Number(c.quantity || 1), 0)
);
const totalInternalCost = computed(() => bundleCostTotal.value + ownCostTotal.value);
const packageMargin = computed(() => Number(form.price || 0) - totalInternalCost.value);
const packageMarginPct = computed(() => {
    const p = Number(form.price || 0);
    if (p <= 0) return 0;
    return ((p - totalInternalCost.value) / p) * 100;
});

const submit = () => {
    form.post(route('services.store'));
};
</script>

<template>
    <Head title="Añadir Servicio" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">
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
                <div class="card overflow-hidden bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg max-w-2xl mx-auto border border-gray-100 dark:border-zinc-800">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit" class="space-y-6">
                            
                            <!-- Nombre -->
                            <div>
                                <InputLabel for="name" value="Nombre del Servicio" class="font-bold text-gray-700 dark:text-gray-300" />
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
                                    <input type="checkbox" v-model="form.is_package" class="rounded border-gray-300 dark:border-zinc-800 dark:bg-zinc-950 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300 font-bold">Es un Paquete (agrupa múltiples servicios)</span>
                                </label>
                            </div>

                            <!-- Servicios Incluidos (Solo si es paquete) -->
                            <div v-if="form.is_package" class="p-4 bg-gray-50 dark:bg-zinc-950 border border-gray-200 dark:border-zinc-800 rounded-md space-y-4">
                                <InputLabel value="Servicios Incluidos en el Paquete" class="font-bold text-gray-700 dark:text-gray-300 mb-2" />
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    <label v-for="service in availableServices" :key="service.id" class="flex items-start gap-2 p-2 hover:bg-white dark:hover:bg-zinc-900 rounded transition-colors">
                                        <input type="checkbox" :value="service.id" v-model="form.services" class="mt-1 rounded border-gray-300 dark:border-zinc-800 dark:bg-zinc-950 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200 truncate">{{ service.name }}</span>
                                                <span class="text-xs font-semibold text-primary whitespace-nowrap">{{ fmt(service.price) }}</span>
                                            </div>
                                            <p v-if="service.description" class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5 line-clamp-2">{{ service.description }}</p>
                                        </div>
                                    </label>
                                </div>
                                <InputError class="mt-2" :message="form.errors.services" />

                                <!-- Resumen agregado del bundle -->
                                <div v-if="bundleDetail.length > 0" class="mt-2 p-3 rounded-md bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800">
                                    <h5 class="text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-2">Resumen del paquete ({{ bundleDetail.length }} servicios)</h5>
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-[10px] uppercase text-gray-400 dark:text-zinc-500 text-left border-b border-gray-200 dark:border-zinc-800">
                                                <th class="pb-1">Servicio</th>
                                                <th class="pb-1 text-right">Precio</th>
                                                <th class="pb-1 text-right">Costo interno</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                            <tr v-for="sub in bundleDetail" :key="sub.id">
                                                <td class="py-1.5 text-gray-700 dark:text-gray-200">{{ sub.name }}</td>
                                                <td class="py-1.5 text-right text-gray-700 dark:text-gray-200">{{ fmt(sub.price) }}</td>
                                                <td class="py-1.5 text-right text-rose-600 dark:text-rose-400">
                                                    {{ fmt((sub.costs || []).reduce((a, c) => a + Number(c.price || 0) * Number(c.quantity || 1), 0)) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot class="font-bold text-gray-800 dark:text-gray-100 border-t border-gray-200 dark:border-zinc-800">
                                            <tr>
                                                <td class="pt-2">Suma sub-servicios</td>
                                                <td class="pt-2 text-right">{{ fmt(bundlePriceTotal) }}</td>
                                                <td class="pt-2 text-right text-rose-600 dark:text-rose-400">{{ fmt(bundleCostTotal) }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                        <div class="p-2 rounded bg-gray-50 dark:bg-zinc-950">
                                            <div class="text-[10px] uppercase text-gray-400">Precio paquete</div>
                                            <div class="font-bold text-primary">{{ fmt(form.price) }}</div>
                                        </div>
                                        <div class="p-2 rounded bg-gray-50 dark:bg-zinc-950">
                                            <div class="text-[10px] uppercase text-gray-400">Costo total interno</div>
                                            <div class="font-bold text-rose-600">{{ fmt(totalInternalCost) }}</div>
                                        </div>
                                        <div class="p-2 rounded bg-gray-50 dark:bg-zinc-950">
                                            <div class="text-[10px] uppercase text-gray-400">Utilidad</div>
                                            <div class="font-bold" :class="packageMargin >= 0 ? 'text-emerald-600' : 'text-red-600'">{{ fmt(packageMargin) }}</div>
                                        </div>
                                        <div class="p-2 rounded bg-gray-50 dark:bg-zinc-950">
                                            <div class="text-[10px] uppercase text-gray-400">Margen %</div>
                                            <div class="font-bold" :class="packageMarginPct >= 0 ? 'text-emerald-600' : 'text-red-600'">{{ packageMarginPct.toFixed(1) }}%</div>
                                        </div>
                                    </div>
                                    <p v-if="form.price > 0 && bundlePriceTotal > Number(form.price)" class="mt-2 text-[11px] text-amber-600">
                                        El precio del paquete ({{ fmt(form.price) }}) es menor que la suma individual ({{ fmt(bundlePriceTotal) }}). Implica descuento de {{ fmt(bundlePriceTotal - Number(form.price)) }}.
                                    </p>
                                </div>
                            </div>

                            <!-- Descripción -->
                            <div>
                                <InputLabel for="description" value="Descripción Default (Opcional)" class="font-bold text-gray-700 dark:text-gray-300" />
                                <textarea
                                    id="description"
                                    class="mt-1 block w-full border-gray-300 dark:border-zinc-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:bg-zinc-950 dark:text-gray-100 placeholder-gray-400 dark:placeholder-zinc-500 transition-colors"
                                    v-model="form.description"
                                    rows="3"
                                    placeholder="Ej. Diseño de trípticos para la promoción de Rendimiento Total..."
                                ></textarea>
                                <InputError class="mt-2" :message="form.errors.description" />
                            </div>

                            <!-- Tipo de Cobro & Precio -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6" v-if="$page.props.auth.user.is_admin">
                                <div>
                                    <InputLabel for="billing_type" value="Tipo de Cobro" class="font-bold text-gray-700 dark:text-gray-300" />
                                    <select
                                        id="billing_type"
                                        v-model="form.billing_type"
                                        class="mt-1 block w-full border-gray-300 dark:border-zinc-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm bg-white dark:bg-zinc-950 text-gray-700 dark:text-gray-200"
                                        required
                                    >
                                        <option value="unique">Pago Total Único</option>
                                        <option value="monthly">Mensualidad / Iguala</option>
                                        <option value="annual">Anual</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.billing_type" />
                                </div>

                                <div>
                                    <InputLabel for="currency" value="Moneda" class="font-bold text-gray-700 dark:text-gray-300" />
                                    <select
                                        id="currency"
                                        v-model="form.currency"
                                        class="mt-1 block w-full border-gray-300 dark:border-zinc-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm bg-white dark:bg-zinc-950 text-gray-700 dark:text-gray-200"
                                        required
                                    >
                                        <option v-for="opt in currencyOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.currency" />
                                </div>

                                <div>
                                    <InputLabel for="price" :value="`Precio Base (${form.currency})`" class="font-bold text-gray-700 dark:text-gray-300" />
                                    <div class="relative mt-1">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 dark:text-zinc-400 sm:text-sm">$</span>
                                        </div>
                                        <TextInput
                                            id="price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="block w-full pl-7 pr-12 border-gray-300 dark:border-zinc-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:bg-zinc-950 dark:text-gray-100"
                                            v-model="form.price"
                                            required
                                        />
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">{{ priceHint }}</p>
                                    <InputError class="mt-2" :message="form.errors.price" />
                                </div>

                                <div v-if="form.billing_type !== 'unique'">
                                    <InputLabel for="renewal_price" value="Precio de Renovación / Anualidad" class="font-bold text-gray-700 dark:text-gray-300" />
                                    <div class="relative mt-1">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">$</span>
                                        </div>
                                        <TextInput
                                            id="renewal_price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="block w-full pl-7 pr-12 border-gray-300 dark:border-zinc-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:bg-zinc-950 dark:text-gray-100"
                                            v-model="form.renewal_price"
                                            required
                                        />
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">{{ renewalHint }}</p>
                                    <InputError class="mt-2" :message="form.errors.renewal_price" />
                                </div>
                            </div>

                            <!-- Addons obligatorios + plan de pago (aplica a cualquier servicio, no solo paquetes) -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-4 bg-gray-50 dark:bg-zinc-950 border border-gray-200 dark:border-zinc-800 rounded-md">
                                <div>
                                    <InputLabel value="Addons obligatorios al cotizar" class="font-bold text-gray-700 dark:text-gray-300" />
                                    <p class="text-xs text-gray-500 mt-1 mb-2">Marca las categorías de las que el cliente <strong>debe</strong> elegir al menos un addon al usar este servicio en una cotización. Si no marcas ninguna, los addons quedan opcionales.</p>
                                    <div class="grid grid-cols-1 gap-1">
                                        <label v-for="(label, key) in addonCategories" :key="key" class="inline-flex items-center gap-2 p-1.5 rounded hover:bg-white dark:hover:bg-zinc-900 transition-colors">
                                            <input type="checkbox" :value="key" v-model="form.required_addon_categories" class="rounded border-gray-300 dark:border-zinc-800 dark:bg-zinc-950 text-indigo-600 focus:ring-indigo-500">
                                            <span class="text-sm text-gray-700 dark:text-gray-200">{{ label }}</span>
                                        </label>
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.required_addon_categories" />
                                </div>
                                <div>
                                    <InputLabel for="payment_plan_months" value="Plan de pago sugerido (meses)" class="font-bold text-gray-700 dark:text-gray-300" />
                                    <p class="text-xs text-gray-500 mt-1 mb-2">Sugerencia por default al cotizar este servicio. El usuario puede ajustarlo en el wizard. <strong>1 = pago único.</strong></p>
                                    <TextInput
                                        id="payment_plan_months"
                                        type="number"
                                        :min="1"
                                        :max="maxPaymentMonths"
                                        class="mt-1 block w-full"
                                        v-model="form.payment_plan_months"
                                    />
                                    <InputError class="mt-2" :message="form.errors.payment_plan_months" />
                                </div>
                            </div>

                            <!-- ¿Qué incluye? -->
                            <div class="p-6 bg-gray-50 dark:bg-zinc-950 border border-gray-200 dark:border-zinc-800 rounded-xl">
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="font-bold text-gray-800 dark:text-gray-100 uppercase tracking-wider text-xs">¿Qué incluye?</h4>
                                    <button type="button" @click="addFeature" class="text-xs flex items-center text-emerald-600 hover:text-emerald-700 font-bold bg-white dark:bg-zinc-900 border border-emerald-100 dark:border-emerald-900/50 px-3 py-1.5 rounded-lg shadow-sm">
                                        <PlusCircleIcon class="h-4 w-4 mr-1" />
                                        Agregar característica
                                    </button>
                                </div>
                                <div v-if="form.features.length > 0" class="space-y-2">
                                    <div v-for="(feature, index) in form.features" :key="index" class="flex items-center gap-2 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 rounded-md px-3 py-2">
                                        <span class="text-emerald-500">✓</span>
                                        <input
                                            type="text"
                                            v-model="feature.label"
                                            class="flex-1 border-none focus:ring-0 p-0 text-sm text-gray-700 dark:text-gray-200 bg-transparent"
                                            placeholder="Ej. 5 horas de servicio incluidas"
                                        />
                                        <button @click="removeFeature(index)" type="button" class="text-gray-300 hover:text-red-500">
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                                <div v-else class="text-center py-4 border-2 border-dashed border-gray-100 dark:border-zinc-900 rounded-xl">
                                    <p class="text-gray-400 dark:text-zinc-500 text-sm italic">Sin características aún. Estas se muestran en la card del paquete dentro del wizard.</p>
                                </div>
                            </div>

                            <!-- Costos Internos -->
                            <div class="p-6 bg-gray-50 dark:bg-zinc-950 border border-gray-200 dark:border-zinc-800 rounded-xl">
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="font-bold text-gray-800 dark:text-gray-100 uppercase tracking-wider text-xs flex items-center">
                                        <span class="bg-red-100 dark:bg-rose-900/40 text-red-600 dark:text-rose-400 p-1 rounded-md mr-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </span>
                                        Costos Internos (Para medir utilidad)
                                    </h4>
                                    <button 
                                        type="button" 
                                        @click="addCost" 
                                        class="text-xs flex items-center text-red-600 dark:text-rose-400 hover:text-red-700 dark:hover:text-rose-300 font-bold bg-white dark:bg-zinc-900 border border-red-100 dark:border-rose-900/50 px-3 py-1.5 rounded-lg shadow-sm transition-all"
                                    >
                                        <PlusCircleIcon class="h-4 w-4 mr-1" />
                                        Agregar Costo
                                    </button>
                                </div>

                                <div v-if="form.costs.length > 0" class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-gray-400 dark:text-zinc-500 text-left border-b border-gray-200 dark:border-zinc-800">
                                                <th class="pb-2 font-semibold uppercase tracking-tighter text-[10px]">TÍTULO DEL COSTO</th>
                                                <th class="pb-2 font-semibold text-center w-20 uppercase tracking-tighter text-[10px]">CANT.</th>
                                                <th class="pb-2 font-semibold text-right w-32 uppercase tracking-tighter text-[10px]">PRECIO UNIT.</th>
                                                <th class="pb-2 w-10"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr v-for="(cost, index) in form.costs" :key="index" class="group">
                                                <td class="py-3">
                                                    <input 
                                                        type="text" 
                                                        v-model="cost.title" 
                                                        class="w-full border-none focus:ring-0 p-0 text-gray-700 dark:text-gray-200 bg-transparent placeholder-gray-300 dark:placeholder-zinc-600"
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
                                                        <span class="text-gray-400 dark:text-zinc-500 mr-1">$</span>
                                                        <input 
                                                            type="number" 
                                                            v-model="cost.price" 
                                                            class="w-full border-none focus:ring-0 p-0 text-right text-gray-700 dark:text-gray-200 bg-transparent"
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
                                            <tr class="font-bold text-gray-800 dark:text-gray-100 border-t border-gray-200 dark:border-zinc-800">
                                                <td class="py-3">TOTAL COSTOS ESTIMADOS:</td>
                                                <td colspan="2" class="py-3 text-right text-red-600 dark:text-rose-400">
                                                    ${{ form.costs.reduce((sum, c) => sum + (c.price * c.quantity), 0).toFixed(2) }}
                                                </td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div v-else class="text-center py-6 border-2 border-dashed border-gray-100 dark:border-zinc-900 rounded-xl">
                                    <p class="text-gray-400 dark:text-zinc-500 text-sm italic">No hay costos agregados aún.</p>
                                </div>
                            </div>

                            <div class="flex items-center justify-end mt-4 pt-4 border-t border-gray-100 dark:border-zinc-800/50">
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
