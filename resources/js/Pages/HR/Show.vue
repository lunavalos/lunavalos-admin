<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { 
    ArrowLeftIcon,
    IdentificationIcon,
    FolderIcon,
    CurrencyDollarIcon,
    UserCircleIcon,
    ClockIcon,
    PencilSquareIcon,
    DocumentArrowUpIcon,
    TrashIcon,
    EyeIcon,
    PlusIcon,
    CalendarDaysIcon,
    TagIcon,
    PrinterIcon
} from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const props = defineProps({
    employee: Object
});

const activeTab = ref('general'); // general, documents, salary, payroll

// Document Upload Form
const docForm = useForm({
    file: null,
    document_type: 'Identificación Oficial'
});

const uploadDocument = () => {
    docForm.post(route('employees.storeDocument', props.employee.id), {
        onSuccess: () => {
            docForm.reset('file');
            document.getElementById('file-upload').value = '';
        }
    });
};

const deleteDocument = (id) => {
    if (confirm('¿Eliminar este documento permanentemente?')) {
        router.delete(route('employees.destroyDocument', id));
    }
};

// Salary Update Form
const salaryForm = useForm({
    salary: props.employee.current_salary,
    change_date: new Date().toISOString().split('T')[0],
    notes: ''
});

const updateSalary = () => {
    salaryForm.post(route('employees.updateSalary', props.employee.id), {
        onSuccess: () => {
            salaryForm.reset('notes');
            showSalaryModal.value = false;
        }
    });
};

const showSalaryModal = ref(false);
const showPayrollModal = ref(false);

const payrollForm = useForm({
    period_start: '',
    period_end: '',
    payment_date: new Date().toISOString().split('T')[0],
    base_salary: props.employee.current_salary,
    bonus: 0,
    deductions: 0,
    status: 'Pagado',
    notes: ''
});

const generatePayroll = () => {
    payrollForm.post(route('employees.storePayroll', props.employee.id), {
        onSuccess: () => {
            showPayrollModal.value = false;
            payrollForm.reset('notes', 'bonus', 'deductions');
        }
    });
};

const documentTypes = [
    'Identificación Oficial',
    'Curriculum Vitae',
    'Contrato Inicial',
    'Constancia Situación Fiscal',
    'Comprobante de Domicilio',
    'Otros'
];

</script>

