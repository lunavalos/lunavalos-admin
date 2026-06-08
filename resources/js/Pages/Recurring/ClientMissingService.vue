<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    client: { type: Object, required: true },
});
</script>

<template>
    <Head :title="`Recurrentes — ${client.business_name}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                    Servicios recurrentes no configurados
                </h2>
                <p class="text-sm text-gray-500 dark:text-zinc-400">
                    Actualmente no hay un plan recurrente activo para este cliente.
                </p>
            </div>
        </template>

        <div class="mt-6 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 p-8 shadow-sm">
            <p class="text-base text-gray-700 dark:text-gray-200 mb-4">
                En Lunavalos ofrecemos servicios recurrentes enfocados en crecimiento digital:
            </p>

            <ul class="grid gap-3 sm:grid-cols-2">
                <li class="rounded-2xl border border-gray-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-900 p-4">
                    <span class="font-semibold text-gray-900 dark:text-gray-100">Manejo de tienda online</span>
                </li>
                <li class="rounded-2xl border border-gray-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-900 p-4">
                    <span class="font-semibold text-gray-900 dark:text-gray-100">Email Marketing</span>
                </li>
                <li class="rounded-2xl border border-gray-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-900 p-4">
                    <span class="font-semibold text-gray-900 dark:text-gray-100">Marketing de Contenidos SEO</span>
                </li>
                <li class="rounded-2xl border border-gray-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-900 p-4">
                    <span class="font-semibold text-gray-900 dark:text-gray-100">Redes sociales</span>
                </li>
            </ul>

            <div class="mt-8 text-sm text-gray-500 dark:text-gray-400">
                Para más información visita
                <a href="https://lunavalos.com" target="_blank" rel="noreferrer" class="font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                    Lunavalos.com
                </a>
                .
            </div>
        </div>
    </AuthenticatedLayout>
</template>
