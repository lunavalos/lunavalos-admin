<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    ArrowLeftIcon,
    UserPlusIcon,
    IdentificationIcon,
    BuildingOfficeIcon,
    CurrencyDollarIcon,
    MapPinIcon,
    PhoneIcon,
    CalendarIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    users: Array
});

const form = useForm({
    user_id: '',
    employee_number: '',
    phone: '',
    curp: '',
    nss: '',
    rfc: '',
    address: '',
    blood_type: '',
    gmm_policy: '',
    gmm_insurer: '',
    gmm_advisor_name: '',
    gmm_advisor_phone: '',
    position: '',
    department: '',
    join_date: '',
    initial_salary: '',
    notes: '',
    photo: null,
});

const submit = () => {
    form.post(route('employees.store'), {
        onSuccess: () => form.reset(),
    });
};

</script>

<template>
    <Head title="Registrar Empleado" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center">
                <Link :href="route('employees.index')" class="mr-4 p-2 hover:bg-gray-100 rounded-full transition-colors">
                    <ArrowLeftIcon class="h-5 w-5 text-gray-500" />
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 flex items-center">
                    <UserPlusIcon class="h-6 w-6 mr-2 text-[#264ab3]" />
                    Registrar Nuevo Empleado
                </h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Photo Upload Section -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col items-center sm:flex-row sm:items-start gap-8">
                    <div class="relative group">
                        <div class="h-32 w-32 rounded-3xl overflow-hidden border-4 border-white shadow-xl bg-gray-50 flex items-center justify-center">
                            <div class="h-full w-full bg-blue-50 text-[#264ab3] flex items-center justify-center text-4xl font-black uppercase">
                                EM
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-gray-900 mb-1">Foto del Empleado</h3>
                        <p class="text-xs text-gray-500 mb-4">Sube una fotografía oficial para el expediente. Si no subes ninguna, se usará la del perfil de usuario vinculado (si existe).</p>
                        
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
                                class="bg-white border border-gray-200 text-gray-700 px-4 py-2 rounded-xl text-xs font-bold cursor-pointer hover:bg-gray-50 transition shadow-sm"
                            >
                                Seleccionar Imagen
                            </label>
                            <span v-if="form.photo" class="text-[10px] font-bold text-blue-600 truncate max-w-[150px]">
                                {{ form.photo.name }}
                            </span>
                        </div>
                        <div v-if="form.errors.photo" class="text-red-500 text-[10px] mt-2 font-bold">{{ form.errors.photo }}</div>
                    </div>
                </div>

                <!-- User Association -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest mb-6 flex items-center border-b border-gray-50 pb-4">
                        <IdentificationIcon class="h-4 w-4 mr-2" />
                        Identificación y Enlace
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Vincular a Usuario</label>
                            <select 
                                v-model="form.user_id"
                                class="w-full border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-[#264ab3] focus:border-transparent text-sm"
                            >
                                <option value="">--- Sin vinculación (Solo datos RRHH) ---</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">
                                    {{ user.name }} ({{ user.email }})
                                </option>
                            </select>
                            <p class="mt-1 text-[10px] text-gray-400 italic">Solo aparecen usuarios que no son clientes ni están vinculados ya.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Número de Empleado *</label>
                            <input 
                                v-model="form.employee_number"
                                type="text"
                                placeholder="P. ej. LUN-001"
                                class="w-full border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-[#264ab3] focus:border-transparent text-sm"
                                required
                            />
                            <div v-if="form.errors.employee_number" class="text-red-500 text-[10px] mt-1">{{ form.errors.employee_number }}</div>
                        </div>
                    </div>
                </div>

                <!-- Job Details -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest mb-6 flex items-center border-b border-gray-50 pb-4">
                        <BuildingOfficeIcon class="h-4 w-4 mr-2" />
                        Datos Laborales
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Puesto</label>
                            <input v-model="form.position" type="text" class="w-full border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-[#264ab3] focus:border-transparent text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Departamento</label>
                            <input v-model="form.department" type="text" class="w-full border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-[#264ab3] focus:border-transparent text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Fecha de Ingreso</label>
                            <input v-model="form.join_date" type="date" class="w-full border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-[#264ab3] focus:border-transparent text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Salario Inicial *</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <CurrencyDollarIcon class="h-4 w-4 text-gray-400" />
                                </div>
                                <input 
                                    v-model="form.initial_salary" 
                                    type="number" 
                                    step="0.01" 
                                    class="w-full pl-8 border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-[#264ab3] focus:border-transparent text-sm" 
                                    required
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Personal Info -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest mb-6 flex items-center border-b border-gray-50 pb-4">
                        <UserPlusIcon class="h-4 w-4 mr-2" />
                        Información Personal / Fiscal
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">CURP</label>
                            <input v-model="form.curp" type="text" class="w-full border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-[#264ab3] focus:border-transparent text-sm uppercase" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">RFC</label>
                            <input v-model="form.rfc" type="text" class="w-full border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-[#264ab3] focus:border-transparent text-sm uppercase" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">NSS (Seguro Social)</label>
                            <input v-model="form.nss" type="text" class="w-full border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-[#264ab3] focus:border-transparent text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Teléfono</label>
                            <input v-model="form.phone" type="text" class="w-full border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-[#264ab3] focus:border-transparent text-sm" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Dirección Completa</label>
                            <textarea v-model="form.address" rows="2" class="w-full border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-[#264ab3] focus:border-transparent text-sm"></textarea>
                        </div>
                        
                        <!-- Nuevos Campos Personal/Fiscal -->
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Tipo de Sangre</label>
                            <input v-model="form.blood_type" type="text" placeholder="Ej. O+" class="w-full border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-[#264ab3] focus:border-transparent text-sm" />
                        </div>
                        
                        <div class="md:col-span-2 mt-4 pt-4 border-t border-gray-100">
                            <h4 class="text-[10px] font-black text-blue-600 uppercase tracking-widest mb-4">Seguro de Gastos Médicos Mayores (GMM)</h4>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Póliza GMM</label>
                            <input v-model="form.gmm_policy" type="text" class="w-full border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-[#264ab3] focus:border-transparent text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Aseguradora GMM</label>
                            <input v-model="form.gmm_insurer" type="text" class="w-full border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-[#264ab3] focus:border-transparent text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Nombre Asesor GMM</label>
                            <input v-model="form.gmm_advisor_name" type="text" class="w-full border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-[#264ab3] focus:border-transparent text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Teléfono Asesor GMM</label>
                            <input v-model="form.gmm_advisor_phone" type="text" class="w-full border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-[#264ab3] focus:border-transparent text-sm" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-4 pb-12">
                    <Link 
                        :href="route('employees.index')"
                        class="px-6 py-2 rounded-xl text-sm font-bold text-gray-500 hover:bg-gray-100 transition"
                    >
                        Cancelar
                    </Link>
                    <button 
                        type="submit"
                        :disabled="form.processing"
                        class="bg-[#264ab3] hover:bg-[#193074] text-white px-8 py-2 rounded-xl text-sm font-bold shadow-sm transition disabled:opacity-50"
                    >
                        {{ form.processing ? 'Guardando...' : 'Finalizar Registro' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
