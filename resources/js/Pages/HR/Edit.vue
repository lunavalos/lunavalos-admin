<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    ArrowLeftIcon,
    PencilSquareIcon,
    IdentificationIcon,
    BuildingOfficeIcon,
    MapPinIcon,
    TagIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    employee: Object
});

const form = useForm({
    employee_number: props.employee.employee_number,
    phone: props.employee.phone,
    curp: props.employee.curp,
    nss: props.employee.nss,
    rfc: props.employee.rfc,
    address: props.employee.address,
    position: props.employee.position,
    department: props.employee.department,
    join_date: props.employee.join_date,
    status: props.employee.status,
    notes: props.employee.notes,
});

const submit = () => {
    form.put(route('employees.update', props.employee.id));
};

</script>

<template>
    <Head title="Editar Expediente" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center">
                <Link :href="route('employees.show', employee.id)" class="mr-4 p-2 hover:bg-gray-100 rounded-full transition-colors">
                    <ArrowLeftIcon class="h-5 w-5 text-gray-500" />
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 flex items-center">
                    <PencilSquareIcon class="h-6 w-6 mr-2 text-[#264ab3]" />
                    Editar Datos: {{ employee.user?.name || 'Empleado' }}
                </h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Administrative Info -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-6 flex items-center border-b border-gray-50 pb-4">
                        <IdentificationIcon class="h-4 w-4 mr-2" />
                        Información Administrativa
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Número de Empleado</label>
                            <input v-model="form.employee_number" type="text" class="w-full border-gray-200 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm" />
                            <div v-if="form.errors.employee_number" class="text-red-500 text-[10px] mt-1">{{ form.errors.employee_number }}</div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Estatus</label>
                            <select v-model="form.status" class="w-full border-gray-200 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm">
                                <option value="Activo">Activo</option>
                                <option value="Inactivo">Inactivo</option>
                                <option value="Suspendido">Suspendido</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Fecha de Ingreso</label>
                            <input v-model="form.join_date" type="date" class="w-full border-gray-200 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm" />
                        </div>
                    </div>
                </div>

                <!-- Job Details -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-6 flex items-center border-b border-gray-50 pb-4">
                        <BuildingOfficeIcon class="h-4 w-4 mr-2" />
                        Detalles del Puesto
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Puesto Actual</label>
                            <input v-model="form.position" type="text" class="w-full border-gray-200 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Departamento</label>
                            <input v-model="form.department" type="text" class="w-full border-gray-200 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm" />
                        </div>
                    </div>
                </div>

                <!-- Personal Info -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-6 flex items-center border-b border-gray-50 pb-4">
                        <TagIcon class="h-4 w-4 mr-2" />
                        Información Personal / Fiscal
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">CURP</label>
                            <input v-model="form.curp" type="text" class="w-full border-gray-200 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm uppercase" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">RFC</label>
                            <input v-model="form.rfc" type="text" class="w-full border-gray-200 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm uppercase" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">NSS</label>
                            <input v-model="form.nss" type="text" class="w-full border-gray-200 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Teléfono</label>
                            <input v-model="form.phone" type="text" class="w-full border-gray-200 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Dirección</label>
                            <input v-model="form.address" type="text" class="w-full border-gray-200 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Notas Especiales</label>
                            <textarea v-model="form.notes" rows="3" class="w-full border-gray-200 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm"></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-4 pb-12">
                    <Link 
                        :href="route('employees.show', employee.id)"
                        class="px-6 py-2 rounded-xl text-sm font-bold text-gray-500 hover:bg-gray-100 transition"
                    >
                        Cancelar
                    </Link>
                    <button 
                        type="submit"
                        :disabled="form.processing"
                        class="bg-[#264ab3] hover:bg-[#193074] text-white px-8 py-2 rounded-xl text-sm font-bold shadow-sm transition disabled:opacity-50"
                    >
                        {{ form.processing ? 'Guardando...' : 'Guardar Cambios' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
