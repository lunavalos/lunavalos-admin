<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { DocumentTextIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Wysiwyg from '@/Components/Wysiwyg.vue';

const props = defineProps({
    contract_template: String
});

const form = useForm({
    contract_template: props.contract_template || '',
});

const submit = () => {
    form.post(route('employees.updateSettings'), {
        preserveScroll: true
    });
};
</script>

<template>
    <Head title="Plantilla de Contrato - RRHH" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight flex items-center">
                <DocumentTextIcon class="h-6 w-6 mr-2 text-[#264ab3] dark:text-blue-400" />
                Plantilla de Contrato para Empleados
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Template Form -->
                <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-sm rounded-2xl border border-gray-100 dark:border-zinc-800">
                    <div class="p-8">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-6 flex items-center">
                            Configuración del Documento Base
                        </h3>
                        
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/30 p-4 rounded-xl mb-6">
                            <h4 class="font-bold text-[#264ab3] dark:text-blue-400 mb-2 text-sm flex items-center">
                                Variables disponibles:
                            </h4>
                            <p class="text-xs text-gray-700 dark:text-zinc-400 leading-relaxed max-w-4xl columns-1 md:columns-2">
                                <code class="bg-white dark:bg-zinc-950 px-1 py-0.5 rounded text-[#264ab3] dark:text-blue-400 border border-blue-100 dark:border-blue-800/30">[nombre_empleado]</code> Nombre completo del empleado<br>
                                <code class="bg-white dark:bg-zinc-950 px-1 py-0.5 rounded text-[#264ab3] dark:text-blue-400 border border-blue-100 dark:border-blue-800/30">[numero_empleado]</code> Número de empleado<br>
                                <code class="bg-white dark:bg-zinc-950 px-1 py-0.5 rounded text-[#264ab3] dark:text-blue-400 border border-blue-100 dark:border-blue-800/30">[telefono]</code> Teléfono<br>
                                <code class="bg-white dark:bg-zinc-950 px-1 py-0.5 rounded text-[#264ab3] dark:text-blue-400 border border-blue-100 dark:border-blue-800/30">[curp]</code> CURP<br>
                                <code class="bg-white dark:bg-zinc-950 px-1 py-0.5 rounded text-[#264ab3] dark:text-blue-400 border border-blue-100 dark:border-blue-800/30">[nss]</code> Número de Seguro Social<br>
                                <code class="bg-white dark:bg-zinc-950 px-1 py-0.5 rounded text-[#264ab3] dark:text-blue-400 border border-blue-100 dark:border-blue-800/30">[rfc]</code> RFC del empleado<br>
                                <code class="bg-white dark:bg-zinc-950 px-1 py-0.5 rounded text-[#264ab3] dark:text-blue-400 border border-blue-100 dark:border-blue-800/30">[direccion]</code> Dirección del empleado<br>
                                <code class="bg-white dark:bg-zinc-950 px-1 py-0.5 rounded text-[#264ab3] dark:text-blue-400 border border-blue-100 dark:border-blue-800/30">[puesto]</code> Puesto<br>
                                <code class="bg-white dark:bg-zinc-950 px-1 py-0.5 rounded text-[#264ab3] dark:text-blue-400 border border-blue-100 dark:border-blue-800/30">[departamento]</code> Departamento o área<br>
                                <code class="bg-white dark:bg-zinc-950 px-1 py-0.5 rounded text-[#264ab3] dark:text-blue-400 border border-blue-100 dark:border-blue-800/30">[fecha_ingreso]</code> Fecha en la que ingresa laborar<br>
                                <code class="bg-white dark:bg-zinc-950 px-1 py-0.5 rounded text-[#264ab3] dark:text-blue-400 border border-blue-100 dark:border-blue-800/30">[salario_actual]</code> Salario en moneda con signo ($)<br>
                                <code class="bg-white dark:bg-zinc-950 px-1 py-0.5 rounded text-[#264ab3] dark:text-blue-400 border border-blue-100 dark:border-blue-800/30">[razon_social]</code> Razón social de la empresa<br>
                                <code class="bg-white dark:bg-zinc-950 px-1 py-0.5 rounded text-[#264ab3] dark:text-blue-400 border border-blue-100 dark:border-blue-800/30">[rfc_empresa]</code> RFC de la empresa<br>
                                <code class="bg-white dark:bg-zinc-950 px-1 py-0.5 rounded text-[#264ab3] dark:text-blue-400 border border-blue-100 dark:border-blue-800/30">[fecha_actual]</code> Fecha del sistema actual<br>
                            </p>
                        </div>

                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <InputLabel for="contract_template" value="Contenido del Contrato" class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" />
                                <div class="bg-gray-50 dark:bg-zinc-950 border border-gray-200 dark:border-zinc-800 rounded-2xl overflow-hidden focus-within:ring-2 focus-within:ring-[#264ab3]/20 focus-within:border-[#264ab3] transition-all">
                                    <Wysiwyg 
                                        v-model="form.contract_template"
                                        placeholder="Redacta la estructura general del contrato para los empleados aquí..."
                                    />
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-end pt-6 border-t border-gray-100 dark:border-zinc-800">
                                <div v-if="form.recentlySuccessful" class="text-sm font-bold text-green-600 dark:text-emerald-400 mr-4 flex items-center">
                                    <CheckCircleIcon class="h-5 w-5 mr-1" />
                                    Guardado
                                </div>
                                <PrimaryButton
                                    :class="{ 'opacity-50': form.processing }"
                                    :disabled="form.processing"
                                    class="bg-[#264ab3] hover:bg-[#193074] shadow-md hover:shadow-lg transition-all"
                                >
                                    Guardar Cambios
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
