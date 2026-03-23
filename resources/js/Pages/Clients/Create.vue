<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { ref, nextTick } from 'vue';
import { EyeIcon, EyeSlashIcon, TrashIcon } from '@heroicons/vue/24/outline';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    quote_data: {
        type: Object,
        default: () => ({})
    },
    services: {
        type: Array,
        default: () => []
    },
    agencies: {
        type: Array,
        default: () => []
    }
});

const selectedServiceId = ref('');
const isCustomRenewal = ref(false);

const addServiceToPackage = () => {
    if (!selectedServiceId.value) return;
    const service = props.services.find(s => s.id === parseInt(selectedServiceId.value));
    if (service) {
        if (form.package_services) {
            form.package_services += ' + ' + service.name;
        } else {
            form.package_services = service.name;
        }
        
        if (!isCustomRenewal.value && service.renewal_price) {
            if (form.package_services === service.name) {
                // First service being added, so replace any manually pre-filled amount entirely
                form.renewal_amount = parseFloat(service.renewal_price);
            } else {
                form.renewal_amount = parseFloat(form.renewal_amount || 0) + parseFloat(service.renewal_price);
            }
        }

        if (service.price) {
            form.initial_price = parseFloat(form.initial_price || 0) + parseFloat(service.price);
        }

        selectedServiceId.value = '';
    }
};

const form = useForm({
    business_name: props.quote_data?.business_name || '',
    contact_name: props.quote_data?.contact_name || '',
    email: props.quote_data?.email || '',
    phone: props.quote_data?.phone || '',
    city: props.quote_data?.city || '',
    agency_source: props.quote_data?.agency_source || '',
    contract_date: props.quote_data?.contract_date || '',
    initial_price: props.quote_data?.initial_price || '',
    next_renewal_date: props.quote_data?.next_renewal_date || '',
    renewal_amount: props.quote_data?.renewal_amount || '',
    package_services: props.quote_data?.package_services || '',
    auto_renew_notice: true,
    domain_names: '',
    domain_provider: '',
    hosting_provider: '',
    email_provider: '',
    login_credentials: '',
    email_accounts: [],
    notes: '',
    login_email: '',
    login_password: '',
    quote_id: props.quote_data?.quote_id || null,
    quote_file: null,
    contract_file: null,
    branding_file: null,
    receipt_file: null,
    imap_host: '',
    imap_port: '993',
    imap_tls: true,
    pop_host: '',
    pop_port: '995',
    pop_tls: true,
    smtp_host: '',
    smtp_port: '465',
    smtp_tls: true,
    has_custom_email_config: false,
});

const showCredentials = ref(false);
const confirmingPassword = ref(false);
const passwordInput = ref(null);

const confirmForm = useForm({
    password: '',
});

const toggleCredentials = () => {
    if (showCredentials.value) {
        showCredentials.value = false;
    } else {
        confirmingPassword.value = true;
        nextTick(() => passwordInput.value?.focus());
    }
};

const verifyPassword = () => {
    confirmForm.post(route('profile.vault.verify'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showCredentials.value = true;
            closeModal();
        },
        onError: () => passwordInput.value?.focus(),
        onFinish: () => confirmForm.reset(),
    });
};

const closeModal = () => {
    confirmingPassword.value = false;
    confirmForm.clearErrors();
    confirmForm.reset();
};

const addEmailAccount = () => {
    showCredentials.value = true;
    form.email_accounts.push({
        email: '',
        password: '',
        username: '',
        phone: ''
    });
};

const removeEmailAccount = (index) => {
    form.email_accounts.splice(index, 1);
};

