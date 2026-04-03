<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { 
    UserIcon, 
    PlusIcon, 
    MagnifyingGlassIcon,
    IdentificationIcon,
    BriefcaseIcon,
    CurrencyDollarIcon,
    ChevronRightIcon
} from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';

const props = defineProps({
    employees: Array
});

const searchQuery = ref('');

const filteredEmployees = computed(() => {
    if (!searchQuery.value) return props.employees;
    const q = searchQuery.value.toLowerCase();
    return props.employees.filter(e => 
        e.employee_number.toLowerCase().includes(q) || 
        (e.user?.name || '').toLowerCase().includes(q) ||
        (e.position || '').toLowerCase().includes(q)
    );
});

const statusColors = {
    'Activo': 'bg-green-100 text-green-700',
    'Inactivo': 'bg-red-100 text-red-700',
    'Suspendido': 'bg-yellow-100 text-yellow-700',
};

</script>

<template>
    <Head title="Recursos Humanos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 flex items-center">
                    <IdentificationIcon class="h-6 w-6 mr-2 text-[#264ab3]" />
                    Recursos Humanos
                </h2>
                <div class="flex space-x-3">
                    <Link
                        :href="route('employees.settings')"
                        class="bg-white hover:bg-gray-50 text-[#264ab3] border border-[#264ab3] font-bold py-2 px-4 rounded-lg shadow-sm flex items-center transition text-sm"
                    >
                        Plantilla de Contrato
                    </Link>
                    <Link
                        :href="route('employees.create')"
                        class="bg-[#264ab3] hover:bg-[#193074] text-white font-bold py-2 px-4 rounded-lg shadow-sm flex items-center transition text-sm"
                    >
                        <PlusIcon class="h-4 w-4 mr-2" />
                        Registrar Empleado
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center">
                    <div class="h-12 w-12 bg-blue-50 text-[#264ab3] rounded-xl flex items-center justify-center mr-4">
                        <UserIcon class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">Total Empleados</p>
                        <p class="text-2xl font-black text-gray-900">{{ employees.length }}</p>
                    </div>
                </div>
                <!-- Add more stats if needed -->
            </div>

            <!-- Search -->
            <div class="mb-6 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" />
                </div>
                <input 
                    v-model="searchQuery"
                    type="text" 
                    placeholder="Buscar por nombre, número o puesto..."
                    class="block w-full pl-10 pr-3 py-3 border border-gray-200 rounded-xl leading-5 bg-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#264ab3] focus:border-transparent sm:text-sm shadow-sm transition-all"
                >
            </div>

            <!-- Employees List -->
            <div class="bg-white shadow-sm border border-gray-100 rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-left">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Empleado</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Departamento / Puesto</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Salario Actual</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Estatus</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="emp in filteredEmployees" :key="emp.id" class="hover:bg-gray-50 transition-colors group">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0">
                                            <img v-if="emp.photo_url" :src="emp.photo_url" :alt="emp.user?.name" class="h-10 w-10 rounded-full object-cover border border-gray-100 shadow-sm" />
                                            <img v-else-if="emp.user?.profile_photo_url" :src="emp.user.profile_photo_url" :alt="emp.user.name" class="h-10 w-10 rounded-full object-cover border border-gray-100 shadow-sm grayscale-[50%] opacity-80" />
                                            <div v-else class="h-10 w-10 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center font-bold text-sm">
                                                {{ (emp.user?.name || 'EM').substring(0, 2).toUpperCase() }}
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-bold text-gray-900 tracking-tight">{{ emp.user?.name || 'Sin usuario vinculado' }}</div>
                                            <div class="text-xs text-gray-500 font-medium">No. {{ emp.employee_number }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-bold text-gray-900 flex items-center">
                                            <BriefcaseIcon class="h-3 w-3 mr-1 text-gray-400" />
                                            {{ emp.position || 'No definido' }}
                                        </span>
                                        <span class="text-xs text-gray-500">{{ emp.department || 'Sin área' }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-black text-gray-900 flex items-center">
                                        <CurrencyDollarIcon class="h-4 w-4 mr-0.5 text-green-600" />
                                        {{ Number(emp.current_salary).toLocaleString('es-MX', { minimumFractionDigits: 2 }) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="[statusColors[emp.status] || 'bg-gray-100 text-gray-600', 'px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border border-transparent']">
                                        {{ emp.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <Link 
                                        :href="route('employees.show', emp.id)"
                                        class="inline-flex items-center text-xs font-bold text-[#264ab3] hover:text-[#193074] transition uppercase tracking-widest"
                                    >
                                        Expediente
                                        <ChevronRightIcon class="h-3 w-3 ml-1" />
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="filteredEmployees.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500 italic">
                                    No se encontraron empleados registrados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
