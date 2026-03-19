<script setup>
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    staffUsers: Array,
    clientUsers: Array,
});

const form = useForm({});
const page = usePage();
const flashError = computed(() => page.props.errors.message);
const currentTab = ref('staff'); // 'staff' or 'clients'

const deleteUser = (id) => {
    if (confirm('¿Estás seguro de que deseas eliminar este usuario?')) {
        form.delete(route('users.destroy', id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Usuarios" />

    <SettingsLayout>
        <template #title>
            Usuarios y Accesos
        </template>
        <template #actions>
            <Link
                v-if="$page.props.auth.user.is_admin || $page.props.auth.user.permissions.includes('Crear Usuarios')"
                :href="route('users.create')"
                class="btn bg-[#264ab3] hover:bg-[#193074] text-white font-bold py-2 px-4 rounded shadow inline-flex items-center transition"
            >
                <PencilSquareIcon class="w-4 h-4 mr-2" />
                Crear Nuevo Acceso
            </Link>
        </template>

        <div class="py-6">
            <div class="container mx-auto">
                <div v-if="flashError" class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                  <span class="block sm:inline">{{ flashError }}</span>
                </div>

                <!-- Tabs -->
                <div class="flex space-x-4 mb-6 border-b border-gray-200">
                    <button 
                        @click="currentTab = 'staff'"
                        :class="[
                            'pb-3 px-2 text-sm font-bold uppercase transition-colors relative',
                            currentTab === 'staff' ? 'text-[#264ab3] border-b-2 border-[#264ab3]' : 'text-gray-400 hover:text-gray-600'
                        ]"
                    >
                        Equipo / Administrativos
                        <span class="ml-2 bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-[10px]">{{ staffUsers.length }}</span>
                    </button>
                    <button 
                        @click="currentTab = 'clients'"
                        :class="[
                            'pb-3 px-2 text-sm font-bold uppercase transition-colors relative',
                            currentTab === 'clients' ? 'text-[#264ab3] border-b-2 border-[#264ab3]' : 'text-gray-400 hover:text-gray-600'
                        ]"
                    >
                        Accesos Clientes
                        <span class="ml-2 bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-[10px]">{{ clientUsers.length }}</span>
                    </button>
                </div>

                <div class="card overflow-hidden bg-white shadow-sm sm:rounded-lg border border-gray-200">
                    <div class="p-0 text-gray-900 overflow-x-auto">
                        
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200">
                                    <th class="p-4 uppercase font-bold text-xs text-gray-500 tracking-wider">Usuario</th>
                                    <th class="p-4 uppercase font-bold text-xs text-gray-500 tracking-wider">Email / Contacto</th>
                                    <th class="p-4 uppercase font-bold text-xs text-gray-500 tracking-wider">Roles Asignados</th>
                                    <th class="p-4 uppercase font-bold text-xs text-gray-500 tracking-wider text-right">Gestión</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr 
                                    v-for="user in (currentTab === 'staff' ? staffUsers : clientUsers)" 
                                    :key="user.id" 
                                    class="hover:bg-gray-50 transition"
                                >
                                    <td class="p-4">
                                        <div class="flex items-center">
                                            <div class="h-8 w-8 rounded-full bg-blue-50 text-[#264ab3] flex items-center justify-center font-bold text-xs mr-3 border border-blue-100 uppercase">
                                                {{ user.name.substring(0, 2) }}
                                            </div>
                                            <span class="font-bold text-gray-800">{{ user.name }}</span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-sm text-gray-600">{{ user.email }}</td>
                                    <td class="p-4">
                                        <div class="flex flex-wrap gap-1">
                                            <span 
                                                v-for="role in user.roles" 
                                                :key="role.id"
                                                :class="[
                                                    'inline-block text-[10px] px-2 py-0.5 rounded font-bold uppercase border',
                                                    role.name === 'Cliente' ? 'bg-indigo-50 text-indigo-700 border-indigo-100' : 'bg-blue-50 text-[#264ab3] border-blue-100'
                                                ]"
                                            >
                                                {{ role.name }}
                                            </span>
                                            <span v-if="!user.roles || user.roles.length === 0" class="text-gray-400 text-xs italic">
                                                Sin roles
                                            </span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-right space-x-1">
                                        <div class="group relative inline-block">
                                            <Link
                                                v-if="$page.props.auth.user.is_admin || $page.props.auth.user.permissions.includes('Editar Usuarios')"
                                                :href="route('users.edit', user.id)"
                                                class="text-gray-400 hover:text-[#264ab3] hover:bg-blue-50 p-2 rounded-lg transition-all inline-flex items-center border border-transparent hover:border-blue-100"
                                            >
                                                <PencilSquareIcon class="w-5 h-5" />
                                            </Link>
                                            <span class="absolute bottom-full right-0 mb-2 px-2 py-1 bg-gray-800 text-white text-[10px] rounded opacity-0 group-hover:opacity-100 whitespace-nowrap pointer-events-none transition-opacity z-10">Editar</span>
                                        </div>
                                        <div class="group relative inline-block">
                                            <button
                                                v-if="($page.props.auth.user.is_admin || $page.props.auth.user.permissions.includes('Eliminar Usuarios')) && $page.props.auth.user.id !== user.id"
                                                @click="deleteUser(user.id)"
                                                class="text-gray-400 hover:text-red-500 hover:bg-red-50 p-2 rounded-lg transition-all inline-flex items-center border border-transparent hover:border-red-100"
                                            >
                                                <TrashIcon class="w-5 h-5" />
                                            </button>
                                            <span class="absolute bottom-full right-0 mb-2 px-2 py-1 bg-gray-800 text-white text-[10px] rounded opacity-0 group-hover:opacity-100 whitespace-nowrap pointer-events-none transition-opacity z-10">Eliminar</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="(currentTab === 'staff' ? staffUsers : clientUsers).length === 0">
                                    <td colspan="4" class="p-12 text-center text-gray-500 italic">
                                        <div class="flex flex-col items-center">
                                            <span class="text-3xl mb-2">👥</span>
                                            No se encontraron usuarios en esta categoría.
                                        </div>
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
