<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { PencilSquareIcon, TrashIcon, PuzzlePieceIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    addons: Array,
    categories: Object,
    cycles: Object,
});

const form = useForm({});
const page = usePage();
const flashMessage = computed(() => page.props.flash?.message);

const formatCurrency = (value) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value);

const cycleLabel = (addon) => {
    if (addon.billing_cycle === 'custom_months' && addon.billing_cycle_months) {
        return `Cada ${addon.billing_cycle_months} meses`;
    }
    return props.cycles[addon.billing_cycle] ?? addon.billing_cycle;
};

const deleteAddon = (id) => {
    if (confirm('¿Eliminar este servicio adicional?')) {
        form.delete(route('service-addons.destroy', id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Servicios Adicionales" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100 flex items-center">
                    <PuzzlePieceIcon class="h-6 w-6 mr-2 text-[#264ab3] dark:text-blue-400" />
                    Servicios Adicionales
                </h2>
                <Link
                    v-if="$page.props.auth.user.is_admin || $page.props.auth.user.permissions.includes('Crear Addons')"
                    :href="route('service-addons.create')"
                    class="btn bg-primary hover:bg-secondary text-white font-bold py-2 px-4 rounded"
                >
                    Añadir Addon
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="container mx-auto">
                <!-- Tabs -->
                <div class="flex border-b border-gray-200 dark:border-zinc-800 mb-6">
                    <Link :href="route('services.index')" class="px-4 py-2 text-sm font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400">Principales</Link>
                    <Link :href="route('service-addons.index')" class="px-4 py-2 text-sm font-semibold border-b-2 border-primary text-primary">Addons</Link>
                </div>

                <div v-if="flashMessage" class="mb-4 bg-green-100 dark:bg-emerald-900/40 border border-green-400 dark:border-emerald-800 text-green-700 dark:text-emerald-300 px-4 py-3 rounded">
                    {{ flashMessage }}
                </div>

                <div class="card overflow-hidden bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg border border-gray-100 dark:border-zinc-800">
                    <div class="p-6 overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-100 dark:bg-zinc-950 border-b border-gray-200 dark:border-zinc-800">
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600 dark:text-gray-400">Nombre</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600 dark:text-gray-400">Categoría</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600 dark:text-gray-400">Ciclo</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600 dark:text-gray-400 text-right">Precio</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600 dark:text-gray-400 text-center">Activo</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600 dark:text-gray-400 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="addon in addons" :key="addon.id" class="border-b border-gray-100 dark:border-zinc-800/50 hover:bg-gray-50 dark:hover:bg-zinc-800/30 transition">
                                    <td class="p-3 font-semibold text-primary dark:text-blue-400">{{ addon.name }}</td>
                                    <td class="p-3">
                                        <span class="bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 text-xs px-2 py-1 rounded">
                                            {{ categories[addon.category] ?? addon.category }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-sm text-gray-600 dark:text-gray-400">{{ cycleLabel(addon) }}</td>
                                    <td class="p-3 text-right font-medium">{{ formatCurrency(addon.price) }}</td>
                                    <td class="p-3 text-center">
                                        <span :class="addon.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'" class="text-xs px-2 py-1 rounded">
                                            {{ addon.is_active ? 'Sí' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-right space-x-1">
                                        <Link :href="route('service-addons.edit', addon.id)" class="text-gray-600 hover:text-blue-600 p-2 rounded-full inline-flex">
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </Link>
                                        <button @click="deleteAddon(addon.id)" class="text-red-500 hover:text-red-700 p-2 rounded-full inline-flex">
                                            <TrashIcon class="w-5 h-5" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="addons.length === 0">
                                    <td colspan="6" class="p-6 text-center text-gray-500 italic">Aún no hay servicios adicionales.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
