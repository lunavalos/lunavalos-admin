<script setup>
import { ref, onMounted } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { 
    HomeIcon, ShieldCheckIcon, UserGroupIcon, 
    BriefcaseIcon, DocumentTextIcon, UsersIcon, Cog6ToothIcon,
    ChevronLeftIcon, ChevronRightIcon, ArrowRightOnRectangleIcon,
    IdentificationIcon, Bars3Icon, InboxIcon, BellIcon, EnvelopeIcon, PencilSquareIcon,
    SwatchIcon, BanknotesIcon, LightBulbIcon, SunIcon, MoonIcon, DocumentChartBarIcon
} from '@heroicons/vue/24/outline';
import Toast from '@/Components/Toast.vue';

const page = usePage();

const notifications = ref([]);
const markAllAsRead = (url = null) => {
    // Si url es un objeto (como el evento de click de Vue), lo ignoramos
    const redirectUrl = typeof url === 'string' ? url : null;

    router.post(route('notifications.markAsRead'), {}, {
        preserveScroll: true,
        onFinish: () => {
            if (redirectUrl) {
                router.visit(redirectUrl);
            }
        }
    });
};

const isSidebarExpanded = ref(true);

const toggleSidebar = () => {
    isSidebarExpanded.value = !isSidebarExpanded.value;
    localStorage.setItem('sidebarExpanded', isSidebarExpanded.value);
};

const isDarkMode = ref(false);

const toggleDarkMode = () => {
    isDarkMode.value = !isDarkMode.value;
    if (isDarkMode.value) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('darkMode', 'true');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('darkMode', 'false');
    }
};

onMounted(() => {
    const savedSidebarState = localStorage.getItem('sidebarExpanded');
    if (savedSidebarState !== null) {
        isSidebarExpanded.value = savedSidebarState === 'true';
    }

    const savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true' || (!savedDarkMode && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        isDarkMode.value = true;
        document.documentElement.classList.add('dark');
    }

    if (page.props.auth.user) {
        window.Echo.private(`App.Models.User.${page.props.auth.user.id}`)
            .notification((notification) => {
                console.log('Real-time notification received:', notification);

                // Al recibir una notificación, la agregamos al inicio del array de notificaciones de Inertia
                page.props.auth.notifications.unshift({
                    id: notification.id,
                    data: notification,
                    created_at: new Date().toISOString(),
                    read_at: null
                });

                // También podemos mostrar una notificación visual básica
                if ("Notification" in window && Notification.permission === "granted") {
                    new Notification(notification.title, {
                        body: notification.message
                    });
                }
            });
    }
});

</script>