<template>
    <Head :title="'Expediente: ' + employee.user?.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <Link :href="route('employees.index')" class="mr-4 p-2 hover:bg-gray-100 rounded-full transition-colors">
                        <ArrowLeftIcon class="h-5 w-5 text-gray-500" />
                    </Link>
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800 leading-tight">
                            Expediente: {{ employee.user?.name || 'Empleado #' + employee.employee_number }}
                        </h2>
                        <div class="flex items-center gap-4 mt-1">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">No. {{ employee.employee_number }}</span>
                            <span class="h-1.5 w-1.5 rounded-full bg-gray-300"></span>
                            <span class="text-xs font-bold text-gray-500 uppercase">{{ employee.position }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <a :href="route('employees.printContract', employee.id)" target="_blank" class="bg-indigo-50 border border-indigo-200 text-indigo-700 px-4 py-2 rounded-xl text-sm font-bold flex items-center hover:bg-indigo-100 transition shadow-sm">
                        <PrinterIcon class="h-4 w-4 mr-2" />
                        Imprimir Contrato
                    </a>
                    <Link :href="route('employees.edit', employee.id)" class="bg-white border border-gray-200 text-gray-600 px-4 py-2 rounded-xl text-sm font-bold flex items-center hover:bg-gray-50 transition shadow-sm">
                        <PencilSquareIcon class="h-4 w-4 mr-2" />
                        Editar Datos
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row gap-8">
                <!-- Sidebar / Basic Profile -->
                <div class="md:w-1/4">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 sticky top-8">
                        <div class="flex flex-col items-center mb-6">
                            <div class="h-24 w-24 bg-blue-50 text-[#264ab3] rounded-3xl flex items-center justify-center text-3xl font-black mb-4 border-2 border-white shadow-md uppercase">
                                {{ (employee.user?.name || 'EM').substring(0,2) }}
                            </div>
                            <h3 class="text-lg font-bold text-center text-gray-900">{{ employee.user?.name || '---' }}</h3>
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mt-1">{{ employee.department }}</p>
                            <span class="mt-4 px-3 py-1 bg-green-50 text-green-700 rounded-full text-[10px] font-black uppercase tracking-widest border border-green-100">
                                {{ employee.status }}
                            </span>
                        </div>

                        <div class="space-y-4 border-t border-gray-50 pt-6">
                            <button @click="activeTab = 'general'" :class="[activeTab === 'general' ? 'text-[#264ab3] bg-blue-50 font-black' : 'text-gray-500 hover:bg-gray-50 font-bold', 'w-full flex items-center px-4 py-3 rounded-xl transition text-sm']">
                                <UserCircleIcon class="h-5 w-5 mr-3" /> Información
                            </button>
                            <button @click="activeTab = 'documents'" :class="[activeTab === 'documents' ? 'text-indigo-600 bg-indigo-50 font-black' : 'text-gray-500 hover:bg-gray-50 font-bold', 'w-full flex items-center px-4 py-3 rounded-xl transition text-sm']">
                                <FolderIcon class="h-5 w-5 mr-3" /> Documentos
                            </button>
                            <button @click="activeTab = 'salary'" :class="[activeTab === 'salary' ? 'text-emerald-600 bg-emerald-50 font-black' : 'text-gray-500 hover:bg-gray-50 font-bold', 'w-full flex items-center px-4 py-3 rounded-xl transition text-sm']">
                                <CurrencyDollarIcon class="h-5 w-5 mr-3" /> Historial Salarial
                            </button>
                            <button @click="activeTab = 'payroll'" :class="[activeTab === 'payroll' ? 'text-purple-600 bg-purple-50 font-black' : 'text-gray-500 hover:bg-gray-50 font-bold', 'w-full flex items-center px-4 py-3 rounded-xl transition text-sm']">
                                <TagIcon class="h-5 w-5 mr-3" /> Recibos
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Main Content Panel -->
                <div class="md:w-3/4">
                    <!-- Tab: general -->
                    <div v-if="activeTab === 'general'" class="space-y-6 animate-in fade-in slide-in-from-bottom-2 duration-300">
                        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                            <h3 class="text-sm font-black text-gray-900 tracking-widest uppercase mb-8 flex items-center border-b border-gray-50 pb-4">
                                <IdentificationIcon class="h-4 w-4 mr-2" />
                                Datos Generales y Fiscales
                            </h3>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-y-10 gap-x-8">
                                <div>
                                    <dt class="text-[10px] uppercase font-black text-gray-400 tracking-widest mb-1.5">No. Empleado</dt>
                                    <dd class="text-sm font-bold text-gray-900 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-100">{{ employee.employee_number }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] uppercase font-black text-gray-400 tracking-widest mb-1.5">CURP</dt>
                                    <dd class="text-sm font-bold text-gray-900 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-100 uppercase">{{ employee.curp || '---' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] uppercase font-black text-gray-400 tracking-widest mb-1.5">RFC</dt>
                                    <dd class="text-sm font-bold text-gray-900 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-100 uppercase">{{ employee.rfc || '---' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] uppercase font-black text-gray-400 tracking-widest mb-1.5">Seguro Social (NSS)</dt>
                                    <dd class="text-sm font-bold text-gray-900 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-100">{{ employee.nss || '---' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] uppercase font-black text-gray-400 tracking-widest mb-1.5">Teléfono</dt>
                                    <dd class="text-sm font-bold text-gray-900 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-100">{{ employee.phone || '---' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] uppercase font-black text-gray-400 tracking-widest mb-1.5">Fecha de Ingreso</dt>
                                    <dd class="text-sm font-bold text-gray-900 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-100">
                                        {{ employee.join_date ? new Date(employee.join_date).toLocaleDateString('es-MX', { day:'2-digit', month:'long', year:'numeric' }) : '---' }}
                                    </dd>
                                </div>
                                <div class="sm:col-span-3">
                                    <dt class="text-[10px] uppercase font-black text-gray-400 tracking-widest mb-1.5">Dirección Registrada</dt>
                                    <dd class="text-sm font-bold text-gray-900 bg-gray-50 p-4 rounded-xl border border-gray-100">{{ employee.address || 'Sin dirección registrada' }}</dd>
                                </div>
                                <div class="sm:col-span-3" v-if="employee.notes">
                                    <dt class="text-[10px] uppercase font-black text-gray-400 tracking-widest mb-1.5">Notas / Comentarios Internos</dt>
                                    <dd class="text-sm text-gray-600 bg-yellow-50 p-4 rounded-xl border border-yellow-100 italic">"{{ employee.notes }}"</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Tab: documents -->
                    <div v-if="activeTab === 'documents'" class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 animate-in fade-in slide-in-from-bottom-2 duration-300">
                        <div class="flex justify-between items-center border-b border-gray-50 pb-6 mb-8">
                            <h3 class="text-sm font-black text-gray-900 tracking-widest uppercase flex items-center">
                                <FolderIcon class="h-4 w-4 mr-2" /> Carpeta Digital del Empleado
                            </h3>
                            <button @click="document.getElementById('file-upload-section').scrollIntoView({behavior: 'smooth'})" class="text-xs font-bold text-[#264ab3] flex items-center hover:underline">
                                <PlusIcon class="h-3 w-3 mr-1" /> Nuevo Documento
                            </button>
                        </div>

                        <!-- Documents Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
                            <div v-for="doc in employee.documents" :key="doc.id" class="group relative bg-gray-50 rounded-2xl p-5 border border-gray-100 hover:border-blue-200 hover:bg-white hover:shadow-xl transition-all duration-300">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="h-10 w-10 bg-white rounded-xl shadow-sm flex items-center justify-center text-blue-500">
                                        <DocumentArrowUpIcon class="h-6 w-6" />
                                    </div>
                                    <button @click="deleteDocument(doc.id)" class="opacity-0 group-hover:opacity-100 p-1.5 text-red-400 hover:text-red-600 transition">
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </div>
                                <h4 class="text-xs font-black text-gray-500 uppercase tracking-widest mb-1">{{ doc.document_type }}</h4>
                                <p class="text-sm font-bold text-gray-900 truncate mb-4">{{ doc.file_name }}</p>
                                <a :href="'/storage/' + doc.file_path" target="_blank" class="flex items-center text-xs font-black text-[#264ab3] group-hover:translate-x-1 transition-transform">
                                    <EyeIcon class="h-3 w-3 mr-1.5" /> VER DOCUMENTO
                                </a>
                            </div>
                            <!-- Empty State -->
                            <div v-if="employee.documents.length === 0" class="col-span-full py-12 text-center bg-gray-50 rounded-2xl border-2 border-dashed border-gray-100">
                                <p class="text-sm text-gray-400 italic">No hay documentos cargados en el expediente todavía.</p>
                            </div>
                        </div>

                        <!-- Upload Section -->
                        <div id="file-upload-section" class="bg-blue-50/50 p-6 rounded-2xl border border-blue-100 shadow-inner">
                            <h4 class="text-xs font-black text-[#264ab3] uppercase tracking-widest mb-4">Cargar Nuevo Documento</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Tipo de Documento</label>
                                    <select v-model="docForm.document_type" class="w-full border-gray-200 rounded-xl bg-white shadow-sm focus:ring-2 focus:ring-[#264ab3] text-sm font-bold">
                                        <option v-for="type in documentTypes" :key="type" :value="type">{{ type }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Seleccionar Archivo (PDF, JPG, PNG)</label>
                                    <input 
                                        type="file" 
                                        id="file-upload"
                                        @input="docForm.file = $event.target.files[0]"
                                        class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200"
                                    />
                                </div>
                                <button 
                                    @click="uploadDocument" 
                                    :disabled="docForm.processing || !docForm.file"
                                    class="bg-[#264ab3] hover:bg-[#193074] text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-sm transition disabled:opacity-50 flex justify-center items-center"
                                >
                                    <DocumentArrowUpIcon class="h-4 w-4 mr-2" /> {{ docForm.processing ? 'Subiendo...' : 'Guardar en Carpeta' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: salary -->
                    <div v-if="activeTab === 'salary'" class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 animate-in fade-in slide-in-from-bottom-2 duration-300">
                        <div class="flex justify-between items-center border-b border-gray-50 pb-6 mb-8">
                            <div>
                                <h3 class="text-sm font-black text-gray-900 tracking-widest uppercase flex items-center">
                                    <CurrencyDollarIcon class="h-4 w-4 mr-2" /> Historial de Salarios
                                </h3>
                                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-tighter mt-1">Sueldo Actual: <span class="text-green-600 text-sm ml-1">${{ Number(employee.current_salary).toLocaleString() }} MXN</span></p>
                            </div>
                            <button @click="showSalaryModal = true" class="bg-emerald-50 text-emerald-700 hover:bg-emerald-100 px-4 py-2 rounded-xl text-xs font-black flex items-center shadow-sm transition-all border border-emerald-100 uppercase tracking-widest">
                                <PlusIcon class="h-4 w-4 mr-2" /> Nuevo Ajuste
                            </button>
                        </div>

                        <!-- Timeline of salaries -->
                        <div class="relative pl-8 space-y-10 before:absolute before:inset-y-0 before:left-3 before:w-0.5 before:bg-gray-100">
                            <div v-for="log in employee.salary_histories" :key="log.id" class="relative group">
                                <div class="absolute -left-8 top-1.5 h-6 w-6 rounded-full border-4 border-white shadow-sm flex items-center justify-center bg-gray-200 group-first:bg-emerald-500"></div>
                                <div class="bg-gray-50/50 group-first:bg-white group-first:border-emerald-100 group-first:shadow-lg group-first:shadow-emerald-900/5 p-6 rounded-2xl border border-gray-100 transition-all">
                                    <div class="flex justify-between items-start mb-2">
                                        <span class="text-lg font-black text-gray-900">${{ Number(log.salary).toLocaleString() }} MXN</span>
                                        <span class="text-[10px] font-bold text-gray-400 flex items-center uppercase tracking-widest">
                                            <CalendarDaysIcon class="h-3 w-3 mr-1" /> {{ new Date(log.change_date).toLocaleDateString('es-MX', { day:'2-digit', month:'short', year:'numeric'}) }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 italic">"{{ log.notes || 'Sin observaciones registradas' }}"</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: payroll -->
                    <div v-if="activeTab === 'payroll'" class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 animate-in fade-in slide-in-from-bottom-2 duration-300">
                        <div class="flex justify-between items-center border-b border-gray-50 pb-6 mb-8">
                            <h3 class="text-sm font-black text-gray-900 tracking-widest uppercase flex items-center">
                                <TagIcon class="h-4 w-4 mr-2" /> Historial de Recibos
                            </h3>
                            <button @click="showPayrollModal = true" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-xl text-xs font-black flex items-center shadow-sm transition-all uppercase tracking-widest">
                                <PlusIcon class="h-4 w-4 mr-2" /> Generar Recibo
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-left">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest">Periodo</th>
                                        <th class="px-4 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Monto Neto</th>
                                        <th class="px-4 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest">Estatus</th>
                                        <th class="px-4 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <tr v-for="receipt in employee.payroll_receipts" :key="receipt.id" class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-4">
                                            <div class="text-xs font-bold text-gray-900">
                                                {{ new Date(receipt.period_start).toLocaleDateString('es-MX', {day:'2-digit', month:'short'}) }} - 
                                                {{ new Date(receipt.period_end).toLocaleDateString('es-MX', {day:'2-digit', month:'short', year:'numeric'}) }}
                                            </div>
                                            <div class="text-[9px] text-gray-400 uppercase font-black tracking-tighter mt-0.5">Pagado el: {{ new Date(receipt.payment_date).toLocaleDateString() }}</div>
                                        </td>
                                        <td class="px-4 py-4 text-right">
                                            <span class="text-sm font-black text-gray-900">${{ Number(receipt.net_total).toLocaleString() }}</span>
                                        </td>
                                        <td class="px-4 py-4">
                                            <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-purple-100 text-purple-700 border border-purple-200">
                                                {{ receipt.status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 text-right">
                                            <div class="flex flex-col items-end gap-1">
                                                <a :href="route('payroll.print', receipt.id)" target="_blank" class="inline-flex items-center text-[10px] font-black text-purple-600 hover:text-purple-800 uppercase tracking-widest transition">
                                                    <PrinterIcon class="h-4 w-4 mr-1" /> Carta
                                                </a>
                                                <a :href="route('payroll.print', receipt.id) + '?format=ticket'" target="_blank" class="inline-flex items-center text-[10px] font-black text-gray-500 hover:text-gray-900 uppercase tracking-widest transition">
                                                    <PrinterIcon class="h-4 w-4 mr-1" /> Ticket 80mm
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="employee.payroll_receipts.length === 0">
                                        <td colspan="4" class="px-4 py-12 text-center text-gray-400 italic text-sm">No se han generado recibos para este empleado.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payroll Modal -->
        <div v-if="showPayrollModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full p-8 relative animate-in zoom-in duration-200">
                <h3 class="text-lg font-black text-gray-900 mb-6 uppercase tracking-wider flex items-center">
                    <TagIcon class="h-6 w-6 mr-2 text-purple-500" /> Generar Recibo de Nómina
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2 grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Inicio del Periodo</label>
                            <input v-model="payrollForm.period_start" type="date" class="w-full border-gray-200 rounded-2xl focus:ring-2 focus:ring-purple-500 shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Fin del Periodo</label>
                            <input v-model="payrollForm.period_end" type="date" class="w-full border-gray-200 rounded-2xl focus:ring-2 focus:ring-purple-500 shadow-sm text-sm" />
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Sueldo Base (Periodo)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400 font-bold">$</span>
                            <input v-model="payrollForm.base_salary" type="number" class="w-full pl-8 border-gray-200 rounded-2xl focus:ring-2 focus:ring-purple-500 text-sm font-black" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Bonos / Extras</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400 font-bold">$</span>
                            <input v-model="payrollForm.bonus" type="number" class="w-full pl-8 border-gray-200 rounded-2xl focus:ring-2 focus:ring-purple-500 text-sm font-bold text-emerald-600" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Deducciones</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400 font-bold">$</span>
                            <input v-model="payrollForm.deductions" type="number" class="w-full pl-8 border-gray-200 rounded-2xl focus:ring-2 focus:ring-purple-500 text-sm font-bold text-red-600" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Fecha de Pago</label>
                        <input v-model="payrollForm.payment_date" type="date" class="w-full border-gray-200 rounded-2xl focus:ring-2 focus:ring-purple-500 shadow-sm text-sm" />
                    </div>

                    <div class="md:col-span-2 bg-gray-50 p-4 rounded-2xl border border-gray-100 flex justify-between items-center">
                        <span class="text-xs font-black text-gray-500 uppercase tracking-widest">Total a Pagar (Neto)</span>
                        <span class="text-xl font-black text-purple-700">${{ (Number(payrollForm.base_salary) + Number(payrollForm.bonus) - Number(payrollForm.deductions)).toLocaleString() }} MXN</span>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Notas</label>
                        <input v-model="payrollForm.notes" type="text" placeholder="P. ej. Pago quincena 1 de Marzo" class="w-full border-gray-200 rounded-2xl focus:ring-2 focus:ring-purple-500 text-sm" />
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <button @click="showPayrollModal = false" class="px-6 py-2.5 text-xs font-black text-gray-400 hover:text-gray-600 transition uppercase tracking-widest">Cancelar</button>
                    <button @click="generatePayroll" :disabled="payrollForm.processing" class="bg-purple-600 hover:bg-purple-700 text-white px-8 py-2.5 rounded-2xl text-xs font-black shadow-lg shadow-purple-900/10 transition disabled:opacity-50 uppercase tracking-widest">
                        Confirmar y Guardar
                    </button>
                </div>
            </div>
        </div>

        <!-- Salary Update Modal (Simple) -->
        <div v-if="showSalaryModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 relative animate-in zoom-in duration-200">
                <h3 class="text-lg font-black text-gray-900 mb-6 uppercase tracking-wider flex items-center">
                    <CurrencyDollarIcon class="h-6 w-6 mr-2 text-emerald-500" /> Nuevo Ajuste Salarial
                </h3>
                <div class="space-y-6">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Nuevo Salario Mensual</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400 font-bold">$</span>
                            <input v-model="salaryForm.salary" type="number" step="0.01" class="w-full pl-8 border-gray-200 rounded-2xl focus:ring-2 focus:ring-emerald-500 shadow-sm text-sm font-bold" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Fecha de Aplicación</label>
                        <input v-model="salaryForm.change_date" type="date" class="w-full border-gray-200 rounded-2xl focus:ring-2 focus:ring-emerald-500 shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Motivo / Notas</label>
                        <textarea v-model="salaryForm.notes" rows="3" placeholder="P. ej. Incremento anual, ascenso..." class="w-full border-gray-200 rounded-2xl focus:ring-2 focus:ring-emerald-500 shadow-sm text-sm"></textarea>
                    </div>
                </div>
                <div class="mt-8 flex justify-end gap-3">
                    <button @click="showSalaryModal = false" class="px-6 py-2.5 text-xs font-black text-gray-400 hover:text-gray-600 transition uppercase tracking-widest">Cancelar</button>
                    <button @click="updateSalary" :disabled="salaryForm.processing" class="bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-2.5 rounded-2xl text-xs font-black shadow-lg shadow-emerald-900/10 transition disabled:opacity-50 uppercase tracking-widest">
                        Registrar Cambio
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.timeline-dot::before {
    content: '';
    position: absolute;
    left: 11px;
    top: 24px;
    bottom: -40px;
    width: 2px;
    background-color: #f1f5f9;
}
</style>
