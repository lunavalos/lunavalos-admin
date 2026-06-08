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
                    Actualmente no hay un plan recurrente activo para esta cuenta.
                </p>
            </div>
        </template>

        <div class="mt-6 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 p-8 shadow-sm">
            <p class="text-base text-gray-700 dark:text-gray-200 mb-4">
                En Lunavalos ofrecemos servicios recurrentes enfocados en crecimiento digital:
            </p>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-gray-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-900 p-4">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">Manejo de tienda online</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Gestión completa de catálogo, pedidos y presencia digital para tu comercio en línea, con foco en conversión y experiencia de usuario.</p>
                </div>
                <div class="rounded-2xl border border-gray-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-900 p-4">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">Email Marketing</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Campañas periódicas y automatizadas para tus clientes, apoyadas en una base de más de 500 contactos activos del área industrial de Coahuila.</p>
                </div>
                <div class="rounded-2xl border border-gray-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-900 p-4">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">Marketing de Contenidos SEO</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Creación de contenidos optimizados para buscadores que ayudan a posicionar tu marca y atraer tráfico orgánico de calidad.</p>
                </div>
                <div class="rounded-2xl border border-gray-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-900 p-4">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">Redes sociales</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Planeación y publicación de contenidos en redes, con seguimiento de métricas y respuesta para fortalecer tu comunidad digital.</p>
                </div>
            </div>

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
