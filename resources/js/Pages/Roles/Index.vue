<script setup>
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

defineProps({
    roles: Array,
});

const form = useForm({});

const deleteRole = (id) => {
    if (confirm('¿Estás seguro de que deseas eliminar este rol?')) {
        form.delete(route('roles.destroy', id));
    }
};
</script>

<template>
    <Head title="Roles" />

    <SettingsLayout>
        <template #title>
             Roles
        </template>
        <template #actions>
            <Link
                v-if="$page.props.auth.user.is_admin || $page.props.auth.user.permissions.includes('Crear Roles')"
                :href="route('roles.create')"
                class="btn bg-[#264ab3] hover:bg-[#193074] text-white font-bold py-2 px-4 rounded shadow inline-flex items-center transition"
            >
                Crear Rol
            </Link>
        </template>

        <div class="py-12">
            <div class="container mx-auto">
                <div class="card overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 overflow-x-auto">
                        
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600">ID</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600">Nombre</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600">Permisos</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600 text-right w-32 whitespace-nowrap">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="role in roles" :key="role.id" class="border-b hover:bg-gray-50 transition">
                                    <td class="p-3">{{ role.id }}</td>
                                    <td class="p-3 font-semibold text-primary">{{ role.name }}</td>
                                    <td class="p-3">
                                        <span 
                                            v-for="perm in role.permissions" 
                                            :key="perm.id"
                                            class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded mr-2 mb-1"
                                        >
                                            {{ perm.name }}
                                        </span>
                                        <span v-if="role.permissions.length === 0" class="text-gray-400 text-sm italic">
                                            Sin permisos
                                        </span>
                                    </td>
                                    <td class="p-3 text-right space-x-1 whitespace-nowrap">
                                        <div class="group relative inline-block">
                                            <Link
                                                v-if="$page.props.auth.user.is_admin || $page.props.auth.user.permissions.includes('Editar Roles')"
                                                :href="route('roles.edit', role.id)"
                                                class="text-gray-600 hover:text-blue-600 hover:bg-blue-50 p-2 rounded-full transition-colors inline-flex items-center"
                                            >
                                                <PencilSquareIcon class="w-5 h-5" />
                                            </Link>
                                            <span class="absolute bottom-full right-0 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 whitespace-nowrap pointer-events-none transition-opacity z-10">Editar</span>
                                        </div>
                                        <div class="group relative inline-block">
                                            <button
                                                v-if="$page.props.auth.user.is_admin || $page.props.auth.user.permissions.includes('Eliminar Roles')"
                                                @click="deleteRole(role.id)"
                                                class="text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-full transition-colors inline-flex items-center"
                                            >
                                                <TrashIcon class="w-5 h-5" />
                                            </button>
                                            <span class="absolute bottom-full right-0 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 whitespace-nowrap pointer-events-none transition-opacity z-10">Eliminar</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="roles.length === 0">
                                    <td colspan="4" class="p-4 text-center text-gray-500">
                                        No se encontraron roles registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                    </div>
                </div>
            </div>
        </div>
    </SettingsLayout>
</template>
