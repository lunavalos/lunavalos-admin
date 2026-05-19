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
    addon: Object,
    categories: Object,
    cycles: Object,
});

const form = useForm({
    name: props.addon.name,
    category: props.addon.category,
    description: props.addon.description ?? '',
    price: props.addon.price,
    billing_cycle: props.addon.billing_cycle,
    billing_cycle_months: props.addon.billing_cycle_months,
    is_active: !!props.addon.is_active,
    costs: props.addon.costs ? props.addon.costs.map(c => ({ title: c.title, quantity: c.quantity, price: c.price })) : [],
});

const addCost = () => form.costs.push({ title: '', quantity: 1, price: 0 });
const removeCost = (i) => form.costs.splice(i, 1);

const { fmt: _fmt } = useMoney();
const fmt = (n, c) => _fmt(n, c);
const costsTotal = computed(() => form.costs.reduce((a, c) => a + Number(c.price || 0) * Number(c.quantity || 1), 0));
const margin = computed(() => Number(form.price || 0) - costsTotal.value);
const marginPct = computed(() => {
    const p = Number(form.price || 0);
    return p > 0 ? (margin.value / p) * 100 : 0;
});

const submit = () => form.put(route('service-addons.update', props.addon.id));
</script>

<template>
    <Head title="Editar Addon" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">Editar Addon: {{ addon.name }}</h2>
                <Link :href="route('service-addons.index')" class="btn bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">Volver</Link>
            </div>
        </template>

        <div class="py-12">
            <div class="container mx-auto max-w-2xl">
                <div class="card bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg p-6 border border-gray-100 dark:border-zinc-800">
                    <form @submit.prevent="submit" class="space-y-6">
                        <div>
                            <InputLabel for="name" value="Nombre" class="font-bold" />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="category" value="Categoría" class="font-bold" />
                                <select id="category" v-model="form.category" class="mt-1 block w-full border-gray-300 dark:border-zinc-800 rounded-md bg-white dark:bg-zinc-950" required>
                                    <option value="">— Selecciona —</option>
                                    <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.category" />
                            </div>
                            <div>
                                <InputLabel for="price" value="Precio (MXN)" class="font-bold" />
                                <TextInput id="price" v-model="form.price" type="number" step="0.01" min="0" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="form.errors.price" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="billing_cycle" value="Modalidad de pago" class="font-bold" />
                                <select id="billing_cycle" v-model="form.billing_cycle" class="mt-1 block w-full border-gray-300 dark:border-zinc-800 rounded-md bg-white dark:bg-zinc-950">
                                    <option v-for="(label, key) in cycles" :key="key" :value="key">{{ label }}</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.billing_cycle" />
                            </div>
                            <div v-if="form.billing_cycle === 'custom_months'">
                                <InputLabel for="billing_cycle_months" value="Cada cuántos meses" class="font-bold" />
                                <TextInput id="billing_cycle_months" v-model="form.billing_cycle_months" type="number" min="1" max="60" class="mt-1 block w-full" />
                                <InputError class="mt-2" :message="form.errors.billing_cycle_months" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="description" value="Descripción" class="font-bold" />
                            <textarea id="description" v-model="form.description" rows="3" class="mt-1 block w-full border-gray-300 dark:border-zinc-800 rounded-md dark:bg-zinc-950" />
                        </div>

                        <label class="inline-flex items-center">
                            <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300 text-indigo-600">
                            <span class="ml-2 text-sm font-bold text-gray-700 dark:text-gray-300">Activo</span>
                        </label>

                        <!-- Costos Internos del Addon (para medir utilidad real) -->
                        <div class="p-6 bg-gray-50 dark:bg-zinc-950 border border-gray-200 dark:border-zinc-800 rounded-xl">
                            <div class="flex justify-between items-center mb-4">
                                <h4 class="font-bold text-gray-800 dark:text-gray-100 uppercase tracking-wider text-xs flex items-center gap-2">
                                    <span class="bg-red-100 dark:bg-rose-900/40 text-red-600 dark:text-rose-400 p-1 rounded-md">$</span>
                                    Costos internos del addon
                                </h4>
                                <button type="button" @click="addCost" class="text-xs flex items-center text-red-600 dark:text-rose-400 hover:text-red-700 font-bold bg-white dark:bg-zinc-900 border border-red-100 dark:border-rose-900/50 px-3 py-1.5 rounded-lg shadow-sm">
                                    <PlusCircleIcon class="h-4 w-4 mr-1" /> Agregar costo
                                </button>
                            </div>

                            <div v-if="form.costs.length > 0" class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-gray-400 dark:text-zinc-500 text-left border-b border-gray-200 dark:border-zinc-800">
                                            <th class="pb-2 font-semibold uppercase text-[10px]">Concepto</th>
                                            <th class="pb-2 font-semibold text-center w-20 uppercase text-[10px]">Cant.</th>
                                            <th class="pb-2 font-semibold text-right w-32 uppercase text-[10px]">Precio unit.</th>
                                            <th class="pb-2 w-10"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                        <tr v-for="(cost, index) in form.costs" :key="index">
                                            <td class="py-2">
                                                <input type="text" v-model="cost.title" class="w-full border-none focus:ring-0 p-0 text-gray-700 dark:text-gray-200 bg-transparent" placeholder="Ej. Hosting anual, dominio, licencia..." />
                                            </td>
                                            <td class="py-2 px-2">
                                                <input type="number" v-model="cost.quantity" class="w-full border-none focus:ring-0 p-0 text-center text-gray-700 dark:text-gray-200 bg-transparent" min="1" />
                                            </td>
                                            <td class="py-2 px-2">
                                                <div class="flex items-center justify-end">
                                                    <span class="text-gray-400 mr-1">$</span>
                                                    <input type="number" v-model="cost.price" class="w-full border-none focus:ring-0 p-0 text-right text-gray-700 dark:text-gray-200 bg-transparent" step="0.01" min="0" />
                                                </div>
                                            </td>
                                            <td class="py-2 text-right">
                                                <button @click="removeCost(index)" type="button" class="text-gray-300 hover:text-red-500">
                                                    <TrashIcon class="h-4 w-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                    <div class="p-2 rounded bg-white dark:bg-zinc-900"><div class="text-[10px] uppercase text-gray-400">Precio addon</div><div class="font-bold text-primary">{{ fmt(form.price) }}</div></div>
                                    <div class="p-2 rounded bg-white dark:bg-zinc-900"><div class="text-[10px] uppercase text-gray-400">Costo total</div><div class="font-bold text-rose-600">{{ fmt(costsTotal) }}</div></div>
                                    <div class="p-2 rounded bg-white dark:bg-zinc-900"><div class="text-[10px] uppercase text-gray-400">Utilidad</div><div class="font-bold" :class="margin >= 0 ? 'text-emerald-600' : 'text-red-600'">{{ fmt(margin) }}</div></div>
                                    <div class="p-2 rounded bg-white dark:bg-zinc-900"><div class="text-[10px] uppercase text-gray-400">Margen %</div><div class="font-bold" :class="marginPct >= 0 ? 'text-emerald-600' : 'text-red-600'">{{ marginPct.toFixed(1) }}%</div></div>
                                </div>
                            </div>
                            <div v-else class="text-center py-4 border-2 border-dashed border-gray-100 dark:border-zinc-900 rounded">
                                <p class="text-gray-400 dark:text-zinc-500 text-sm italic">Sin costos registrados. Agrégalos para medir la utilidad real cuando este addon se cotice.</p>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4 border-t border-gray-100 dark:border-zinc-800/50">
                            <button type="submit" :disabled="form.processing" class="btn bg-primary hover:bg-secondary text-white font-bold py-2 px-6 rounded" :class="{ 'opacity-25': form.processing }">
                                Guardar cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
