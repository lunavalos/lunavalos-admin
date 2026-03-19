<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const isSettings = () => route().current('settings.*');
const isTab = (tabName) => {
    if (!isSettings()) return false;
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab') || 'empresa';
    return tab === tabName;
};

const isUsers = () => route().current('users.*');
const isRoles = () => route().current('roles.*');
const isAgencies = () => route().current('agencies.*');

</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col space-y-4">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        <slot name="title">Ajustes</slot>
                    </h2>
                    <slot name="actions"></slot>
                </div>
                
                <!-- Tabs -->
                <nav class="flex space-x-2 overflow-x-auto pb-1 border-b border-gray-200">
                    <Link :href="route('settings.edit', {tab: 'empresa'})"
                          :class="[isTab('empresa') ? 'border-b-2 border-[#264ab3] text-[#264ab3] font-bold' : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-2 px-4 text-sm font-medium transition-colors']">
                        Datos de la empresa
                    </Link>
                    <Link :href="route('settings.edit', {tab: 'contrato'})"
                          :class="[isTab('contrato') ? 'border-b-2 border-[#264ab3] text-[#264ab3] font-bold' : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-2 px-4 text-sm font-medium transition-colors']">
                        Contrato legal
                    </Link>
                    <Link :href="route('users.index')"
                          :class="[isUsers() ? 'border-b-2 border-[#264ab3] text-[#264ab3] font-bold' : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-2 px-4 text-sm font-medium transition-colors']"
                          v-if="$page.props.auth.user.can.view_users">
                        Usuarios
                    </Link>
                    <Link :href="route('roles.index')"
                          :class="[isRoles() ? 'border-b-2 border-[#264ab3] text-[#264ab3] font-bold' : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-2 px-4 text-sm font-medium transition-colors']"
                          v-if="$page.props.auth.user.can.view_roles">
                        Roles
                    </Link>
                    <Link :href="route('agencies.index')"
                          :class="[isAgencies() ? 'border-b-2 border-[#264ab3] text-[#264ab3] font-bold' : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-2 px-4 text-sm font-medium transition-colors']"
                          v-if="$page.props.auth.user.can.view_clients">
                        Orígenes
                    </Link>
                </nav>
            </div>
        </template>
        
        <slot />
    </AuthenticatedLayout>
</template>
