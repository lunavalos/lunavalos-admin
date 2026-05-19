<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { MegaphoneIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    clients: { type: Array, default: () => [] },
    allClients: { type: Array, default: () => [] },
});

const providerColors = {
    facebook:  'bg-blue-100 text-blue-800',
    instagram: 'bg-pink-100 text-pink-700',
    linkedin:  'bg-sky-100 text-sky-800',
    tiktok:    'bg-zinc-900 text-white',
    youtube:   'bg-red-100 text-red-700',
};

const clientSearch = ref('');
const clientDropdownOpen = ref(false);
const clientComboRef = ref(null);

const filteredClients = computed(() => {
    const q = clientSearch.value.trim().toLowerCase();
    if (!q) return props.allClients ?? [];
    return (props.allClients ?? []).filter(c =>
        c.business_name?.toLowerCase().includes(q)
    );
});

const openClientDropdown = () => {
    clientSearch.value = '';
    clientDropdownOpen.value = true;
};

const selectClient = (client) => {
    clientDropdownOpen.value = false;
    router.visit(route('social.clients.show', client.id));
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
    <Head title="Social Publishing" />
    <AuthenticatedLayout>
        <div class="p-6 max-w-7xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <MegaphoneIcon class="w-7 h-7 text-primary" />
                        Social Publishing
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Gestiona las cuentas sociales de tus clientes y publica desde una sola interfaz.
                    </p>
                </div>
            </div>

            <!-- Conectar primera cuenta -->
            <div v-if="!clients.length" class="rounded-xl border border-dashed border-gray-300 dark:border-zinc-700 p-10 text-center bg-white dark:bg-zinc-900">
                <MegaphoneIcon class="w-10 h-10 mx-auto text-gray-300" />
                <p class="mt-3 text-gray-500">Aún no hay clientes con cuentas sociales conectadas.</p>
                <p class="mt-1 text-xs text-gray-400">Entra al detalle de un cliente para conectar sus redes.</p>
                
                <div class="mt-4 max-w-md mx-auto relative text-left" ref="clientComboRef">
                    <button
                        v-if="!clientDropdownOpen"
                        type="button"
                        @click="openClientDropdown"
                        class="w-full flex items-center justify-between border border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-700 dark:text-gray-300 rounded-md shadow-sm px-3 py-2 text-sm focus:outline-none focus:border-[#264ab3] focus:ring-1 focus:ring-[#264ab3] transition"
                    >
                        <span class="text-gray-400 dark:text-zinc-500">Seleccionar cliente...</span>
                        <svg class="h-4 w-4 text-gray-400 dark:text-zinc-500 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div v-if="clientDropdownOpen" class="relative">
                        <input
                            v-model="clientSearch"
                            type="text"
                            autofocus
                            placeholder="Buscar cliente…"
                            class="w-full border border-[#264ab3] dark:border-blue-500 bg-white dark:bg-zinc-950 text-gray-800 dark:text-gray-200 rounded-md shadow-sm px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#264ab3] transition"
                        />
                        <svg class="absolute right-3 top-2.5 h-4 w-4 text-gray-400 dark:text-zinc-500 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                        </svg>
                    </div>
                    <div
                        v-if="clientDropdownOpen"
                        class="absolute z-50 mt-1 w-full bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-md shadow-xl overflow-y-auto"
                        style="max-height: 250px;"
                    >
                        <button
                            v-for="client in filteredClients"
                            :key="client.id"
                            type="button"
                            @click="selectClient(client)"
                            class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:text-[#264ab3] dark:hover:text-blue-400 transition"
                        >
                            {{ client.business_name }}
                        </button>
                        <div v-if="filteredClients.length === 0" class="px-4 py-3 text-center text-sm text-gray-400 dark:text-zinc-500 italic">
                            No se encontraron clientes
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <Link v-for="c in clients" :key="c.id" :href="route('social.clients.show', c.id)"
                      class="block bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-5 hover:shadow-md transition">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100">{{ c.business_name }}</h3>
                            <p class="text-xs text-gray-500">{{ c.contact_name }}</p>
                        </div>
                        <ArrowTopRightOnSquareIcon class="w-4 h-4 text-gray-400" />
                    </div>

                    <div class="mt-3 flex flex-wrap gap-1">
                        <span v-for="a in c.social_accounts" :key="a.id"
                              :class="['text-[10px] px-2 py-0.5 rounded-full font-medium', providerColors[a.provider] || 'bg-gray-100']">
                            {{ a.provider }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 pt-3 border-t border-gray-100 dark:border-zinc-800">
                        <div>
                            <p class="text-[10px] uppercase text-gray-400">Programados</p>
                            <p class="font-bold text-amber-600">{{ c.scheduled_count }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase text-gray-400">Publicados (mes)</p>
                            <p class="font-bold text-emerald-600">{{ c.published_count }}</p>
                        </div>
                    </div>
                </Link>
            </div>

            <div v-if="clients.length" class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-4">
                <p class="text-xs text-gray-500 mb-2">Ir a otro cliente:</p>
                <div class="w-full md:w-80 relative text-left" ref="clientComboRef">
                    <button
                        v-if="!clientDropdownOpen"
                        type="button"
                        @click="openClientDropdown"
                        class="w-full flex items-center justify-between border border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-700 dark:text-gray-300 rounded-md shadow-sm px-3 py-2 text-sm focus:outline-none focus:border-[#264ab3] focus:ring-1 focus:ring-[#264ab3] transition"
                    >
                        <span class="text-gray-400 dark:text-zinc-500">Seleccionar cliente...</span>
                        <svg class="h-4 w-4 text-gray-400 dark:text-zinc-500 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div v-if="clientDropdownOpen" class="relative">
                        <input
                            v-model="clientSearch"
                            type="text"
                            autofocus
                            placeholder="Buscar cliente…"
                            class="w-full border border-[#264ab3] dark:border-blue-500 bg-white dark:bg-zinc-950 text-gray-800 dark:text-gray-200 rounded-md shadow-sm px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#264ab3] transition"
                        />
                        <svg class="absolute right-3 top-2.5 h-4 w-4 text-gray-400 dark:text-zinc-500 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                        </svg>
                    </div>
                    <div
                        v-if="clientDropdownOpen"
                        class="absolute z-50 mt-1 w-full bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-md shadow-xl overflow-y-auto"
                        style="max-height: 250px;"
                    >
                        <button
                            v-for="client in filteredClients"
                            :key="client.id"
                            type="button"
                            @click="selectClient(client)"
                            class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:text-[#264ab3] dark:hover:text-blue-400 transition"
                        >
                            {{ client.business_name }}
                        </button>
                        <div v-if="filteredClients.length === 0" class="px-4 py-3 text-center text-sm text-gray-400 dark:text-zinc-500 italic">
                            No se encontraron clientes
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

