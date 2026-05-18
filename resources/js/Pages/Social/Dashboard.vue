<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { MegaphoneIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';

defineProps({
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
                <div class="mt-4 max-w-md mx-auto">
                    <select class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-sm"
                        @change="$event.target.value && $inertia.visit(route('social.clients.show', $event.target.value))">
                        <option value="">Seleccionar cliente…</option>
                        <option v-for="c in allClients" :key="c.id" :value="c.id">{{ c.business_name }}</option>
                    </select>
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
                <select class="w-full md:w-80 rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-sm"
                    @change="$event.target.value && $inertia.visit(route('social.clients.show', $event.target.value))">
                    <option value="">Seleccionar cliente…</option>
                    <option v-for="c in allClients" :key="c.id" :value="c.id">{{ c.business_name }}</option>
                </select>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
