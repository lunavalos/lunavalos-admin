<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowUpTrayIcon, DocumentTextIcon, CheckCircleIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

const fileInput = ref(null);
const fileError = ref('');
const isProcessing = ref(false);

const csvHeaders = ref([]);
const csvData = ref([]);

const mapping = ref({});

const SystemFields = [
    { value: 'business_name', label: 'Empresa / Negocio (Requerido)', required: true },
    { value: 'contact_name', label: 'Contacto' },
    { value: 'city', label: 'Ciudad' },
    { value: 'phone', label: 'Teléfono' },
    { value: 'email', label: 'Email' },
    { value: 'agency_source', label: 'Agencia / Origen' },
    { value: 'contract_date', label: 'Fecha Contratación (AAAA-MM-DD)' },
    { value: 'next_renewal_date', label: 'Siguiente Renovación (AAAA-MM-DD)' },
    { value: 'renewal_amount', label: 'Monto de Renovación (Solo Números)' },
    { value: 'package_services', label: 'Paquete / Servicios' },
    { value: 'domain_names', label: 'Dominios' },
    { value: 'domain_provider', label: 'Proveedor Dominio' },
    { value: 'hosting_provider', label: 'Proveedor Hosting' },
    { value: 'email_provider', label: 'Proveedor Correos' },
    { value: 'login_credentials', label: 'Credenciales / Accesos' },
    { value: 'notes', label: 'Notas' },
    { value: 'login_email', label: 'Email de Acceso (Para Entrar Aplicación)' },
    { value: 'login_password', label: 'Contraseña de Acceso (Para Entrar Aplicación)' },
    { value: 'email_accounts', label: 'Cuentas Corp. (correo|pass|usuario|tel)' },
];

const form = useForm({
    clients: [],
    truncate_clients: false,
});

//! Robust custom CSV Parser that handles commas inside quotes
const parseCSV = (str) => {
    const arr = [];
    let quote = false;
    let row = 0, col = 0;

    for (let c = 0; c < str.length; c++) {
        let cc = str[c], nc = str[c+1];
        arr[row] = arr[row] || [];
        arr[row][col] = arr[row][col] || '';

        if (cc == '"' && quote && nc == '"') { arr[row][col] += cc; ++c; continue; }
        if (cc == '"') { quote = !quote; continue; }
        if (cc == ',' && !quote) { ++col; continue; }
        if (cc == '\r' && nc == '\n' && !quote) { ++row; col = 0; ++c; continue; }
        if (cc == '\n' && !quote) { ++row; col = 0; continue; }
        if (cc == '\r' && !quote) { ++row; col = 0; continue; }
        arr[row][col] += cc;
    }
    
    // Remove if last row is strictly 1 empty cell
    if (arr[arr.length - 1] && arr[arr.length - 1].length === 1 && arr[arr.length - 1][0] === '') {
        arr.pop();
    }
    return arr;
};

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (!file) return;
    
    // Reset Data
    fileError.value = '';
    csvHeaders.value = [];
    csvData.value = [];
    mapping.value = {};
    
    if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
        fileError.value = 'El archivo seleccionado no es un CSV válido. Por favor, exporta tu Sheet como archivo ".csv".';
        fileInput.value.value = '';
        return;
    }

    isProcessing.value = true;
    
    const reader = new FileReader();
    reader.onload = (e) => {
        try {
            const text = e.target.result;
            const parsed = parseCSV(text);
            
            if (parsed.length < 2) {
                fileError.value = 'El archivo CSV está vacío o no tiene suficientes datos.';
                isProcessing.value = false;
                return;
            }
            
            csvHeaders.value = parsed[0].map(h => h ? h.trim() : 'Columna sin nombre');
            csvData.value = parsed;
            
            // Auto-Map if possible (fuzzy matching)
            SystemFields.forEach(field => {
                const index = csvHeaders.value.findIndex(h => 
                    h.toLowerCase().includes(field.label.split(' ')[0].toLowerCase()) || 
                    field.value.includes(h.toLowerCase().split(' ')[0])
                );
                if (index !== -1) {
                    mapping.value[field.value] = index;
                }
            });
            
        } catch (error) {
            fileError.value = 'Ocurrió un error al leer el archivo.';
        }
        isProcessing.value = false;
    };
    reader.readAsText(file);
};

const submitImport = () => {
    // build clients array
    const clientsData = [];
    
    // start from row 1 since row 0 is headers
    for (let r = 1; r < csvData.value.length; r++) {
        const row = csvData.value[r];
        if (!row || row.length === 0 || (row.length === 1 && !row[0])) continue;

        let obj = {};
        let hasData = false;
        
        for (const field of SystemFields) {
            const colIndex = mapping.value[field.value];
            if (colIndex !== '' && colIndex !== undefined) {
                obj[field.value] = row[colIndex] ? row[colIndex].trim() : '';
                if (obj[field.value]) hasData = true;
            }
        }
        
        // Push only if we actually found mapped data and it has a business name
        if (hasData && obj.business_name) {
            clientsData.push(obj);
        }
    }
    
    if (clientsData.length === 0) {
        alert("Atención: No se encontraron datos válidos o no has mapeado la columna 'Empresa / Negocio', la cual es obligatoria para poder importar.");
        return;
    }
    
    if (confirm(`Estás a punto de importar ${clientsData.length} clientes. ¿Continuar?`)) {
        form.clients = clientsData;
        form.post(route('clients.importBulk'));
    }
};