<template>
    <div class="flex flex-col h-screen bg-[#f9fafb] dark:bg-zinc-950 font-sans overflow-hidden transition-colors duration-300">
        
        <!-- Topbar: spans full width -->
        <header class="h-16 bg-white dark:bg-zinc-900 flex items-center justify-between px-4 sm:px-6 border-b border-gray-200 dark:border-zinc-800 z-30 shrink-0">
            <div class="flex items-center">
                <!-- Sidebar Toggle -->
                <button 
                    @click="toggleSidebar" 
                    class="p-2 mr-3 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 text-[#264ab3] dark:text-blue-400 shadow-sm hover:bg-gray-50 dark:hover:bg-zinc-700 focus:outline-none rounded-md transition-colors"
                >
                    <Bars3Icon class="h-6 w-6"/>
                </button>
                <!-- Logo -->
                <Link :href="route('dashboard')" class="flex items-center">
                    <ApplicationLogo class="h-8 auto dark:brightness-200" />
                </Link>
            </div>

            <!-- Notifications & DarkMode & User Dropdown -->
            <div class="flex items-center space-x-3">
                
                <!-- Dark Mode Toggle -->
                <button 
                    @click="toggleDarkMode" 
                    class="p-2 text-gray-400 hover:text-[#264ab3] dark:hover:text-blue-400 transition-colors focus:outline-none bg-gray-50 dark:bg-zinc-800 rounded-full"
                    :title="isDarkMode ? 'Modo Claro' : 'Modo Oscuro'"
                >
                    <SunIcon v-if="isDarkMode" class="h-6 w-6" />
                    <MoonIcon v-else class="h-6 w-6" />
                </button>

                <!-- Notifications Dropdown -->
                <div class="relative">
                    <Dropdown align="right" width="80">
                        <template #trigger>
                            <button type="button" class="relative p-2 text-gray-400 hover:text-[#264ab3] dark:hover:text-blue-400 transition-colors focus:outline-none bg-gray-50 dark:bg-zinc-800 rounded-full">
                                <BellIcon class="h-6 w-6" />
                                <span v-if="$page.props.auth.notifications.length > 0" class="absolute top-0 right-0 block h-4 w-4 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center border-2 border-white dark:border-zinc-900 ring-red-500">
                                    {{ $page.props.auth.notifications.length }}
                                </span>
                            </button>
                        </template>

                        <template #content>
                            <div class="p-4 border-b border-gray-100 dark:border-zinc-800 flex justify-between items-center bg-gray-50/50 dark:bg-zinc-900 text-gray-800 dark:text-gray-100">
                                <h3 class="text-xs font-bold uppercase tracking-widest text-[#264ab3] dark:text-blue-400">Notificaciones</h3>
                                <button 
                                    v-if="$page.props.auth.notifications.length > 0"
                                    @click="markAllAsRead" 
                                    class="text-[10px] text-blue-600 dark:text-blue-400 hover:underline font-bold"
                                >
                                    Marcar como leídas
                                </button>
                            </div>
                            <div class="max-h-96 overflow-y-auto custom-scrollbar dark:bg-zinc-900">
                                <div v-if="$page.props.auth.notifications.length === 0" class="p-8 text-center">
                                    <BellIcon class="h-8 w-8 text-gray-200 dark:text-zinc-800 mx-auto mb-2" />
                                    <p class="text-xs text-gray-400 dark:text-zinc-500">Sin notificaciones pendientes</p>
                                </div>
                                <div v-else>
                                    <template v-for="notif in $page.props.auth.notifications" :key="notif.id">
                                        <div 
                                            class="block p-4 hover:bg-blue-50 dark:hover:bg-zinc-800/50 transition-colors border-b border-gray-50 dark:border-zinc-800 last:border-0 cursor-pointer"
                                            @click="markAllAsRead(notif.data.url)"
                                        >
                                            <div class="flex items-start">
                                                <div class="flex-1">
                                                    <p class="text-xs font-bold text-gray-800 dark:text-gray-200 mb-1 line-clamp-2">
                                                        {{ notif.data.message }}
                                                    </p>
                                                    <p class="text-[10px] text-gray-400 dark:text-zinc-500">
                                                        Ticket #{{ notif.data.ticket_id }}
                                                    </p>
                                                </div>
                                                <div class="h-2 w-2 rounded-full bg-blue-600 mt-1 shrink-0 ml-2"></div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </Dropdown>
                </div>

                <!-- Settings/User Dropdown -->
                <div class="relative">
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <span class="inline-flex rounded-md items-center">
                                <button type="button" class="inline-flex items-center gap-2 border border-transparent bg-white dark:bg-zinc-900 pl-3 pr-2 py-1 text-sm font-medium text-gray-700 dark:text-gray-300 transition duration-150 ease-in-out hover:text-[#264ab3] dark:hover:text-blue-400 focus:outline-none">
                                    <img 
                                        :src="$page.props.auth.user.profile_photo_url" 
                                        :alt="$page.props.auth.user.name" 
                                        class="h-8 w-8 rounded-full border border-gray-200 dark:border-zinc-800 object-cover"
                                    />
                                    <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </span>
                        </template>

                        <template #content>
                            <div class="dark:bg-zinc-900">
                                <DropdownLink :href="route('profile.edit')">
                                    Mi Perfil
                                </DropdownLink>
                                <DropdownLink :href="route('logout')" method="post" as="button">
                                    Cerrar Sesión
                                </DropdownLink>
                            </div>
                        </template>
                    </Dropdown>
                </div>
            </div>
        </header>

        <!-- Main Body Area -->
        <div class="flex flex-1 overflow-hidden">
            
            <!-- Sidebar -->
            <aside 
                :class="[
                    'bg-white dark:bg-zinc-900 border-r border-gray-200 dark:border-zinc-800 flex flex-col transition-all duration-300 ease-in-out z-20', 
                    isSidebarExpanded ? 'w-20 md:w-64 flex' : 'hidden md:flex md:w-20'
                ]"
            >
                <!-- Navigation Links -->
                <nav class="flex-1 overflow-y-auto py-4">
                    <ul class="space-y-2 px-3">
                        <!-- Dashboard (Visible to everyone) -->
                        <li>
                            <Link 
                                :href="route('dashboard')"
                                :class="[
                                    route().current('dashboard') 
                                        ? 'bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 font-semibold shadow-sm' 
                                        : 'text-gray-600 dark:text-zinc-400 hover:bg-gray-50 dark:hover:bg-zinc-800/50 hover:text-[#264ab3] dark:hover:text-blue-400',
                                    'group flex items-center rounded-md transition-colors duration-200 cursor-pointer',
                                    isSidebarExpanded ? 'px-3 py-2.5' : 'justify-center py-3'
                                ]"
                                :title="!isSidebarExpanded ? 'Dashboard' : ''"
                            >
                                <HomeIcon class="h-6 w-6 flex-shrink-0" aria-hidden="true" />
                                <span v-if="isSidebarExpanded" class="ml-4 uppercase text-sm tracking-wide truncate">
                                    Dashboard
                                </span>
                            </Link>
                        </li>

                        <!-- Tickets Link (Visible to everyone) -->
                        <li>
                            <Link 
                                :href="route('tickets.index')"
                                :class="[
                                    route().current('tickets.*') 
                                        ? 'bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 font-semibold shadow-sm' 
                                        : 'text-gray-600 dark:text-zinc-400 hover:bg-gray-50 dark:hover:bg-zinc-800/50 hover:text-[#264ab3] dark:hover:text-blue-400',
                                    'group flex items-center rounded-md transition-colors duration-200 cursor-pointer',
                                    isSidebarExpanded ? 'px-3 py-2.5' : 'justify-center py-3'
                                ]"
                                :title="!isSidebarExpanded ? 'Tickets' : ''"
                            >
                                <InboxIcon class="h-6 w-6 flex-shrink-0" aria-hidden="true" />
                                <span v-if="isSidebarExpanded" class="ml-4 uppercase text-sm tracking-wide truncate">
                                    Tickets
                                </span>
                            </Link>
                        </li>

                        <!-- Emails Link (Only for clients with custom config) -->
                        <li v-if="$page.props.auth.user.is_client && $page.props.auth.user.has_custom_email_config">
                            <Link 
                                :href="route('client.emails')"
                                :class="[
                                    route().current('client.emails') 
                                        ? 'bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 font-semibold shadow-sm' 
                                        : 'text-gray-600 dark:text-zinc-400 hover:bg-gray-50 dark:hover:bg-zinc-800/50 hover:text-[#264ab3] dark:hover:text-blue-400',
                                    'group flex items-center rounded-md transition-colors duration-200 cursor-pointer',
                                    isSidebarExpanded ? 'px-3 py-2.5' : 'justify-center py-3'
                                ]"
                                :title="!isSidebarExpanded ? 'Configuracion de Emails' : ''"
                            >
                                <EnvelopeIcon class="h-6 w-6 flex-shrink-0" aria-hidden="true" />
                                <span v-if="isSidebarExpanded" class="ml-4 uppercase text-sm tracking-wide truncate">
                                    Emails
                                </span>
                            </Link>
                        </li>

                        <!-- Signature Link (For all clients) -->
                        <li v-if="$page.props.auth.user.is_client">
                            <Link 
                                :href="route('client.signatures')"
                                :class="[
                                    route().current('client.signatures') 
                                        ? 'bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 font-semibold shadow-sm' 
                                        : 'text-gray-600 dark:text-zinc-400 hover:bg-gray-50 dark:hover:bg-zinc-800/50 hover:text-[#264ab3] dark:hover:text-blue-400',
                                    'group flex items-center rounded-md transition-colors duration-200 cursor-pointer',
                                    isSidebarExpanded ? 'px-3 py-2.5' : 'justify-center py-3'
                                ]"
                                :title="!isSidebarExpanded ? 'Generador de Firma' : ''"
                            >
                                <SwatchIcon class="h-6 w-6 flex-shrink-0" aria-hidden="true" />
                                <span v-if="isSidebarExpanded" class="ml-4 uppercase text-sm tracking-wide truncate">
                                    Firma
                                </span>
                            </Link>
                        </li>

                        <!-- Briefing Link (For all clients) -->
                        <li v-if="$page.props.auth.user.is_client">
                            <Link 
                                :href="route('client.briefing')"
                                :class="[
                                    route().current('client.briefing') 
                                        ? 'bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 font-semibold shadow-sm' 
                                        : 'text-gray-600 dark:text-zinc-400 hover:bg-gray-50 dark:hover:bg-zinc-800/50 hover:text-[#264ab3] dark:hover:text-blue-400',
                                    'group flex items-center rounded-md transition-colors duration-200 cursor-pointer',
                                    isSidebarExpanded ? 'px-3 py-2.5' : 'justify-center py-3'
                                ]"
                                :title="!isSidebarExpanded ? 'Briefing Creativo' : ''"
                            >
                                <LightBulbIcon class="h-6 w-6 flex-shrink-0" aria-hidden="true" />
                                <span v-if="isSidebarExpanded" class="ml-4 uppercase text-sm tracking-wide truncate">
                                    Briefing
                                </span>
                            </Link>
                        </li>

                        <!-- Reports Link (For clients with linked client_id) -->
                        <li v-if="$page.props.auth.user.is_client && $page.props.auth.user.client_id">
                            <Link 
                                :href="route('client.reports.index')"
                                :class="[
                                    route().current('client.reports.*') 
                                        ? 'bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 font-semibold shadow-sm' 
                                        : 'text-gray-600 dark:text-zinc-400 hover:bg-gray-50 dark:hover:bg-zinc-800/50 hover:text-[#264ab3] dark:hover:text-blue-400',
                                    'group flex items-center rounded-md transition-colors duration-200 cursor-pointer',
                                    isSidebarExpanded ? 'px-3 py-2.5' : 'justify-center py-3'
                                ]"
                                :title="!isSidebarExpanded ? 'Mis Reportes' : ''"
                            >
                                <DocumentChartBarIcon class="h-6 w-6 flex-shrink-0" aria-hidden="true" />
                                <span v-if="isSidebarExpanded" class="ml-4 uppercase text-sm tracking-wide truncate">
                                    Reportes
                                </span>
                            </Link>
                        </li>

                        <!-- ADMIN LINKS EXCLUSIVE -->
                        <template v-if="!$page.props.auth.user.is_client">

                        <!-- Services Link -->
                        <li v-if="$page.props.auth.user.can.view_services">
                            <Link 
                                :href="route('services.index')"
                                :class="[
                                    route().current('services.*') 
                                        ? 'bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 font-semibold shadow-sm' 
                                        : 'text-gray-600 dark:text-zinc-400 hover:bg-gray-50 dark:hover:bg-zinc-800/50 hover:text-[#264ab3] dark:hover:text-blue-400',
                                    'group flex items-center rounded-md transition-colors duration-200 cursor-pointer',
                                    isSidebarExpanded ? 'px-3 py-2.5' : 'justify-center py-3'
                                ]"
                                :title="!isSidebarExpanded ? 'Servicios' : ''"
                            >
                                <BriefcaseIcon class="h-6 w-6 flex-shrink-0" aria-hidden="true" />
                                <span v-if="isSidebarExpanded" class="ml-4 uppercase text-sm tracking-wide truncate">
                                    Servicios
                                </span>
                            </Link>
                        </li>
                        <!-- Quotes Link -->
                        <li v-if="$page.props.auth.user.can.view_quotes !== false">
                            <Link 
                                :href="route('quotes.index')"
                                :class="[
                                    route().current('quotes.*') 
                                        ? 'bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 font-semibold shadow-sm' 
                                        : 'text-gray-600 dark:text-zinc-400 hover:bg-gray-50 dark:hover:bg-zinc-800/50 hover:text-[#264ab3] dark:hover:text-blue-400',
                                    'group flex items-center rounded-md transition-colors duration-200 cursor-pointer',
                                    isSidebarExpanded ? 'px-3 py-2.5' : 'justify-center py-3'
                                ]"
                                :title="!isSidebarExpanded ? 'Cotizaciones' : ''"
                            >
                                <DocumentTextIcon class="h-6 w-6 flex-shrink-0" aria-hidden="true" />
                                <span v-if="isSidebarExpanded" class="ml-4 uppercase text-sm tracking-wide truncate">
                                    Cotizaciones
                                </span>
                            </Link>
                        </li>
                        
                        <!-- Finances Link -->
                        <li v-if="$page.props.auth.user.is_admin || ($page.props.auth.user.permissions && $page.props.auth.user.permissions.includes('Ver Finanzas'))">
                            <Link 
                                :href="route('finances.index')"
                                :class="[
                                    route().current('finances.*') 
                                        ? 'bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 font-semibold shadow-sm' 
                                        : 'text-gray-600 dark:text-zinc-400 hover:bg-gray-50 dark:hover:bg-zinc-800/50 hover:text-[#264ab3] dark:hover:text-blue-400',
                                    'group flex items-center rounded-md transition-colors duration-200 cursor-pointer',
                                    isSidebarExpanded ? 'px-3 py-2.5' : 'justify-center py-3'
                                ]"
                                :title="!isSidebarExpanded ? 'Finanzas' : ''"
                            >
                                <BanknotesIcon class="h-6 w-6 flex-shrink-0" aria-hidden="true" />
                                <span v-if="isSidebarExpanded" class="ml-4 uppercase text-sm tracking-wide truncate">
                                    Finanzas
                                </span>
                            </Link>
                        </li>

                        <!-- Reports Link -->
                        <li v-if="$page.props.auth.user.can.view_reports">
                            <Link 
                                :href="route('reports.index')"
                                :class="[
                                    route().current('reports.*') 
                                        ? 'bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 font-semibold shadow-sm' 
                                        : 'text-gray-600 dark:text-zinc-400 hover:bg-gray-50 dark:hover:bg-zinc-800/50 hover:text-[#264ab3] dark:hover:text-blue-400',
                                    'group flex items-center rounded-md transition-colors duration-200 cursor-pointer',
                                    isSidebarExpanded ? 'px-3 py-2.5' : 'justify-center py-3'
                                ]"
                                :title="!isSidebarExpanded ? 'Reportes' : ''"
                            >
                                <DocumentChartBarIcon class="h-6 w-6 flex-shrink-0" aria-hidden="true" />
                                <span v-if="isSidebarExpanded" class="ml-4 uppercase text-sm tracking-wide truncate">
                                    Reportes
                                </span>
                            </Link>
                        </li>

                        <!-- Clients Link -->
                        <li v-if="$page.props.auth.user.can.view_clients">
                            <Link 
                                :href="route('clients.index')"
                                :class="[
                                    route().current('clients.*') 
                                        ? 'bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 font-semibold shadow-sm' 
                                        : 'text-gray-600 dark:text-zinc-400 hover:bg-gray-50 dark:hover:bg-zinc-800/50 hover:text-[#264ab3] dark:hover:text-blue-400',
                                    'group flex items-center rounded-md transition-colors duration-200 cursor-pointer',
                                    isSidebarExpanded ? 'px-3 py-2.5' : 'justify-center py-3'
                                ]"
                                :title="!isSidebarExpanded ? 'Clientes' : ''"
                            >
                                <UsersIcon class="h-6 w-6 flex-shrink-0" aria-hidden="true" />
                                <span v-if="isSidebarExpanded" class="ml-4 uppercase text-sm tracking-wide truncate">
                                    Clientes
                                </span>
                            </Link>
                        </li>
                        
                        <!-- HR Link -->
                        <li v-if="$page.props.auth.user.is_admin || $page.props.auth.user.permissions.includes('Ver RRHH')">
                            <Link 
                                :href="route('employees.index')"
                                :class="[
                                    route().current('employees.*') 
                                        ? 'bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 font-semibold shadow-sm' 
                                        : 'text-gray-600 dark:text-zinc-400 hover:bg-gray-50 dark:hover:bg-zinc-800/50 hover:text-[#264ab3] dark:hover:text-blue-400',
                                    'group flex items-center rounded-md transition-colors duration-200 cursor-pointer',
                                    isSidebarExpanded ? 'px-3 py-2.5' : 'justify-center py-3'
                                ]"
                                :title="!isSidebarExpanded ? 'Recursos Humanos' : ''"
                            >
                                <IdentificationIcon class="h-6 w-6 flex-shrink-0" aria-hidden="true" />
                                <span v-if="isSidebarExpanded" class="ml-4 uppercase text-sm tracking-wide truncate">
                                    RRHH
                                </span>
                            </Link>
                        </li>
                        <!-- Settings Link -->
                        <li v-if="$page.props.auth.user.is_admin">
                            <Link 
                                :href="route('settings.edit')"
                                :class="[
                                    route().current('settings.*') 
                                        ? 'bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 font-semibold shadow-sm' 
                                        : 'text-gray-600 dark:text-zinc-400 hover:bg-gray-50 dark:hover:bg-zinc-800/50 hover:text-[#264ab3] dark:hover:text-blue-400',
                                    'group flex items-center rounded-md transition-colors duration-200 cursor-pointer',
                                    isSidebarExpanded ? 'px-3 py-2.5' : 'justify-center py-3'
                                ]"
                                :title="!isSidebarExpanded ? 'Ajustes' : ''"
                            >
                                <Cog6ToothIcon class="h-6 w-6 flex-shrink-0" aria-hidden="true" />
                                <span v-if="isSidebarExpanded" class="ml-4 uppercase text-sm tracking-wide truncate">
                                    Ajustes
                                </span>
                            </Link>
                        </li>

                        <!-- Signature Templates (Admin) -->
                        <li v-if="$page.props.auth.user.is_admin">
                            <Link 
                                :href="route('signature-templates.index')"
                                :class="[
                                    route().current('signature-templates.*') 
                                        ? 'bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 font-semibold shadow-sm' 
                                        : 'text-gray-600 dark:text-zinc-400 hover:bg-gray-50 dark:hover:bg-zinc-800/50 hover:text-[#264ab3] dark:hover:text-blue-400',
                                    'group flex items-center rounded-md transition-colors duration-200 cursor-pointer',
                                    isSidebarExpanded ? 'px-3 py-2.5' : 'justify-center py-3'
                                ]"
                                :title="!isSidebarExpanded ? 'Plantillas de Firmas' : ''"
                            >
                                <SwatchIcon class="h-6 w-6 flex-shrink-0" aria-hidden="true" />
                                <span v-if="isSidebarExpanded" class="ml-4 uppercase text-sm tracking-wide truncate">
                                    Plantillas Firmas
                                </span>
                            </Link>
                        </li>
                        </template>
                    </ul>
                </nav>
            </aside>

            <!-- Main Content Area -->
            <div class="flex-1 flex flex-col overflow-hidden bg-gray-50 dark:bg-zinc-950 relative z-10">
                <!-- Page Header -->
                <div class="bg-white dark:bg-zinc-900 px-4 py-6 sm:px-6 lg:px-8 border-b border-gray-200 dark:border-zinc-800" v-if="$slots.header">
                    <slot name="header" />
                </div>

                <!-- Page Main Content -->
                <main class="flex-1 overflow-x-hidden overflow-y-auto p-4 sm:p-6 lg:p-8">
                    <slot />
                </main>
            </div>
        </div>
        <Toast />
    </div>
</template>

<style scoped>
@media (max-width: 767px) {
    aside {
        width: 5rem !important;
    }
    aside span {
        display: none !important;
    }
    aside a {
        justify-content: center !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        padding-top: 0.75rem !important;
        padding-bottom: 0.75rem !important;
    }
}
</style>
