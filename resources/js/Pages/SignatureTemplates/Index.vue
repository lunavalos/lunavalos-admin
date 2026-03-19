<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { PlusIcon, PencilIcon, TrashIcon, SwatchIcon } from '@heroicons/vue/24/outline';

defineProps({
    templates: Array
});

const deleteTemplate = (id) => {
    if (confirm('¿Estás seguro de eliminar esta plantilla?')) {
        router.delete(route('signature-templates.destroy', id));
    }
};
</script>

<template>
    <Head title="Plantillas de Firmas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 flex items-center gap-2">
                    <SwatchIcon class="h-6 w-6 text-gray-500" />
                    Plantillas de Firmas
                </h2>
                <Link :href="route('signature-templates.create')" class="bg-[#264ab3] text-white px-4 py-2 rounded-md font-bold text-sm flex items-center">
                    <PlusIcon class="h-4 w-4 mr-1 text-white" />
                    Nueva Plantilla
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                    <div class="p-6 text-gray-900">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nombre</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Slug</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Estado</th>
                                    <th class="px-6 py-3 bg-gray-50"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="template in templates" :key="template.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ template.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ template.slug }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="template.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-2 py-1 text-xs rounded-full font-bold">
                                            {{ template.is_active ? 'Activa' : 'Inactiva' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                                        <Link :href="route('signature-templates.edit', template.id)" class="text-indigo-600 hover:text-indigo-900 inline-block">
                                            <PencilIcon class="h-5 w-5" />
                                        </Link>
                                        <button @click="deleteTemplate(template.id)" class="text-red-600 hover:text-red-900 inline-block">
                                            <TrashIcon class="h-5 w-5" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="templates.length === 0">
                                    <td colspan="4" class="px-6 py-10 text-center text-gray-500">No hay plantillas creadas.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
