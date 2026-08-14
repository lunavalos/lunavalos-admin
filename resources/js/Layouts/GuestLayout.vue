<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="/">
                <ApplicationLogo class="w-40 h-auto fill-current text-gray-500 mx-auto" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>

        <!--
            Enlaces legales. Los revisores de Meta, LinkedIn, TikTok y Google
            llegan al dominio de la app, no al sitio corporativo, y necesitan
            alcanzar desde aquí las políticas que declaramos en sus paneles.
        -->
        <footer class="mt-6 mb-6 px-6 text-center text-xs text-gray-500">
            <nav class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2">
                <a
                    href="https://lunavalos.com/privacy"
                    target="_blank"
                    rel="noopener"
                    class="hover:text-gray-700 hover:underline"
                >
                    Aviso de Privacidad
                </a>
                <a
                    href="https://lunavalos.com/terminos"
                    target="_blank"
                    rel="noopener"
                    class="hover:text-gray-700 hover:underline"
                >
                    Términos y Condiciones
                </a>
                <a
                    href="https://lunavalos.com/eliminar-datos"
                    target="_blank"
                    rel="noopener"
                    class="hover:text-gray-700 hover:underline"
                >
                    Eliminación de Datos
                </a>
                <a
                    href="https://lunavalos.com"
                    target="_blank"
                    rel="noopener"
                    class="hover:text-gray-700 hover:underline"
                >
                    lunavalos.com
                </a>
            </nav>
            <p class="mt-3">
                &copy; {{ new Date().getFullYear() }} LunAvalos Digital House, S.A.S.
            </p>
        </footer>
    </div>
</template>