</script>

<template>
    <Head title="Importar Clientes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Importación Masiva de Clientes
                </h2>
                <Link
                    :href="route('clients.index')"
                    class="btn bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded shadow transition-colors"
                >
                    Volver al Directorio
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="container mx-auto">
                
                <!-- PASO 1: SUBIR ARCHIVO -->
                <div v-show="csvHeaders.length === 0" class="card bg-white shadow-md sm:rounded-xl p-10 text-center border-2 border-dashed border-gray-300 max-w-2xl mx-auto">
                    <DocumentTextIcon class="h-20 w-20 text-gray-400 mx-auto mb-4" />
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Paso 1: Sube tu tabla de datos</h3>
                    <p class="text-gray-500 mb-6 px-8">
                        Ve a Google Sheets o a tu propio Excel, entra a "Archivo" > "Descargar", y selecciona el formato <strong>Valores separados por comas (.csv)</strong>. Sube ese archivo aquí.
                    </p>
                    
                    <div v-if="fileError" class="mb-6 p-4 bg-red-50 text-red-700 rounded-lg text-sm border border-red-200">
                        {{ fileError }}
                    </div>

                    <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                        <div class="relative overflow-hidden inline-block cursor-pointer">
                            <button class="bg-[#264ab3] hover:bg-[#193074] text-white font-bold py-3 px-8 rounded shadow-lg transition-colors inline-flex items-center w-full sm:w-auto justify-center">
                                <ArrowUpTrayIcon class="h-5 w-5 mr-2" />
                                Seleccionar Archivo CSV
                            </button>
                            <input 
                                type="file" 
                                accept=".csv, text/csv"
                                ref="fileInput"
                                @change="handleFileUpload" 
                                class="absolute top-0 left-0 w-full h-full opacity-0 cursor-pointer"
                            />
                        </div>
                        <a href="/plantilla_clientes.csv" download="Plantilla de Clientes Lunavalos.csv" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-8 rounded shadow-lg transition-colors inline-flex items-center w-full sm:w-auto justify-center">
                            <ArrowDownTrayIcon class="h-5 w-5 mr-2" />
                            Descargar plantilla CSV
                        </a>
                    </div>
                </div>

                <!-- PASO 2: MAPEAR COLUMNAS -->
                <div v-if="csvHeaders.length > 0" class="card bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="bg-blue-50 border-b border-blue-100 p-6 flex justify-between items-center">
                        <div>
                            <h3 class="text-xl font-bold text-blue-900 flex items-center">
                                <CheckCircleIcon class="h-6 w-6 text-green-500 mr-2" />
                                Paso 2: Conectar tus Columnas
                            </h3>
                            <p class="text-sm text-blue-700 mt-1">
                                Hemos leído tu archivo CSV. Por favor indícale al sistema qué columna de tu Excel corresponde a los datos requeridos.
                            </p>
                        </div>
                        <button 
                            @click="fileInput.value = ''; csvHeaders = [];"
                            class="text-sm text-red-500 hover:text-red-700 font-semibold underline"
                        >
                            Subir otro archivo
                        </button>
                    </div>

                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Helper text -->
                            <div class="col-span-1 md:col-span-2 bg-gray-50 border border-gray-200 rounded p-4 text-sm text-gray-600">
                                <strong>💡 Recomendación:</strong> Deja seleccionada la opción <em>"-- Ignorar --"</em> en los campos que no utilices y el sistema los dejará vacíos.
                            </div>

                            <!-- Formulario Dinamico para Mapeo -->
                            <div v-for="field in SystemFields" :key="field.value" class="flex flex-col">
                                <label class="mb-1 font-semibold text-gray-700" :class="{'text-purple-700': field.required}">
                                    {{ field.label }} <span v-if="field.required">*</span>
                                </label>
                                <select 
                                    v-model="mapping[field.value]"
                                    class="border-gray-300 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-md shadow-sm sm:text-sm text-gray-700 bg-gray-50"
                                >
                                    <option value="">-- Ignorar (Dejar Vacío) --</option>
                                    <option 
                                        v-for="(headerName, i) in csvHeaders" 
                                        :key="i" 
                                        :value="i"
                                    >
                                        [Columna del CSV]: {{ headerName }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-8 pt-6 border-t border-gray-200 flex justify-between items-center bg-gray-50 rounded p-4">
                            <div class="flex items-center space-x-3">
                                <input 
                                    type="checkbox" 
                                    id="truncate_clients"
                                    v-model="form.truncate_clients"
                                    class="h-5 w-5 text-red-600 border-gray-300 rounded focus:ring-red-500"
                                >
                                <div>
                                    <label for="truncate_clients" class="font-bold text-red-700 block">⚠️ Vaciar tabla de clientes antes de importar</label>
                                    <span class="text-xs text-gray-500">Elimina el catálogo actual e inicia desde cero (recomendado si actualizas todo el Excel).</span>
                                </div>
                            </div>
                            <button
                                @click="submitImport"
                                class="btn bg-[#264ab3] hover:bg-[#193074] text-white font-bold py-3 px-8 rounded shadow-lg transition-colors ml-4"
                                :class="{ 'opacity-50': form.processing }"
                                :disabled="form.processing"
                            >
                                Importar {{ csvData.length - 1 }} Clientes
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
