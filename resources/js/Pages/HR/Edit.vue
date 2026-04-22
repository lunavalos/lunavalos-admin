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
    birth_date: props.employee.birth_date || '',
    address: props.employee.address,
    blood_type: props.employee.blood_type || '',
    gmm_policy: props.employee.gmm_policy || '',
    gmm_insurer: props.employee.gmm_insurer || '',
    gmm_advisor_name: props.employee.gmm_advisor_name || '',
    gmm_advisor_phone: props.employee.gmm_advisor_phone || '',
    position: props.employee.position,
    department: props.employee.department,
    join_date: props.employee.join_date,
    status: props.employee.status,
    notes: props.employee.notes,
    photo: null,
    _method: 'put',
});

const submit = () => {
    form.post(route('employees.update', props.employee.id), {
        onSuccess: () => {
            // Success
        },
        forceFormData: true,
    });
};

</script>

<template>
    <Head title="Editar Expediente" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center">
                <Link :href="route('employees.show', employee.id)" class="mr-4 p-2 hover:bg-gray-100 dark:hover:bg-zinc-800 rounded-full transition-colors">
                    <ArrowLeftIcon class="h-5 w-5 text-gray-500 dark:text-zinc-400" />
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100 flex items-center">
                    <PencilSquareIcon class="h-6 w-6 mr-2 text-[#264ab3] dark:text-blue-400" />
                    Editar Datos: <span class="ml-2 text-[#264ab3] dark:text-blue-400">{{ employee.user?.name || 'Empleado' }}</span>
                </h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Photo Upload Section -->
                <div class="bg-white dark:bg-zinc-900 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-zinc-800 flex flex-col items-center sm:flex-row sm:items-start gap-8">
                    <div class="relative group">
                        <div class="h-32 w-32 rounded-3xl overflow-hidden border-4 border-white dark:border-zinc-800 shadow-xl bg-gray-50 dark:bg-zinc-950 flex items-center justify-center">
                            <img v-if="employee.photo_url" :src="employee.photo_url" class="h-full w-full object-cover" />
                            <div v-else class="h-full w-full bg-blue-50 dark:bg-blue-900/20 text-[#264ab3] dark:text-blue-400 flex items-center justify-center text-4xl font-black uppercase">
                                {{ (employee.user?.name || 'EM').substring(0,2) }}
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">Foto del Empleado</h3>
                        <p class="text-xs text-gray-500 dark:text-zinc-400 mb-4">Esta imagen es exclusiva del expediente de RRHH. Si no se sube ninguna, se mostrará la del perfil de usuario (si existe) o iniciales.</p>
                        
                        <div class="flex items-center gap-4">
                            <input 
                                type="file" 
                                id="emp-photo"
                                accept="image/*"
                                @change="form.photo = $event.target.files[0]"
                                class="hidden"
                            />
                            <label 
                                for="emp-photo" 
                                class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-xl text-xs font-bold cursor-pointer hover:bg-gray-50 dark:hover:bg-zinc-800 transition shadow-sm"
                            >
                                Seleccionar Imagen
                            </label>
                            <span v-if="form.photo" class="text-[10px] font-bold text-blue-600 dark:text-blue-400 truncate max-w-[150px]">
                                {{ form.photo.name }}
                            </span>
                        </div>
                        <div v-if="form.errors.photo" class="text-red-500 dark:text-rose-500 text-[10px] mt-2 font-bold">{{ form.errors.photo }}</div>
                    </div>
                </div>

                <!-- Administrative Info -->
                <div class="bg-white dark:bg-zinc-900 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-zinc-800">
                    <h3 class="text-xs font-black text-gray-400 dark:text-zinc-500 uppercase tracking-widest mb-6 flex items-center border-b border-gray-50 dark:border-zinc-800 pb-4">
                        <IdentificationIcon class="h-4 w-4 mr-2" />
                        Información Administrativa
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase mb-2">Número de Empleado</label>
                            <input v-model="form.employee_number" type="text" class="w-full border-gray-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm transition-colors" />
                            <div v-if="form.errors.employee_number" class="text-red-500 dark:text-rose-500 text-[10px] mt-1">{{ form.errors.employee_number }}</div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase mb-2">Estatus</label>
                            <select v-model="form.status" class="w-full border-gray-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm transition-colors">
                                <option value="Activo">Activo</option>
                                <option value="Inactivo">Inactivo</option>
                                <option value="Suspendido">Suspendido</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase mb-2">Fecha de Ingreso</label>
                            <input v-model="form.join_date" type="date" class="w-full border-gray-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm transition-colors" />
                        </div>
                    </div>
                </div>

                <!-- Job Details -->
                <div class="bg-white dark:bg-zinc-900 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-zinc-800">
                    <h3 class="text-xs font-black text-gray-400 dark:text-zinc-500 uppercase tracking-widest mb-6 flex items-center border-b border-gray-50 dark:border-zinc-800 pb-4">
                        <BuildingOfficeIcon class="h-4 w-4 mr-2" />
                        Detalles del Puesto
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase mb-2">Puesto Actual</label>
                            <input v-model="form.position" type="text" class="w-full border-gray-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm transition-colors" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase mb-2">Departamento</label>
                            <input v-model="form.department" type="text" class="w-full border-gray-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm transition-colors" />
                        </div>
                    </div>
                </div>

                <!-- Personal Info -->
                <div class="bg-white dark:bg-zinc-900 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-zinc-800">
                    <h3 class="text-xs font-black text-gray-400 dark:text-zinc-500 uppercase tracking-widest mb-6 flex items-center border-b border-gray-50 dark:border-zinc-800 pb-4">
                        <TagIcon class="h-4 w-4 mr-2" />
                        Información Personal / Fiscal
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase mb-2">Fecha de Nacimiento</label>
                            <input v-model="form.birth_date" type="date" class="w-full border-gray-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm transition-colors" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase mb-2">CURP</label>
                            <input v-model="form.curp" type="text" class="w-full border-gray-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm uppercase transition-colors" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase mb-2">RFC</label>
                            <input v-model="form.rfc" type="text" class="w-full border-gray-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm uppercase transition-colors" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase mb-2">NSS</label>
                            <input v-model="form.nss" type="text" class="w-full border-gray-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm transition-colors" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase mb-2">Teléfono</label>
                            <input v-model="form.phone" type="text" class="w-full border-gray-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm transition-colors" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase mb-2">Dirección</label>
                            <input v-model="form.address" type="text" class="w-full border-gray-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm transition-colors" />
                        </div>
                        
                        <!-- Nuevos Campos Personal/Fiscal -->
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase mb-2">Tipo de Sangre</label>
                            <input v-model="form.blood_type" type="text" placeholder="Ej. O+" class="w-full border-gray-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm transition-colors" />
                        </div>
                        
                        <div class="md:col-span-2 mt-4 pt-4 border-t border-gray-100 dark:border-zinc-800">
                            <h4 class="text-[10px] font-black text-[#264ab3] dark:text-blue-400 uppercase tracking-widest mb-4">Seguro de Gastos Médicos Mayores (GMM)</h4>
                        </div>
 
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase mb-2">Póliza GMM</label>
                            <input v-model="form.gmm_policy" type="text" class="w-full border-gray-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm transition-colors" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase mb-2">Aseguradora GMM</label>
                            <input v-model="form.gmm_insurer" type="text" class="w-full border-gray-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm transition-colors" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase mb-2">Nombre Asesor GMM</label>
                            <input v-model="form.gmm_advisor_name" type="text" class="w-full border-gray-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm transition-colors" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase mb-2">Teléfono Asesor GMM</label>
                            <input v-model="form.gmm_advisor_phone" type="text" class="w-full border-gray-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm transition-colors" />
                        </div>
                        <div class="md:col-span-2 mt-4 pt-4 border-t border-gray-100 dark:border-zinc-800">
                            <label class="block text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase mb-2">Notas Especiales</label>
                            <textarea v-model="form.notes" rows="3" class="w-full border-gray-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 rounded-xl focus:ring-2 focus:ring-[#264ab3] text-sm transition-colors"></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-4 pb-12">
                    <Link 
                        :href="route('employees.show', employee.id)"
                        class="px-6 py-2 rounded-xl text-sm font-bold text-gray-500 dark:text-zinc-400 hover:bg-gray-100 dark:hover:bg-zinc-800 transition"
                    >
                        Cancelar
                    </Link>
                    <button 
                        type="submit"
                        :disabled="form.processing"
                        class="bg-[#264ab3] dark:bg-blue-600 hover:bg-[#193074] dark:hover:bg-blue-700 text-white px-8 py-2 rounded-xl text-sm font-bold shadow-sm transition disabled:opacity-50"
                    >
                        {{ form.processing ? 'Guardando...' : 'Guardar Cambios' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
