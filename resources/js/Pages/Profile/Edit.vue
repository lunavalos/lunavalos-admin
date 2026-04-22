<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import UpdateVaultForm from './Partials/UpdateVaultForm.vue';
import TwoFactorAuthenticationForm from './Partials/TwoFactorAuthenticationForm.vue';
import { Head, usePage } from '@inertiajs/vue3';

const isClient = usePage().props.auth.user.is_client;

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    twoFactorEnabled: {
        type: Boolean,
    },
});
</script>

<template>
    <Head title="Mi Perfil" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100"
            >
                Mi Perfil
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <!-- Mandatory 2FA Warning for All Users -->
                <div v-if="!twoFactorEnabled" class="bg-amber-50 dark:bg-amber-900/20 border-l-4 border-amber-500 p-4 rounded-lg shadow-sm">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200">Acción Requerida: Autenticación de Dos Factores Obligatoria</h3>
                            <div class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                                <p>Para garantizar la seguridad de su cuenta y cumplir con nuestras políticas de protección de datos, es obligatorio activar la autenticación de dos factores (2FA) antes de poder acceder a las herramientas de su panel.</p>
                                <p class="mt-1 font-semibold">Deslice hacia abajo hasta la sección de "Autenticación de Dos Factores" para comenzar.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-white dark:bg-zinc-900 p-4 shadow sm:rounded-lg sm:p-8 border border-gray-200 dark:border-zinc-800"
                >
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div class="bg-white dark:bg-zinc-900 p-4 shadow sm:rounded-lg sm:p-8 border border-gray-200 dark:border-zinc-800">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div class="bg-white dark:bg-zinc-900 p-4 shadow sm:rounded-lg sm:p-8 border-y border-r border-gray-200 dark:border-zinc-800 border-l-4 border-[#264ab3]">
                    <TwoFactorAuthenticationForm :enabled="twoFactorEnabled" class="max-w-xl" />
                </div>

                <div
                    v-if="!isClient"
                    class="bg-white dark:bg-zinc-900 p-4 shadow sm:rounded-lg sm:p-8 border border-gray-200 dark:border-zinc-800"
                >
                    <UpdateVaultForm class="w-full" />
                </div>

                <div
                    v-if="!isClient"
                    class="bg-white dark:bg-zinc-900 p-4 shadow sm:rounded-lg sm:p-8 border border-gray-200 dark:border-zinc-800"
                >
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