const submit = () => {
    form.post(route('clients.store'), {
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Nuevo Cliente" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Añadir Nuevo Cliente
                </h2>
                <Link
                    :href="route('clients.index')"
                    class="btn bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded shadow transition-colors"
                >
                    Cancelar
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="container mx-auto">
                <form @submit.prevent="submit" class="space-y-6">
                    
                    <!-- 1. Datos Generales -->
                    <div class="card bg-white shadow-sm sm:rounded-lg">
                        <div class="border-b border-gray-200 pb-4 mb-6">
                            <h3 class="text-lg font-bold text-[#264ab3]">1. Información de Contacto</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="business_name" value="Empresa / Negocio" class="font-bold text-gray-700" />
                                <TextInput
                                    id="business_name"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                    v-model="form.business_name"
                                    required
                                    placeholder="Ej. Trazamapas"
                                />
                                <InputError class="mt-2" :message="form.errors.business_name" />
                            </div>

                            <div>
                                <InputLabel for="contact_name" value="Nombre del Contacto" class="font-bold text-gray-700" />
                                <TextInput
                                    id="contact_name"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                    v-model="form.contact_name"
                                    placeholder="Ej. Juan Pérez"
                                />
                                <InputError class="mt-2" :message="form.errors.contact_name" />
                            </div>

                            <div>
                                <InputLabel for="email" value="Correo Electrónico (Email)" class="font-bold text-gray-700" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                    v-model="form.email"
                                    placeholder="contacto@empresa.com"
                                />
                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>

                            <div>
                                <InputLabel for="phone" value="Teléfono" class="font-bold text-gray-700" />
                                <TextInput
                                    id="phone"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                    v-model="form.phone"
                                    placeholder="Ej. 844 123 4567"
                                />
                                <InputError class="mt-2" :message="form.errors.phone" />
                            </div>

                            <div>
                                <InputLabel for="city" value="Ciudad" class="font-bold text-gray-700" />
                                <TextInput
                                    id="city"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                    v-model="form.city"
                                    placeholder="Ej. Saltillo, Coah"
                                />
                                <InputError class="mt-2" :message="form.errors.city" />
                            </div>

                            <div>
                                <InputLabel for="agency_source" value="Agencia / Origen" class="font-bold text-gray-700" />
                                <select
                                    id="agency_source"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    v-model="form.agency_source"
                                >
                                    <option value="" disabled>Selecciona un origen...</option>
                                    <option v-for="agency in props.agencies" :key="agency.id" :value="agency.name">
                                        {{ agency.name }}
                                    </option>
                                    <option value="Organic">Organico</option>
                                    <option value="Otro">Otro</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.agency_source" />
                            </div>
                        </div>
                    </div>

                    <!-- 2. Contrato y Renovaciones -->
                    <div class="card bg-white shadow-sm sm:rounded-lg">
                        <div class="border-b border-gray-200 pb-4 mb-6">
                            <h3 class="text-lg font-bold text-green-700">2. Fechas y Renovaciones (Automatización)</h3>
                            <p class="text-sm text-gray-500 mt-1">Configura aquí las fechas para que el sistema te recuerde cobrar a tiempo.</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div>
                                <InputLabel for="contract_date" value="Fecha de Contratación" class="font-bold text-gray-700" />
                                <TextInput
                                    id="contract_date"
                                    type="date"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                    v-model="form.contract_date"
                                />
                                <InputError class="mt-2" :message="form.errors.contract_date" />
                            </div>

                            <div>
                                <InputLabel for="initial_price" value="Pago Inicial ($ MXN)" class="font-bold text-gray-700" />
                                <div class="relative mt-1">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">$</span>
                                    </div>
                                    <TextInput
                                        id="initial_price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="block w-full pl-7 border-gray-300 focus:border-indigo-500 rounded-md shadow-sm"
                                        v-model="form.initial_price"
                                        placeholder="0.00"
                                    />
                                </div>
                                <InputError class="mt-2" :message="form.errors.initial_price" />
                            </div>

                            <div class="bg-green-50 rounded-lg p-3 border border-green-200" v-if="$page.props.auth.user.is_admin">
                                <InputLabel for="next_renewal_date" value="Día Exacto de Próxima Renovación" class="font-bold text-green-800" />
                                <TextInput
                                    id="next_renewal_date"
                                    type="date"
                                    class="mt-1 block w-full border-green-300 focus:border-green-500 focus:ring-green-500 rounded-md shadow-sm bg-white"
                                    v-model="form.next_renewal_date"
                                />
                                <InputError class="mt-2" :message="form.errors.next_renewal_date" />
                                <div class="mt-2">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" v-model="form.auto_renew_notice" class="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500">
                                        <span class="ml-2 text-xs text-green-800 font-medium">Lanzar alertas para esta fecha</span>
                                    </label>
                                </div>
                            </div>

                            <div class="bg-green-50 rounded-lg p-3 border border-green-200" v-if="$page.props.auth.user.is_admin">
                                <div class="flex justify-between items-center h-[24px]">
                                    <InputLabel for="renewal_amount" value="Monto de Renovación" class="font-bold text-green-800" />
                                </div>
                                <div class="relative mt-1">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-green-800 sm:text-sm">$</span>
                                    </div>
                                    <TextInput
                                        id="renewal_amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="block w-full pl-7 border-green-300 focus:border-green-500 focus:ring-green-500 rounded-md bg-white shadow-sm font-bold disabled:opacity-50 disabled:bg-gray-100"
                                        v-model="form.renewal_amount"
                                        placeholder="0.00"
                                        :disabled="!isCustomRenewal"
                                    />
                                </div>
                                <InputError class="mt-2" :message="form.errors.renewal_amount" />
                                <div class="mt-2 text-right">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" v-model="isCustomRenewal" class="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500">
                                        <span class="ml-2 text-xs text-green-800">Agregar precio de renovación personalizado</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <div class="flex items-center justify-between mb-2">
                                <InputLabel for="package_services" value="Paquete / Servicios Contratados" class="font-bold text-gray-700" />
                                <div class="flex items-center">
                                    <select 
                                        v-model="selectedServiceId"
                                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm py-1 pl-2 pr-8 text-gray-600 bg-gray-50"
                                        @change="addServiceToPackage"
                                    >
                                        <option value="" disabled selected>Añadir del catálogo...</option>
                                        <option v-for="s in services" :key="s.id" :value="s.id">
                                            {{ s.is_package ? '📦 ' + s.name : s.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <TextInput
                                id="package_services"
                                type="text"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                v-model="form.package_services"
                                placeholder="Ej. Diseño Web + Hosting Anual"
                            />
                            <InputError class="mt-2" :message="form.errors.package_services" />
                        </div>
                    </div>

                    <!-- 3. Activos Técnicos -->
                    <div class="card bg-white shadow-sm sm:rounded-lg">
                        <div class="border-b border-gray-200 pb-4 mb-6">
                            <h3 class="text-lg font-bold text-purple-700">3. Activos Técnicos y Accesos</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div class="md:col-span-2">
                                <InputLabel for="domain_names" value="Dominio(s) Principal(es)" class="font-bold text-gray-700" />
                                <TextInput
                                    id="domain_names"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 font-mono text-sm focus:border-indigo-500 rounded-md"
                                    v-model="form.domain_names"
                                    placeholder="ejemplo.com, ej.com.mx"
                                />
                                <InputError class="mt-2" :message="form.errors.domain_names" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel for="domain_provider" value="Proveedor Dominio" class="font-bold text-gray-700" />
                                <TextInput
                                    id="domain_provider"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                    v-model="form.domain_provider"
                                    placeholder="Ej. GoDaddy, Hostinger"
                                />
                                <InputError class="mt-2" :message="form.errors.domain_provider" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel for="hosting_provider" value="Proveedor Hosting" class="font-bold text-gray-700" />
                                <TextInput
                                    id="hosting_provider"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                    v-model="form.hosting_provider"
                                    placeholder="Ej. Hostgator (LunAvalos Reseller)"
                                />
                                <InputError class="mt-2" :message="form.errors.hosting_provider" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel for="email_provider" value="Proveedor de Correos (Servidor)" class="font-bold text-gray-700" />
                                <TextInput
                                    id="email_provider"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                    v-model="form.email_provider"
                                    placeholder="Ej. Google Workspace, Mismo que Hosting"
                                />
                                <InputError class="mt-2" :message="form.errors.email_provider" />
                            </div>
                        </div>

                        <!-- Checkbox para mostrar configuración manual -->
                        <div class="mt-6 p-4 bg-blue-50 border border-blue-100 rounded-lg">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.has_custom_email_config" class="rounded border-gray-300 text-[#264ab3] shadow-sm focus:ring-[#264ab3] h-5 w-5">
                                <div class="ml-3">
                                    <span class="block text-sm font-bold text-[#264ab3]">¿Utiliza correos del servidor corporativo? (Configuración Manual)</span>
                                    <span class="block text-xs text-blue-700">Activa esta opción si necesitas configurar IMAP/POP/SMTP manualmente para manuales de configuración.</span>
                                </div>
                            </label>
                        </div>

                        <!-- Boveda de Contraseñas -->
                        <div class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                            <div class="flex justify-between items-center mb-2">
                                <InputLabel for="login_credentials" value="Bóveda de Accesos (CPanel, Wp-Admin, etc.)" class="font-bold text-gray-800" />
                                <button type="button" @click="toggleCredentials" class="text-sm text-blue-600 hover:text-blue-800 flex items-center font-semibold">
                                    <template v-if="!showCredentials">
                                        <EyeIcon class="h-4 w-4 mr-1" /> Mostrar Contraseñas
                                    </template>
                                    <template v-else>
                                        <EyeSlashIcon class="h-4 w-4 mr-1" /> Ocultar
                                    </template>
                                </button>
                            </div>
                            <!-- Fake password font when hidden -->
                            <textarea
                                id="login_credentials"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md shadow-sm font-mono text-sm transition-all"
                                :class="{'text-transparent bg-gray-200 select-none' : !showCredentials, 'bg-white text-gray-800' : showCredentials}"
                                v-model="form.login_credentials"
                                rows="3"
                                placeholder="Escribe aquí los usuarios y contraseñas..."
                            ></textarea>
                            <div v-if="!showCredentials && form.login_credentials" class="text-xs text-gray-500 mt-1 italic">Presiona "Mostrar Contraseñas" para ver el contenido.</div>
                        </div>

                        <!-- Boveda de Correos Institucionales -->
                        <div class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                            <div class="flex justify-between items-center mb-4">
                                <InputLabel value="Cuentas de Correo Institucional" class="font-bold text-gray-800" />
                                <button type="button" @click="addEmailAccount" class="bg-[#264ab3] hover:bg-[#193074] text-white text-xs font-bold py-1.5 px-3 rounded shadow transition">
                                    + Agregar Correo
                                </button>
                            </div>
                            
                            <div v-if="form.email_accounts.length === 0" class="text-sm text-gray-500 italic text-center py-4 border-2 border-dashed border-gray-300 rounded mb-2">
                                No hay correos registrados. Presiona "+ Agregar Correo" para comenzar.
                            </div>

                            <div v-else class="overflow-x-auto mt-4 border border-gray-200 rounded-lg shadow-sm">
                                <table class="min-w-full divide-y divide-gray-200 bg-white">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th scope="col" class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider border-r border-gray-200 w-[30%]">
                                                Dirección de correo
                                            </th>
                                            <th scope="col" class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider border-r border-gray-200 w-[25%]">
                                                Contraseña
                                            </th>
                                            <th scope="col" class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider border-r border-gray-200 w-[20%]">
                                                Usuario <span class="text-gray-400 font-normal normal-case">(Opcional)</span>
                                            </th>
                                            <th scope="col" class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider border-r border-gray-200 w-[20%]">
                                                Teléfono <span class="text-gray-400 font-normal normal-case">(Opcional)</span>
                                            </th>
                                            <th scope="col" class="px-2 py-2.5 w-[5%] text-center text-xs font-medium text-gray-500 uppercase tracking-wider relative bg-gray-50">
                                                <div v-if="!showCredentials" class="absolute inset-0 bg-gray-100 flex items-center justify-center z-10" title="Desbloquea la bóveda para editar">🔒</div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200 transition-all duration-300" :class="{'opacity-50 blur-[4px] pointer-events-none select-none': !showCredentials}">
                                        <tr v-for="(account, index) in form.email_accounts" :key="index" class="hover:bg-blue-50/50 transition-colors group">
                                            <td class="border-r border-gray-200 p-0 relative">
                                                <input type="email" v-model="account.email" class="w-full border-0 focus:ring-2 focus:ring-inset focus:ring-[#264ab3] text-sm bg-transparent px-3 py-2.5 placeholder-gray-300" placeholder="ejemplo@dominio.com" required>
                                            </td>
                                            <td class="border-r border-gray-200 p-0 relative">
                                                <input type="text" v-model="account.password" class="w-full border-0 focus:ring-2 focus:ring-inset focus:ring-[#264ab3] text-sm bg-transparent px-3 py-2.5 font-mono placeholder-gray-300" placeholder="Contraseña">
                                            </td>
                                            <td class="border-r border-gray-200 p-0 relative">
                                                <input type="text" v-model="account.username" class="w-full border-0 focus:ring-2 focus:ring-inset focus:ring-[#264ab3] text-sm bg-transparent px-3 py-2.5 placeholder-gray-300" placeholder="Usuario">
                                            </td>
                                            <td class="border-r border-gray-200 p-0 relative">
                                                <input type="text" v-model="account.phone" class="w-full border-0 focus:ring-2 focus:ring-inset focus:ring-[#264ab3] text-sm bg-transparent px-3 py-2.5 placeholder-gray-300" placeholder="Teléfono">
                                            </td>
                                            <td class="p-0 text-center whitespace-nowrap align-middle bg-gray-50 group-hover:bg-red-50/30 transition-colors">
                                                <button type="button" @click="removeEmailAccount(index)" class="text-gray-400 hover:text-red-600 transition p-2 inline-flex items-center justify-center rounded-full w-full h-full" title="Eliminar fila">
                                                    <TrashIcon class="h-4 w-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <InputLabel for="notes" value="Otras Notas" class="font-bold text-gray-700" />
                            <textarea
                                id="notes"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md shadow-sm"
                                v-model="form.notes"
                                rows="2"
                                placeholder="Cualquier otra información útil..."
                            ></textarea>
                            <InputError class="mt-2" :message="form.errors.notes" />
                        </div>
                    </div>

                    <!-- 4. Configuración de Correo (IMAP/POP/SMTP) -->
                    <div v-if="form.has_custom_email_config" class="card bg-white shadow-sm sm:rounded-lg border-l-4 border-blue-600 transition-all">
                        <div class="border-b border-gray-200 pb-4 mb-6">
                            <h3 class="text-lg font-bold text-blue-600">4. Configuración de Servidores de Correo</h3>
                            <p class="text-sm text-gray-500 mt-1">Datos para el manual de configuración de correos del cliente.</p>
                        </div>

                        <div class="space-y-8">
                            <!-- IMAP -->
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                                <div class="md:col-span-2">
                                    <InputLabel for="imap_host" value="Servidor Entrante (IMAP)" class="font-bold text-gray-700" />
                                    <TextInput id="imap_host" type="text" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md" v-model="form.imap_host" placeholder="imap.ejemplo.com" />
                                </div>
                                <div>
                                    <InputLabel for="imap_port" value="Puerto IMAP" class="font-bold text-gray-700" />
                                    <TextInput id="imap_port" type="text" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md" v-model="form.imap_port" placeholder="993" />
                                </div>
                                <div class="flex items-center pb-3">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" v-model="form.imap_tls" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        <span class="ml-2 text-sm font-bold text-gray-700">Usar SSL/TLS</span>
                                    </label>
                                </div>
                            </div>

                            <!-- SMTP -->
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                                <div class="md:col-span-2">
                                    <InputLabel for="smtp_host" value="Servidor Saliente (SMTP)" class="font-bold text-gray-700" />
                                    <TextInput id="smtp_host" type="text" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md" v-model="form.smtp_host" placeholder="smtp.ejemplo.com" />
                                </div>
                                <div>
                                    <InputLabel for="smtp_port" value="Puerto SMTP" class="font-bold text-gray-700" />
                                    <TextInput id="smtp_port" type="text" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md" v-model="form.smtp_port" placeholder="465" />
                                </div>
                                <div class="flex items-center pb-3">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" v-model="form.smtp_tls" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        <span class="ml-2 text-sm font-bold text-gray-700">Usar SSL/TLS</span>
                                    </label>
                                </div>
                            </div>

                            <!-- POP3 -->
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                                <div class="md:col-span-2">
                                    <div class="flex items-center">
                                        <InputLabel for="pop_host" value="Servidor Entrante (POP3)" class="font-bold text-gray-700" />
                                        <span class="ml-2 text-[10px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded font-bold uppercase">No recomendado</span>
                                    </div>
                                    <TextInput id="pop_host" type="text" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md" v-model="form.pop_host" placeholder="pop.ejemplo.com" />
                                </div>
                                <div>
                                    <InputLabel for="pop_port" value="Puerto POP3" class="font-bold text-gray-700" />
                                    <TextInput id="pop_port" type="text" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md" v-model="form.pop_port" placeholder="995" />
                                </div>
                                <div class="flex items-center pb-3">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" v-model="form.pop_tls" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        <span class="ml-2 text-sm font-bold text-gray-700">Usar SSL/TLS</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Archivos Adjuntos -->
                    <div class="card bg-white shadow-sm sm:rounded-lg mb-6">
                        <div class="border-b border-gray-200 p-6 pb-4 mb-6">
                            <h3 class="text-lg font-bold text-gray-700">5. Documentos y Archivos</h3>
                            <p class="text-sm text-gray-500 mt-1">Adjunta la cotización, contrato y manuales del cliente.</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 p-6 pt-0">
                            <div>
                                <InputLabel for="quote_file" value="Cotización Final" class="font-bold text-gray-700" />
                                <input type="file" @input="form.quote_file = $event.target.files[0]" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200" />
                                <InputError class="mt-2" :message="form.errors.quote_file" />
                            </div>
                            <div>
                                <InputLabel for="contract_file" value="Contrato de Servicios" class="font-bold text-gray-700" />
                                <input type="file" @input="form.contract_file = $event.target.files[0]" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200" />
                                <InputError class="mt-2" :message="form.errors.contract_file" />
                            </div>
                            <div>
                                <InputLabel for="branding_file" value="Logo / Manual (ZIP/PDF)" class="font-bold text-gray-700" />
                                <input type="file" @input="form.branding_file = $event.target.files[0]" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200" />
                                <InputError class="mt-2" :message="form.errors.branding_file" />
                            </div>
                            <div>
                                <InputLabel for="receipt_file" value="Recibo de Pago" class="font-bold text-gray-700" />
                                <input type="file" @input="form.receipt_file = $event.target.files[0]" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200" />
                                <InputError class="mt-2" :message="form.errors.receipt_file" />
                            </div>
                        </div>
                    </div>

                    <!-- 6. Portal del Cliente -->
                    <div class="card bg-white shadow-sm sm:rounded-lg border-l-4 border-blue-500">
                        <div class="border-b border-gray-200 pb-4 mb-6 p-6">
                            <h3 class="text-lg font-bold text-blue-800">6. Accesos para el Portal de Cliente (Opcional)</h3>
                            <p class="text-sm text-gray-500 mt-1">Llena estos campos SOLO si deseas crearle una cuenta a este cliente para que entre a ver sus tickets de soporte. Si lo dejas vacío, solo se registrará en tu catálogo.</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 pt-0">
                            <div>
                                <InputLabel for="login_email" value="Correo de Acceso (Email del Sistema)" class="font-bold text-gray-700" />
                                <TextInput
                                    id="login_email"
                                    type="email"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md bg-blue-50"
                                    v-model="form.login_email"
                                    placeholder="cliente@ejemplo.com"
                                    autocomplete="off"
                                />
                                <InputError class="mt-2" :message="form.errors.login_email" />
                            </div>

                            <div>
                                <InputLabel for="login_password" value="Contraseña de Acceso" class="font-bold text-gray-700" />
                                <TextInput
                                    id="login_password"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md bg-blue-50"
                                    v-model="form.login_password"
                                    placeholder="Mínimo 6 caracteres"
                                    autocomplete="new-password"
                                />
                                <InputError class="mt-2" :message="form.errors.login_password" />
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 space-x-4">
                        <Link
                            :href="route('clients.index')"
                            class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 font-bold py-3 px-6 rounded shadow-sm transition"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            class="btn bg-[#264ab3] hover:bg-[#193074] text-white font-bold py-3 px-8 rounded shadow-lg transition"
                            :class="{ 'opacity-50': form.processing }"
                            :disabled="form.processing"
                        >
                            Guardar Cliente
                        </button>
                    </div>

                </form>
            </div>
        </div>

        <!-- MODAL CONTRASEÑA -->
        <Modal :show="confirmingPassword" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Confirmación de Seguridad
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    Por favor, ingresa tu contraseña de inicio de sesión para desbloquear la bóveda.
                </p>

                <div class="mt-6">
                    <TextInput
                        id="password_confirm"
                        ref="passwordInput"
                        v-model="confirmForm.password"
                        type="password"
                        class="mt-1 block w-3/4"
                        placeholder="Tu contraseña"
                        @keyup.enter="verifyPassword"
                    />

                    <InputError :message="confirmForm.errors.password" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal">
                        Cancelar
                    </SecondaryButton>

                    <PrimaryButton
                        class="ms-3"
                        :class="{ 'opacity-25': confirmForm.processing }"
                        :disabled="confirmForm.processing"
                        @click="verifyPassword"
                    >
                        Desbloquear
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>
