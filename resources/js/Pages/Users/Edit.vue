<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

import { ref, computed, onMounted, onUnmounted } from 'vue';
import { BuildingOfficeIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    user:    Object,
    roles:   Array,
    clients: Array,
});

const form = useForm({
    name:      props.user.name,
    email:     props.user.email,
    password:  '',
    roles:     props.user.roles.map(r => r.name),
    client_id: props.user.client_id ?? null,
});

const isClientRole = computed(() => form.roles.includes('Cliente'));

const submit = () => {
    form.put(route('users.update', props.user.id));
};

// ── Combobox buscador de cliente ─────────────────────────────────────────────
const clientSearch       = ref('');
const clientDropdownOpen = ref(false);
const clientComboRef     = ref(null);

const selectedClientLabel = computed(() => {
    if (!form.client_id) return null;
    return props.clients?.find(c => c.id === form.client_id)?.business_name ?? null;
});

const filteredClients = computed(() => {
    const q = clientSearch.value.trim().toLowerCase();
    if (!q) return props.clients ?? [];
    return (props.clients ?? []).filter(c =>
        c.business_name?.toLowerCase().includes(q)
    );
});

const openClientDropdown = () => {
    clientSearch.value = '';
    clientDropdownOpen.value = true;
};

const selectClient = (client) => {
    form.client_id           = client ? client.id : null;
    clientSearch.value       = '';
    clientDropdownOpen.value = false;
};

const clearClient = () => {
    form.client_id           = null;
    clientSearch.value       = '';
    clientDropdownOpen.value = false;
};

const handleClickOutsideClient = (e) => {
    if (clientComboRef.value && !clientComboRef.value.contains(e.target)) {
        clientDropdownOpen.value = false;
    }
};

onMounted(()  => document.addEventListener('mousedown', handleClickOutsideClient));
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutsideClient));
</script>

<template>
    <Head title="Editar Usuario" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">
                    Editar Usuario: {{ user.name }}
                </h2>
                <Link
                    :href="route('users.index')"
                    class="btn bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded"
                >
                    Volver
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="container mx-auto">
                <div class="card overflow-hidden bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg border border-gray-200 dark:border-zinc-800">
                    <div class="p-6 text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-zinc-800">
                        <form @submit.prevent="submit" class="space-y-6">
                            
                            <div>
                                <InputLabel for="name" value="Nombre" class="font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    v-model="form.name"
                                    required
                                    autofocus
                                    autocomplete="name"
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <div>
                                <InputLabel for="email" value="Correo Electrónico" class="font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    v-model="form.email"
                                    required
                                    autocomplete="username"
                                />
                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>

                            <div>
                                <InputLabel for="password" value="Contraseña (Dejar en blanco para no cambiar)" class="font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput
                                    id="password"
                                    type="password"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    v-model="form.password"
                                    autocomplete="new-password"
                                />
                                <InputError class="mt-2" :message="form.errors.password" />
                            </div>

                            <div v-if="roles && roles.length > 0">
                                <h2 class="sub-title block font-medium text-sm text-gray-700 dark:text-gray-300 mb-2 font-bold !text-xl !not-italic">
                                    Roles:
                                </h2>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <label v-for="role in roles" :key="role.id" class="flex items-center space-x-2 bg-gray-50 dark:bg-zinc-950 rounded-xl p-3 hover:bg-gray-100 dark:hover:bg-zinc-800 transition border border-gray-200 dark:border-zinc-800 mb-1 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            :value="role.name"
                                            v-model="form.roles"
                                            class="rounded border-gray-300 dark:border-zinc-800 dark:bg-zinc-900 text-primary shadow-sm focus:ring-primary"
                                        >
                                        <span class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ role.name }}</span>
                                    </label>
                                </div>
                                <InputError class="mt-2" :message="form.errors.roles" />
                            </div>

                            <!-- Client selector — visible only when 'Cliente' role is checked -->
                            <transition
                                enter-active-class="transition-all duration-300 ease-out"
                                enter-from-class="opacity-0 -translate-y-2"
                                enter-to-class="opacity-100 translate-y-0"
                                leave-active-class="transition-all duration-200 ease-in"
                                leave-from-class="opacity-100 translate-y-0"
                                leave-to-class="opacity-0 -translate-y-2"
                            >
                                <div v-if="isClientRole" class="rounded-2xl border border-blue-200 dark:border-blue-900/40 bg-blue-50 dark:bg-blue-900/10 p-5">
                                    <div class="flex items-center gap-2 mb-3">
                                        <BuildingOfficeIcon class="h-5 w-5 text-[#264ab3] dark:text-blue-400" />
                                        <h3 class="text-sm font-bold text-[#264ab3] dark:text-blue-400">Vincular a un Cliente</h3>
                                    </div>
                                    <p class="text-xs text-blue-600 dark:text-blue-400 mb-3">
                                        Selecciona la empresa a la que pertenece este usuario. Un cliente puede tener múltiples usuarios vinculados.
                                    </p>
                                <!-- Searchable client combobox -->
                                    <div ref="clientComboRef" class="relative">

                                        <!-- Trigger button (closed state) -->
                                        <button
                                            v-if="!clientDropdownOpen"
                                            type="button"
                                            @click="openClientDropdown"
                                            class="w-full flex items-center justify-between border border-blue-200 dark:border-blue-900/40 bg-white dark:bg-zinc-950 text-gray-700 dark:text-gray-300 rounded-xl shadow-sm px-3 py-2 text-sm text-left focus:outline-none focus:border-[#264ab3] focus:ring-1 focus:ring-[#264ab3] transition"
                                        >
                                            <span :class="!selectedClientLabel ? 'text-gray-400 dark:text-zinc-500 italic' : ''">
                                                {{ selectedClientLabel ?? 'Sin vincular (usuario independiente)' }}
                                            </span>
                                            <svg class="h-4 w-4 text-gray-400 dark:text-zinc-500 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                            </svg>
                                        </button>

                                        <!-- Search input (open state) -->
                                        <div v-if="clientDropdownOpen" class="relative">
                                            <input
                                                v-model="clientSearch"
                                                type="text"
                                                autofocus
                                                placeholder="Buscar cliente..."
                                                class="w-full border border-[#264ab3] dark:border-blue-500 bg-white dark:bg-zinc-950 text-gray-800 dark:text-gray-200 rounded-xl shadow-sm px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#264ab3] transition"
                                            />
                                            <svg class="absolute right-3 top-2.5 h-4 w-4 text-gray-400 dark:text-zinc-500 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                                            </svg>
                                        </div>

                                        <!-- Dropdown list -->
                                        <div
                                            v-if="clientDropdownOpen"
                                            class="absolute z-50 mt-1 w-full bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl shadow-xl overflow-y-auto"
                                            style="max-height: 260px;"
                                        >
                                            <!-- Clear option -->
                                            <button
                                                type="button"
                                                @click="clearClient"
                                                class="w-full text-left px-4 py-2.5 text-sm text-gray-400 dark:text-zinc-500 italic hover:bg-gray-50 dark:hover:bg-zinc-800 transition"
                                            >
                                                Sin vincular (usuario independiente)
                                            </button>
                                            <div class="border-t border-gray-100 dark:border-zinc-800"></div>

                                            <!-- Client options -->
                                            <button
                                                v-for="client in filteredClients"
                                                :key="client.id"
                                                type="button"
                                                @click="selectClient(client)"
                                                class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:text-[#264ab3] dark:hover:text-blue-400 transition flex items-center justify-between"
                                                :class="form.client_id === client.id ? 'bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 font-semibold' : ''"
                                            >
                                                {{ client.business_name }}
                                                <svg v-if="form.client_id === client.id" class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                                </svg>
                                            </button>

                                            <!-- Empty state -->
                                            <div v-if="filteredClients.length === 0" class="px-4 py-6 text-center text-sm text-gray-400 dark:text-zinc-500 italic">
                                                No se encontraron clientes
                                            </div>
                                        </div>
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.client_id" />
                                </div>
                            </transition>

                            <div class="flex items-center justify-end mt-4">
                                <button class="btn ml-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Actualizar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
