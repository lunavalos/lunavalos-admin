<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ref, computed, watch, nextTick } from 'vue';
import { CheckCircleIcon, PlusCircleIcon, PlusIcon, TagIcon, PaperClipIcon, TrashIcon, UserIcon, ArrowLeftIcon, BuildingOfficeIcon, UserPlusIcon, MagnifyingGlassIcon, EyeIcon, EyeSlashIcon, ChartBarIcon } from '@heroicons/vue/24/outline';
import axios from 'axios';

const props = defineProps({
    client: Object,
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
        // For monthly billing, the recurring charge is the base price, not the renewal_price (annuity)
        const billingType = service.billing_type || 'annual';
        const serviceAmount = billingType === 'monthly'
            ? parseFloat(service.price || 0)
            : parseFloat(service.renewal_price || 0);

        form.services.push({
            service_id: service.id,
            service_name: service.name,
            renewal_date: '',
            renewal_amount: serviceAmount,
            billing_type: billingType,
            initial_payment: parseFloat(service.price || 0), // pre-fill with service setup price
            initial_cost: 0,
        });

        if (form.package_services) {
            form.package_services += ' + ' + service.name;
        } else {
            form.package_services = service.name;
        }

        selectedServiceId.value = '';
    }
};

const addManualService = () => {
    form.services.push({
        service_id: null,
        service_name: '',
        renewal_date: '',
        renewal_amount: 0,
        billing_type: 'annual',
        initial_payment: 0,
        initial_cost: 0,
    });
};

const removeService = (index) => {
    form.services.splice(index, 1);
};

const form = useForm({
    business_name: props.client.business_name || '',
    contact_name: props.client.contact_name || '',
    email: props.client.email || '',
    phone: props.client.phone || '',
    city: props.client.city || '',
    agency_source: props.client.agency_source || '',
    contract_date: props.client.contract_date ? props.client.contract_date.split('T')[0] : '', // format from datetime casting
    initial_price: props.client.initial_price || '',
    domain_names: props.client.domain_names || '',
    domain_provider: props.client.domain_provider || '',
    hosting_provider: props.client.hosting_provider || '',
    email_provider: props.client.email_provider || '',
    login_credentials: props.client.login_credentials || '',
    email_accounts: props.client.email_accounts || [],
    vault_credentials: props.client.vault_credentials || [],
    notes: props.client.notes || '',
    login_email: '',
    login_password: '',
    _method: 'put',
    quote_file: null,
    contract_file: null,
    branding_file: null,
    receipt_file: null,
    imap_host: props.client.imap_host || '',
    imap_port: props.client.imap_port || '993',
    imap_tls: props.client.imap_tls === 1 || props.client.imap_tls === true,
    pop_host: props.client.pop_host || '',
    pop_port: props.client.pop_port || '995',
    pop_tls: props.client.pop_tls === 1 || props.client.pop_tls === true,
    smtp_host: props.client.smtp_host || '',
    smtp_port: props.client.smtp_port || '465',
    smtp_tls: props.client.smtp_tls === 1 || props.client.smtp_tls === true,
    has_custom_email_config: props.client.has_custom_email_config === 1 || props.client.has_custom_email_config === true,
    services: props.client.services ? props.client.services.map(s => ({
        id: s.id,
        service_id: s.service_id,
        service_name: s.service_name,
        renewal_date: s.renewal_date ? s.renewal_date.split('T')[0] : '',
        renewal_amount: s.renewal_amount,
        billing_type: s.billing_type || 'annual',
        initial_payment: parseFloat(s.initial_payment || 0),
        initial_cost: parseFloat(s.initial_cost || 0),
    })) : [],
});

const fileInput = ref(null);
const attachedFiles = ref([...(props.client.attachments || [])]);

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

const addVaultCredential = () => {
    showCredentials.value = true;
    form.vault_credentials.push({
        service: '',
        url: '',
        username: '',
        password: '',
        notes: ''
    });
};

const removeVaultCredential = (index) => {
    form.vault_credentials.splice(index, 1);
};

const submit = () => {
    form.post(route('clients.update', props.client.id), {
        forceFormData: true,
    });
};

const deleteClient = () => {
    if (confirm('¿Estás seguro de que deseas eliminar este cliente? Esta acción no se puede deshacer.')) {
        form.delete(route('clients.destroy', props.client.id));
    }
};
const costForm = useForm({
    concept: '',
    amount: '',
    billing_frequency: 'monthly',
});
const addingCost = ref(false);

const submitCost = () => {
    costForm.post(route('clients.costs.store', props.client.id), {
        onSuccess: () => {
            addingCost.value = false;
            costForm.reset();
        },
        preserveScroll: true
    });
};

const deleteCost = (costId) => {
    if (confirm('¿Estás seguro de que deseas eliminar este costo interno?')) {
        costForm.delete(route('clients.costs.destroy', [props.client.id, costId]), {
            preserveScroll: true
        });
    }
};

const formatCurrency = (value) => {
    if (!value) return '$0.00';
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(value);
};

const totalMonthlyCosts = computed(() => {
    // Costos del módulo de costos internos con frecuencia mensual
    const fromCostsTable = (props.client.costs || [])
        .filter(c => c.billing_frequency === 'monthly')
        .reduce((sum, c) => sum + parseFloat(c.amount || 0), 0);

    // Costo inicial de servicios mensuales (también es un costo recurrente mensual)
    const fromMonthlyServices = form.services
        .filter(s => s.billing_type === 'monthly')
        .reduce((sum, s) => sum + parseFloat(s.initial_cost || 0), 0);

    return fromCostsTable + fromMonthlyServices;
});

const totalAnnualCosts = computed(() => {
    if (!props.client.costs) return 0;
    return props.client.costs
        .filter(c => c.billing_frequency === 'annual')
        .reduce((sum, c) => sum + parseFloat(c.amount), 0);
});

const totalUniqueCosts = computed(() => {
    if (!props.client.costs) return 0;
    return props.client.costs
        .filter(c => c.billing_frequency === 'unique')
        .reduce((sum, c) => sum + parseFloat(c.amount), 0);
});

// Income totals — reactive from form.services (updates live as user edits)
const totalMonthlyIncome = computed(() =>
    form.services
        .filter(s => s.billing_type === 'monthly')
        .reduce((sum, s) => sum + parseFloat(s.renewal_amount || 0), 0)
);

const totalAnnualIncome = computed(() =>
    form.services
        .filter(s => s.billing_type === 'annual')
        .reduce((sum, s) => sum + parseFloat(s.renewal_amount || 0), 0)
);

const totalUniqueIncome = computed(() =>
    form.services
        .filter(s => s.billing_type === 'once')
        .reduce((sum, s) => sum + parseFloat(s.renewal_amount || 0), 0)
);

// Per-service initial fields — also reactive
const totalInitialPayment = computed(() =>
    form.services.reduce((sum, s) => sum + parseFloat(s.initial_payment || 0), 0)
);

const totalInitialCost = computed(() =>
    form.services.reduce((sum, s) => sum + parseFloat(s.initial_cost || 0), 0)
);

const monthlyNetMargin = computed(() => totalMonthlyIncome.value - totalMonthlyCosts.value);

</script>

<template>
    <Head title="Editar Cliente" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100 flex items-center">
                    <span class="mr-2">Editar Cliente:</span> 
                    <span class="text-[#264ab3] dark:text-blue-400">{{ client.business_name }}</span>
                </h2>
                <div class="space-x-3 flex items-center">
                    <button 
                        @click="deleteClient"
                        class="text-red-400 hover:text-red-600 transition"
                        title="Eliminar Cliente"
                    >
                        <TrashIcon class="h-6 w-6" />
                    </button>
                    <Link
                        :href="route('clients.index')"
                        class="btn bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded shadow transition-colors"
                    >
                        Volver al Directorio
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="container mx-auto">
                <form @submit.prevent="submit" class="space-y-6">
                    
                    <!-- 1. Datos Generales -->
                    <div class="card bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg border border-gray-100 dark:border-zinc-800 p-6">
                        <div class="border-b border-gray-200 dark:border-zinc-800 pb-4 mb-6">
                            <h3 class="text-lg font-bold text-[#264ab3] dark:text-blue-400">1. Información de Contacto</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="business_name" value="Empresa / Negocio" class="font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput
                                    id="business_name"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md"
                                    v-model="form.business_name"
                                    required
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
                                />
                                <InputError class="mt-2" :message="form.errors.city" />
                            </div>

                            <div>
                                <InputLabel for="agency_source" value="Agencia / Origen" class="font-bold text-gray-700 dark:text-gray-300" />
                                <select
                                    id="agency_source"
                                    class="mt-1 block w-full border-gray-300 dark:border-zinc-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:bg-zinc-950 dark:text-gray-100 transition-colors"
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

                    <!-- 7. Costos Internos y Rentabilidad (Nuevo Módulo) -->
                    <div class="card bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg border border-gray-100 dark:border-zinc-800 mb-6" v-if="$page.props.auth.user.is_admin">
                        <div class="border-b border-gray-200 dark:border-zinc-800 p-6 pb-4">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 flex items-center">
                                        <ChartBarIcon class="h-5 w-5 mr-2 text-indigo-600 dark:text-indigo-400" />
                                        Rentabilidad y Costos Internos
                                    </h3>
                                    <p class="text-sm text-gray-500 dark:text-zinc-400 mt-1">Lleva el control de lo que este cliente te cuesta mantener (dominio, freelancers, licencias, etc).</p>
                                </div>
                                <button type="button" @click="addingCost = !addingCost" class="btn bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded shadow-sm text-sm transition transition-colors">
                                    {{ addingCost ? 'Cancelar' : '+ Agregar Costo' }}
                                </button>
                            </div>
                        </div>

                        <!-- Formulario para Agregar Costo -->
                        <div v-if="addingCost" class="p-6 bg-indigo-50 dark:bg-indigo-950/20 border-b border-indigo-100 dark:border-indigo-900">
                            <h4 class="font-bold text-indigo-800 dark:text-indigo-400 mb-4 text-sm uppercase">Nuevo Costo</h4>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                                <div class="md:col-span-2">
                                    <InputLabel for="cost_concept" value="Concepto" class="font-bold text-gray-700 dark:text-gray-300" />
                                    <TextInput id="cost_concept" type="text" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md" v-model="costForm.concept" placeholder="Ej: Dominio web, Diseñador Freelance, etc." />
                                    <InputError class="mt-2" :message="costForm.errors.concept" />
                                </div>
                                <div>
                                    <InputLabel for="cost_amount" value="Costo ($)" class="font-bold text-gray-700" />
                                    <TextInput id="cost_amount" type="number" step="0.01" min="0" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md" v-model="costForm.amount" placeholder="0.00" />
                                    <InputError class="mt-2" :message="costForm.errors.amount" />
                                </div>
                                <div>
                                    <InputLabel for="cost_freq" value="Frecuencia" class="font-bold text-gray-700 dark:text-gray-300" />
                                    <select id="cost_freq" class="mt-1 block w-full border-gray-300 dark:border-zinc-800 focus:border-indigo-500 rounded-md shadow-sm dark:bg-zinc-950 dark:text-gray-100 transition-colors" v-model="costForm.billing_frequency">
                                        <option value="monthly">Mensual</option>
                                        <option value="annual">Anual</option>
                                        <option value="unique">Único</option>
                                    </select>
                                    <InputError class="mt-2" :message="costForm.errors.billing_frequency" />
                                </div>
                                <div class="md:col-span-4 mt-2">
                                    <button type="button" @click="submitCost" class="btn bg-[#264ab3] hover:bg-[#193074] text-white font-bold py-2 px-6 rounded shadow" :disabled="costForm.processing">
                                        Guardar Costo
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Tabla de Costos -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-gray-50 dark:bg-zinc-950/50 border-b dark:border-zinc-800 border-t dark:border-zinc-800 text-xs uppercase text-gray-600 dark:text-zinc-400 font-bold">
                                    <tr>
                                        <th class="p-4" style="width: 50%">Concepto</th>
                                        <th class="p-4 text-center">Frecuencia</th>
                                        <th class="p-4 text-right">Costo Estimado</th>
                                        <th class="p-4 text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="cost in client.costs" :key="cost.id" class="border-b border-gray-100 dark:border-zinc-800/50 hover:bg-gray-50 dark:hover:bg-zinc-800/30 transition-colors">
                                        <td class="p-4 font-medium text-gray-800 dark:text-gray-200">{{ cost.concept }}</td>
                                        <td class="p-4 text-center text-sm">
                                            <span class="px-2 py-1 rounded-full bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-zinc-300 text-xs font-semibold" v-if="cost.billing_frequency === 'monthly'">Mensual</span>
                                            <span class="px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-xs font-semibold" v-else-if="cost.billing_frequency === 'annual'">Anual</span>
                                            <span class="px-2 py-1 rounded-full bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 text-xs font-semibold" v-else>Único</span>
                                        </td>
                                        <td class="p-4 text-right font-bold text-red-600 dark:text-rose-400">{{ formatCurrency(cost.amount) }}</td>
                                        <td class="p-4 text-center">
                                            <button type="button" @click="deleteCost(cost.id)" class="text-gray-400 hover:text-red-500 transition" title="Eliminar Costo">
                                                <TrashIcon class="h-5 w-5 inline-block" />
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!client.costs || client.costs.length === 0">
                                        <td colspan="4" class="p-6 text-center text-gray-400 dark:text-zinc-500 italic">No se han registrado costos internos para este cliente.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
 
                        <!-- Sumario de Utilidad -->
                        <div class="bg-gray-50 dark:bg-zinc-950/50 p-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 rounded-b-lg border-t border-gray-200 dark:border-zinc-800">
                            <!-- Ingresos Estimados -->
                            <div>
                                <h4 class="text-xs font-bold uppercase text-gray-500 dark:text-zinc-500 mb-2 tracking-wider">Métrica de Ingresos</h4>
                                <div class="text-sm flex justify-between mb-1">
                                    <span class="text-gray-600 dark:text-zinc-400">Cobro Inicial:</span>
                                    <span class="font-bold text-gray-800 dark:text-gray-200">{{ formatCurrency(totalInitialPayment) }}</span>
                                </div>
                                <div class="text-sm flex justify-between mb-1">
                                    <span class="text-gray-600 dark:text-zinc-400">Únicos (servicios):</span>
                                    <span class="font-bold text-green-700 dark:text-emerald-400">{{ formatCurrency(totalUniqueIncome) }}</span>
                                </div>
                                <div class="text-sm flex justify-between pb-1 mb-1 border-b border-gray-200 dark:border-zinc-800">
                                    <span class="text-gray-600 dark:text-zinc-400">Anuales:</span>
                                    <span class="font-bold text-green-700 dark:text-emerald-400">{{ formatCurrency(totalAnnualIncome) }}</span>
                                </div>
                                <div class="text-sm flex justify-between">
                                    <span class="text-gray-600 dark:text-zinc-400">Mensuales:</span>
                                    <span class="font-bold text-green-700 dark:text-emerald-400">{{ formatCurrency(totalMonthlyIncome) }}</span>
                                </div>
                            </div>

                            <!-- Costos Estimados -->
                            <div>
                                <h4 class="text-xs font-bold uppercase text-gray-500 dark:text-zinc-500 mb-2 tracking-wider">Totales de Costos</h4>
                                <div class="text-sm flex justify-between mb-1">
                                    <span class="text-gray-600 dark:text-zinc-400">Costo Inicial:</span>
                                    <span class="font-bold text-red-600 dark:text-rose-400">{{ formatCurrency(totalInitialCost) }}</span>
                                </div>
                                <div class="text-sm flex justify-between mb-1">
                                    <span class="text-gray-600 dark:text-zinc-400">Costos Únicos:</span>
                                    <span class="font-bold text-red-600 dark:text-rose-400">{{ formatCurrency(totalUniqueCosts) }}</span>
                                </div>
                                <div class="text-sm flex justify-between pb-1 mb-1 border-b border-gray-200 dark:border-zinc-800">
                                    <span class="text-gray-600 dark:text-zinc-400">Anuales:</span>
                                    <span class="font-bold text-red-600 dark:text-rose-400">{{ formatCurrency(totalAnnualCosts) }}</span>
                                </div>
                                <div class="text-sm flex justify-between text-gray-800 dark:text-gray-200">
                                    <span class="text-gray-600 dark:text-zinc-400">Mensuales:</span>
                                    <span class="font-bold text-red-600 dark:text-rose-400">{{ formatCurrency(totalMonthlyCosts) }}</span>
                                </div>
                            </div>

                            <!-- Utilidad Mensual Neta -->
                            <div class="bg-white dark:bg-zinc-900 p-4 rounded-md shadow-sm border border-gray-100 dark:border-zinc-800 flex flex-col justify-center">
                                <h4 class="text-xs font-bold uppercase text-gray-500 dark:text-zinc-500 tracking-wider">Margen Mensual Neto</h4>
                                <div
                                    class="mt-2 text-xl font-black"
                                    :class="monthlyNetMargin > 0 ? 'text-green-600 dark:text-emerald-400' : (monthlyNetMargin === 0 ? 'text-gray-600 dark:text-zinc-400' : 'text-red-600 dark:text-rose-400')"
                                >
                                    {{ formatCurrency(monthlyNetMargin) }}
                                </div>
                                <div class="text-xs text-gray-400 dark:text-zinc-500 mt-1">Ingresos mensuales menos costos mensuales.</div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Contrato y Renovaciones -->
                    <div class="card bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg border border-gray-100 dark:border-zinc-800 p-6">
                        <div class="border-b border-gray-200 dark:border-zinc-800 pb-4 mb-6">
                            <h3 class="text-lg font-bold text-green-700 dark:text-emerald-400">2. Servicios y Renovaciones</h3>
                            <p class="text-sm text-gray-500 dark:text-zinc-400 mt-1">Gestiona los servicios y fechas de pago del cliente.</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div>
                                <InputLabel for="contract_date" value="Fecha de Contratación" class="font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput
                                    id="contract_date"
                                    type="date"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md shadow-sm dark:bg-zinc-950 dark:text-gray-100 placeholder-gray-400"
                                    v-model="form.contract_date"
                                />
                                <InputError class="mt-2" :message="form.errors.contract_date" />
                            </div>

                        </div>

                        <div class="mt-6">
                            <div class="flex items-center justify-between mb-2">
                                <InputLabel value="Servicios Contratados" class="font-bold text-gray-700 dark:text-gray-300" />
                                <div class="flex items-center gap-2">
                                    <button 
                                        type="button" 
                                        @click="addManualService"
                                        class="text-xs font-bold py-1.5 px-3 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition"
                                    >
                                        + Manual
                                    </button>
                                    <select 
                                        v-model="selectedServiceId"
                                        class="border-gray-300 dark:border-zinc-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm py-1 pl-2 pr-8 text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-zinc-950 transition-colors"
                                        @change="addServiceToPackage"
                                    >
                                        <option value="" disabled selected>Añadir del catálogo...</option>
                                        <option v-for="s in services" :key="s.id" :value="s.id">
                                            {{ s.is_package ? '📦 ' + s.name : s.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <!-- Listado de servicios con fechas individuales -->
                            <div v-if="form.services.length > 0" class="mt-4 space-y-3">
                                <p class="text-sm font-bold text-gray-600 dark:text-zinc-400">Desglose de Servicios y Renovaciones Individuales:</p>
                                <div v-for="(s, index) in form.services" :key="index" class="p-3 bg-gray-50 dark:bg-zinc-950/50 border border-gray-200 dark:border-zinc-800 rounded-lg space-y-2">
                                    <!-- Row 1: nombre, monto recurrente, fecha, tipo -->
                                    <div class="flex flex-wrap items-end gap-3">
                                        <div class="flex-1 min-w-[200px]">
                                            <InputLabel :value="'Servicio ' + (index + 1)" class="text-xs" />
                                            <TextInput type="text" v-model="s.service_name" class="w-full text-sm py-1" />
                                        </div>
                                        <div class="w-32">
                                            <InputLabel value="Monto recurrente ($)" class="text-xs" />
                                            <TextInput type="number" step="0.01" v-model="s.renewal_amount" class="w-full text-sm py-1" />
                                        </div>
                                        <div class="w-40">
                                            <InputLabel :value="s.billing_type === 'once' ? 'Fecha de Pago' : 'Fecha Renovación'" class="text-xs" />
                                            <TextInput type="date" v-model="s.renewal_date" class="w-full text-sm py-1" />
                                        </div>
                                        <div class="w-32">
                                            <InputLabel value="Tipo Pago" class="text-xs" />
                                            <select v-model="s.billing_type" class="w-full text-sm py-1 border-gray-300 dark:border-zinc-800 rounded-md bg-white dark:bg-zinc-950">
                                                <option value="once">Único</option>
                                                <option value="monthly">Mensual</option>
                                                <option value="annual">Anual</option>
                                            </select>
                                        </div>
                                        <button type="button" @click="removeService(index)" class="p-2 text-red-500 hover:text-red-700 transition-colors">
                                            <TrashIcon class="h-5 w-5" />
                                        </button>
                                    </div>
                                    <!-- Row 2: pago inicial y costo inicial -->
                                    <div class="flex flex-wrap items-end gap-3 pt-2 border-t border-gray-200 dark:border-zinc-700">
                                        <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400 self-center">Financiero inicial:</div>
                                        <div class="w-44">
                                            <InputLabel value="Pago Inicial del cliente ($)" class="text-xs text-green-700 dark:text-emerald-400 font-semibold" />
                                            <TextInput type="number" step="0.01" min="0" v-model="s.initial_payment" class="w-full text-sm py-1 border-green-300 dark:border-emerald-800 focus:border-green-500" placeholder="0.00" />
                                        </div>
                                        <div class="w-44">
                                            <InputLabel value="Costo Inicial interno ($)" class="text-xs text-red-600 dark:text-rose-400 font-semibold" />
                                            <TextInput type="number" step="0.01" min="0" v-model="s.initial_cost" class="w-full text-sm py-1 border-red-300 dark:border-rose-800 focus:border-red-500" placeholder="0.00" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Activos Técnicos -->
                    <div class="card bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg border border-gray-100 dark:border-zinc-800 p-6">
                        <div class="border-b border-gray-200 dark:border-zinc-800 pb-4 mb-6">
                            <h3 class="text-lg font-bold text-purple-700 dark:text-indigo-400">3. Activos Técnicos y Accesos</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div class="md:col-span-2">
                                <InputLabel for="domain_names" value="Dominio(s) Principal(es)" class="font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput
                                    id="domain_names"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 font-mono text-sm focus:border-indigo-500 rounded-md"
                                    v-model="form.domain_names"
                                />
                                <InputError class="mt-2" :message="form.errors.domain_names" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel for="domain_provider" value="Proveedor Dominio" class="font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput
                                    id="domain_provider"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 focus:border-indigo-500 rounded-md"
                                    v-model="form.domain_provider"
                                />
                                <InputError class="mt-2" :message="form.errors.domain_provider" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel for="hosting_provider" value="Proveedor Hosting" class="font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput
                                    id="hosting_provider"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 focus:border-indigo-500 rounded-md"
                                    v-model="form.hosting_provider"
                                />
                                <InputError class="mt-2" :message="form.errors.hosting_provider" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel for="email_provider" value="Proveedor de Correos (Servidor)" class="font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput
                                    id="email_provider"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 focus:border-indigo-500 rounded-md"
                                    v-model="form.email_provider"
                                />
                                <InputError class="mt-2" :message="form.errors.email_provider" />
                            </div>
                        </div>

                        <!-- Checkbox para mostrar configuración manual -->
                        <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-900 rounded-lg">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.has_custom_email_config" class="rounded border-gray-300 dark:border-zinc-800 text-[#264ab3] dark:text-blue-500 shadow-sm focus:ring-[#264ab3] h-5 w-5 dark:bg-zinc-950">
                                <div class="ml-3">
                                    <span class="block text-sm font-bold text-[#264ab3] dark:text-blue-400">¿Utiliza correos del servidor corporativo? (Configuración Manual)</span>
                                    <span class="block text-xs text-blue-700 dark:text-blue-300/70">Activa esta opción si necesitas configurar IMAP/POP/SMTP manualmente para manuales de configuración.</span>
                                </div>
                            </label>
                        </div>

                        <!-- Boveda de Contraseñas Estructurada (NUEVA) -->
                        <div class="mt-6 p-4 bg-indigo-50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900 rounded-lg">
                            <div class="flex justify-between items-center mb-4">
                                <div class="flex items-center">
                                    <UserIcon class="h-5 w-5 mr-2 text-indigo-600 dark:text-indigo-400" />
                                    <InputLabel value="Bóveda de Accesos Estructurada" class="font-bold text-indigo-900 dark:text-indigo-300" />
                                </div>
                                <button type="button" @click="addVaultCredential" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold py-1.5 px-3 rounded shadow transition">
                                    + Agregar Acceso
                                </button>
                            </div>
                            
                            <div v-if="form.vault_credentials.length === 0" class="text-sm text-gray-500 dark:text-zinc-500 italic text-center py-4 border-2 border-dashed border-indigo-200 dark:border-indigo-900 rounded mb-2 bg-white dark:bg-zinc-950/50">
                                No hay accesos estructurados. Presiona "+ Agregar Acceso" para comenzar.
                            </div>

                            <div v-else class="overflow-x-auto mt-4 border border-indigo-200 dark:border-indigo-800 rounded-lg shadow-sm">
                                <table class="min-w-full divide-y divide-indigo-200 dark:divide-indigo-800 bg-white dark:bg-zinc-950">
                                    <thead class="bg-indigo-50 dark:bg-indigo-900/30">
                                        <tr>
                                            <th scope="col" class="px-3 py-2.5 text-left text-xs font-bold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider border-r border-indigo-100 dark:border-indigo-800 w-[20%]">Plataforma</th>
                                            <th scope="col" class="px-3 py-2.5 text-left text-xs font-bold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider border-r border-indigo-100 dark:border-indigo-800 w-[20%]">Usuario</th>
                                            <th scope="col" class="px-3 py-2.5 text-left text-xs font-bold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider border-r border-indigo-100 dark:border-indigo-800 w-[20%]">Contraseña</th>
                                            <th scope="col" class="px-3 py-2.5 text-left text-xs font-bold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider border-r border-indigo-100 dark:border-indigo-800 w-[35%]">URL / Notas</th>
                                            <th scope="col" class="px-2 py-2.5 w-[5%] text-center text-xs font-medium text-gray-500 uppercase tracking-wider relative bg-indigo-50 dark:bg-indigo-900/30">
                                                <div v-if="!showCredentials" class="absolute inset-0 bg-indigo-50/80 dark:bg-indigo-900/50 backdrop-blur-[1px] flex items-center justify-center z-10" title="Desbloquea la bóveda para editar">🔒</div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-zinc-950 divide-y divide-indigo-100 dark:divide-indigo-800 transition-all duration-300" :class="{'opacity-50 blur-[4px] pointer-events-none select-none': !showCredentials}">
                                        <tr v-for="(vault, index) in form.vault_credentials" :key="index" class="hover:bg-indigo-50/30 dark:hover:bg-indigo-900/20 transition-colors group">
                                            <td class="border-r border-indigo-100 dark:border-indigo-800 p-0">
                                                <input type="text" v-model="vault.service" class="w-full border-0 focus:ring-2 focus:ring-inset focus:ring-indigo-500 text-sm bg-transparent px-3 py-2.5 placeholder-gray-300 dark:placeholder-zinc-600 dark:text-gray-100 font-bold transition-colors" placeholder="Ej: WordPress">
                                            </td>
                                            <td class="border-r border-indigo-100 dark:border-indigo-800 p-0">
                                                <input type="text" v-model="vault.username" class="w-full border-0 focus:ring-2 focus:ring-inset focus:ring-indigo-500 text-sm bg-transparent px-3 py-2.5 placeholder-gray-300 dark:placeholder-zinc-600 dark:text-gray-100 transition-colors" placeholder="Usuario">
                                            </td>
                                            <td class="border-r border-indigo-100 dark:border-indigo-800 p-0">
                                                <input type="text" v-model="vault.password" class="w-full border-0 focus:ring-2 focus:ring-inset focus:ring-indigo-500 text-sm bg-transparent px-3 py-2.5 font-mono placeholder-gray-300 dark:placeholder-zinc-600 dark:text-gray-100 transition-colors" placeholder="Contraseña">
                                            </td>
                                            <td class="border-r border-indigo-100 dark:border-indigo-800 p-0">
                                                <input type="text" v-model="vault.notes" class="w-full border-0 focus:ring-2 focus:ring-inset focus:ring-indigo-500 text-sm bg-transparent px-3 py-2.5 placeholder-gray-300 dark:placeholder-zinc-600 dark:text-gray-100 transition-colors" placeholder="URL o notas adicionales">
                                            </td>
                                            <td class="p-0 text-center whitespace-nowrap align-middle bg-indigo-50/20 dark:bg-indigo-900/10 group-hover:bg-red-50/30 dark:group-hover:bg-rose-900/30 transition-colors">
                                                <button type="button" @click="removeVaultCredential(index)" class="text-gray-400 hover:text-red-600 dark:hover:text-rose-400 transition p-2 inline-flex items-center justify-center rounded-full w-full h-full" title="Eliminar fila">
                                                    <TrashIcon class="h-4 w-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Boveda de Contraseñas (Legacy / Notas rápidas) -->
                        <div class="mt-6 p-4 bg-gray-50 dark:bg-zinc-950/50 border border-gray-200 dark:border-zinc-800 rounded-lg">
                            <div class="flex justify-between items-center mb-2">
                                <InputLabel for="login_credentials" value="Bóveda en Bloque (Texto libre)" class="font-bold text-gray-800 dark:text-gray-200" />
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
                                class="mt-1 block w-full border-gray-300 dark:border-zinc-800 focus:border-indigo-500 rounded-md shadow-sm font-mono text-sm transition-all"
                                :class="{'text-transparent bg-gray-200 dark:bg-zinc-900 select-none' : !showCredentials, 'bg-white dark:bg-zinc-950 text-gray-800 dark:text-gray-100' : showCredentials}"
                                v-model="form.login_credentials"
                                rows="5"
                                placeholder="Escribe aquí los usuarios y contraseñas..."
                            ></textarea>
                            <div v-if="!showCredentials && form.login_credentials" class="text-xs text-gray-500 dark:text-zinc-500 mt-1 italic">Presiona "Mostrar Contraseñas" para ver el contenido.</div>
                        </div>

                        <!-- Boveda de Correos Institucionales -->
                        <div class="mt-6 p-4 bg-gray-50 dark:bg-zinc-950/50 border border-gray-200 dark:border-zinc-800 rounded-lg">
                            <div class="flex justify-between items-center mb-4">
                                <InputLabel value="Cuentas de Correo Institucional" class="font-bold text-gray-800 dark:text-gray-200" />
                                <button type="button" @click="addEmailAccount" class="bg-[#264ab3] hover:bg-[#193074] text-white text-xs font-bold py-1.5 px-3 rounded shadow transition">
                                    + Agregar Correo
                                </button>
                            </div>
                            
                            <div v-if="form.email_accounts.length === 0" class="text-sm text-gray-500 dark:text-zinc-500 italic text-center py-4 border-2 border-dashed border-gray-300 dark:border-zinc-800 rounded mb-2 bg-white dark:bg-zinc-950/50">
                                No hay correos registrados. Presiona "+ Agregar Correo" para comenzar.
                            </div>

                            <div v-else class="overflow-x-auto mt-4 border border-gray-200 dark:border-zinc-800 rounded-lg shadow-sm">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800 bg-white dark:bg-zinc-950">
                                    <thead class="bg-gray-100 dark:bg-zinc-900">
                                        <tr>
                                            <th scope="col" class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 dark:text-zinc-400 uppercase tracking-wider border-r border-gray-200 dark:border-zinc-800 w-[30%]">
                                                Dirección de correo
                                            </th>
                                            <th scope="col" class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 dark:text-zinc-400 uppercase tracking-wider border-r border-gray-200 dark:border-zinc-800 w-[25%]">
                                                Contraseña
                                            </th>
                                            <th scope="col" class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 dark:text-zinc-400 uppercase tracking-wider border-r border-gray-200 dark:border-zinc-800 w-[20%]">
                                                Usuario <span class="text-gray-400 dark:text-zinc-500 font-normal normal-case">(Opcional)</span>
                                            </th>
                                            <th scope="col" class="px-3 py-2.5 text-left text-xs font-bold text-gray-600 dark:text-zinc-400 uppercase tracking-wider border-r border-gray-200 dark:border-zinc-800 w-[20%]">
                                                Teléfono <span class="text-gray-400 dark:text-zinc-500 font-normal normal-case">(Opcional)</span>
                                            </th>
                                            <th scope="col" class="px-2 py-2.5 w-[5%] text-center text-xs font-medium text-gray-500 uppercase tracking-wider relative bg-gray-50 dark:bg-zinc-900">
                                                <div v-if="!showCredentials" class="absolute inset-0 bg-gray-100 dark:bg-zinc-900 flex items-center justify-center z-10" title="Desbloquea la bóveda para editar">🔒</div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-zinc-950 divide-y divide-gray-200 dark:divide-zinc-800 transition-all duration-300" :class="{'opacity-50 blur-[4px] pointer-events-none select-none': !showCredentials}">
                                        <tr v-for="(account, index) in form.email_accounts" :key="index" class="hover:bg-blue-50/50 dark:hover:bg-blue-900/20 transition-colors group">
                                            <td class="border-r border-gray-200 dark:border-zinc-800 p-0 relative">
                                                <input type="email" v-model="account.email" class="w-full border-0 focus:ring-2 focus:ring-inset focus:ring-[#264ab3] text-sm bg-transparent px-3 py-2.5 placeholder-gray-300 dark:placeholder-zinc-600 dark:text-gray-100 transition-colors" placeholder="ejemplo@dominio.com" required>
                                            </td>
                                            <td class="border-r border-gray-200 dark:border-zinc-800 p-0 relative">
                                                <input type="text" v-model="account.password" class="w-full border-0 focus:ring-2 focus:ring-inset focus:ring-[#264ab3] text-sm bg-transparent px-3 py-2.5 font-mono placeholder-gray-300 dark:placeholder-zinc-600 dark:text-gray-100 transition-colors" placeholder="Contraseña">
                                            </td>
                                            <td class="border-r border-gray-200 dark:border-zinc-800 p-0 relative">
                                                <input type="text" v-model="account.username" class="w-full border-0 focus:ring-2 focus:ring-inset focus:ring-[#264ab3] text-sm bg-transparent px-3 py-2.5 placeholder-gray-300 dark:placeholder-zinc-600 dark:text-gray-100 transition-colors" placeholder="Usuario">
                                            </td>
                                            <td class="border-r border-gray-200 dark:border-zinc-800 p-0 relative">
                                                <input type="text" v-model="account.phone" class="w-full border-0 focus:ring-2 focus:ring-inset focus:ring-[#264ab3] text-sm bg-transparent px-3 py-2.5 placeholder-gray-300 dark:placeholder-zinc-600 dark:text-gray-100 transition-colors" placeholder="Teléfono">
                                            </td>
                                            <td class="p-0 text-center whitespace-nowrap align-middle bg-gray-50 dark:bg-zinc-900 group-hover:bg-red-50/30 dark:group-hover:bg-rose-900/30 transition-colors">
                                                <button type="button" @click="removeEmailAccount(index)" class="text-gray-400 hover:text-red-600 dark:hover:text-rose-400 transition p-2 inline-flex items-center justify-center rounded-full w-full h-full" title="Eliminar fila">
                                                    <TrashIcon class="h-4 w-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <InputLabel for="notes" value="Otras Notas" class="font-bold text-gray-700 dark:text-gray-300" />
                            <textarea
                                id="notes"
                                class="mt-1 block w-full border-gray-300 dark:border-zinc-800 focus:border-indigo-500 rounded-md shadow-sm dark:bg-zinc-950 dark:text-gray-100 placeholder-gray-400 dark:placeholder-zinc-500 transition-colors"
                                v-model="form.notes"
                                rows="2"
                                placeholder="Cualquier otra información útil..."
                            ></textarea>
                            <InputError class="mt-2" :message="form.errors.notes" />
                        </div>
                    </div>

                    <!-- 4. Configuración de Correo (IMAP/POP/SMTP) -->
                    <div v-if="form.has_custom_email_config" class="card bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg border-l-4 border-blue-600 dark:border-blue-500 transition-all p-6">
                        <div class="border-b border-gray-200 dark:border-zinc-800 pb-4 mb-6">
                            <h3 class="text-lg font-bold text-blue-600 dark:text-blue-400">4. Configuración de Servidores de Correo</h3>
                            <p class="text-sm text-gray-500 dark:text-zinc-400 mt-1">Datos para el manual de configuración de correos del cliente.</p>
                        </div>

                        <div class="space-y-8">
                            <!-- IMAP -->
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                                <div class="md:col-span-2">
                                    <InputLabel for="imap_host" value="Servidor Entrante (IMAP)" class="font-bold text-gray-700 dark:text-gray-300" />
                                    <TextInput id="imap_host" type="text" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md" v-model="form.imap_host" placeholder="imap.ejemplo.com" />
                                </div>
                                <div>
                                    <InputLabel for="imap_port" value="Puerto IMAP" class="font-bold text-gray-700 dark:text-gray-300" />
                                    <TextInput id="imap_port" type="text" class="mt-1 block w-full border-gray-300 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 focus:border-indigo-500 rounded-md transition-colors" v-model="form.imap_port" placeholder="993" />
                                </div>
                                <div class="flex items-center pb-3">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" v-model="form.imap_tls" class="rounded border-gray-300 dark:border-zinc-800 dark:bg-zinc-950 text-blue-600 shadow-sm focus:ring-blue-500 h-5 w-5 transition-colors">
                                        <span class="ml-2 text-sm font-bold text-gray-700 dark:text-gray-300">Usar SSL/TLS</span>
                                    </label>
                                </div>
                            </div>

                            <!-- SMTP -->
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                                <div class="md:col-span-2">
                                    <InputLabel for="smtp_host" value="Servidor Saliente (SMTP)" class="font-bold text-gray-700 dark:text-gray-300" />
                                    <TextInput id="smtp_host" type="text" class="mt-1 block w-full border-gray-300 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 focus:border-indigo-500 rounded-md transition-colors" v-model="form.smtp_host" placeholder="smtp.ejemplo.com" />
                                </div>
                                <div>
                                    <InputLabel for="smtp_port" value="Puerto SMTP" class="font-bold text-gray-700 dark:text-gray-300" />
                                    <TextInput id="smtp_port" type="text" class="mt-1 block w-full border-gray-300 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 focus:border-indigo-500 rounded-md transition-colors" v-model="form.smtp_port" placeholder="465" />
                                </div>
                                <div class="flex items-center pb-3">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" v-model="form.smtp_tls" class="rounded border-gray-300 dark:border-zinc-800 dark:bg-zinc-950 text-blue-600 shadow-sm focus:ring-blue-500 h-5 w-5 transition-colors">
                                        <span class="ml-2 text-sm font-bold text-gray-700 dark:text-gray-300">Usar SSL/TLS</span>
                                    </label>
                                </div>
                            </div>

                            <!-- POP3 -->
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                                <div class="md:col-span-2">
                                    <div class="flex items-center">
                                        <InputLabel for="pop_host" value="Servidor Entrante (POP3)" class="font-bold text-gray-700 dark:text-gray-300" />
                                        <span class="ml-2 text-[10px] bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-zinc-400 px-1.5 py-0.5 rounded font-bold uppercase">No recomendado</span>
                                    </div>
                                    <TextInput id="pop_host" type="text" class="mt-1 block w-full border-gray-300 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 focus:border-indigo-500 rounded-md transition-colors" v-model="form.pop_host" placeholder="pop.ejemplo.com" />
                                </div>
                                <div>
                                    <InputLabel for="pop_port" value="Puerto POP3" class="font-bold text-gray-700 dark:text-gray-300" />
                                    <TextInput id="pop_port" type="text" class="mt-1 block w-full border-gray-300 dark:border-zinc-800 dark:bg-zinc-950 dark:text-gray-100 focus:border-indigo-500 rounded-md transition-colors" v-model="form.pop_port" placeholder="995" />
                                </div>
                                <div class="flex items-center pb-3">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" v-model="form.pop_tls" class="rounded border-gray-300 dark:border-zinc-800 dark:bg-zinc-950 text-blue-600 shadow-sm focus:ring-blue-500 h-5 w-5 transition-colors">
                                        <span class="ml-2 text-sm font-bold text-gray-700 dark:text-gray-300">Usar SSL/TLS</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Archivos Adjuntos -->
                    <div class="card bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg border border-gray-100 dark:border-zinc-800 mb-6">
                        <div class="border-b border-gray-200 dark:border-zinc-800 p-6 pb-4 mb-6">
                            <h3 class="text-lg font-bold text-gray-700 dark:text-gray-100">5. Documentos y Archivos</h3>
                            <p class="text-sm text-gray-500 dark:text-zinc-400 mt-1">Sube nuevos documentos si deseas reemplazar los actuales.</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 p-6 pt-0">
                            <div>
                                <InputLabel for="quote_file" value="Cotización Final" class="font-bold text-gray-700 dark:text-gray-300" />
                                <div v-if="client.quote_file_path" class="mb-2 text-xs text-blue-600 dark:text-blue-400">
                                    <a :href="'/storage/' + client.quote_file_path" target="_blank" class="hover:underline">Ver archivo actual</a>
                                </div>
                                <input type="file" @input="form.quote_file = $event.target.files[0]" class="mt-1 block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-100 dark:file:bg-zinc-950 file:text-gray-700 dark:file:text-gray-300 hover:file:bg-gray-200 dark:hover:file:bg-zinc-800 transition-colors" />
                                <InputError class="mt-2" :message="form.errors.quote_file" />
                            </div>
                            <div>
                                <InputLabel for="contract_file" value="Contrato de Servicios" class="font-bold text-gray-700 dark:text-gray-300" />
                                <div v-if="client.contract_file_path" class="mb-2 text-xs text-blue-600 dark:text-blue-400">
                                    <a :href="'/storage/' + client.contract_file_path" target="_blank" class="hover:underline">Ver archivo actual</a>
                                </div>
                                <input type="file" @input="form.contract_file = $event.target.files[0]" class="mt-1 block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-100 dark:file:bg-zinc-950 file:text-gray-700 dark:file:text-gray-300 hover:file:bg-gray-200 dark:hover:file:bg-zinc-800 transition-colors" />
                                <InputError class="mt-2" :message="form.errors.contract_file" />
                            </div>
                            <div>
                                <InputLabel for="branding_file" value="Logo / Manual (ZIP/PDF)" class="font-bold text-gray-700 dark:text-gray-300" />
                                <div v-if="client.branding_file_path" class="mb-2 text-xs text-blue-600 dark:text-blue-400">
                                    <a :href="'/storage/' + client.branding_file_path" target="_blank" class="hover:underline">Ver archivo actual</a>
                                </div>
                                <input type="file" @input="form.branding_file = $event.target.files[0]" class="mt-1 block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-100 dark:file:bg-zinc-950 file:text-gray-700 dark:file:text-gray-300 hover:file:bg-gray-200 dark:hover:file:bg-zinc-800 transition-colors" />
                                <InputError class="mt-2" :message="form.errors.branding_file" />
                            </div>
                            <div>
                                <InputLabel for="receipt_file" value="Recibo de Pago" class="font-bold text-gray-700 dark:text-gray-300" />
                                <div v-if="client.receipt_file_path" class="mb-2 text-xs text-blue-600 dark:text-blue-400">
                                    <a :href="'/storage/' + client.receipt_file_path" target="_blank" class="hover:underline">Ver archivo actual</a>
                                </div>
                                <input type="file" @input="form.receipt_file = $event.target.files[0]" class="mt-1 block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-100 dark:file:bg-zinc-950 file:text-gray-700 dark:file:text-gray-300 hover:file:bg-gray-200 dark:hover:file:bg-zinc-800 transition-colors" />
                                <InputError class="mt-2" :message="form.errors.receipt_file" />
                            </div>
                        </div>
                    </div>

                    <!-- 6. Portal del Cliente -->
                    <div v-if="!client.user_id" class="card bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg border-l-4 border-blue-500 p-6">
                        <div class="border-b border-gray-200 dark:border-zinc-800 pb-4 mb-6">
                            <h3 class="text-lg font-bold text-blue-800 dark:text-blue-400">6. Accesos para el Portal de Cliente</h3>
                            <p class="text-sm text-gray-500 dark:text-zinc-400 mt-1">Este cliente actualmente no tiene una cuenta de inicio de sesión vinculada. Si deseas que pueda entrar para ver sus tickets, crea su acceso aquí mismo.</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-0">
                            <div>
                                <InputLabel for="login_email" value="Correo de Acceso (Email del Sistema)" class="font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput
                                    id="login_email"
                                    type="email"
                                    class="mt-1 block w-full border-gray-300 dark:border-zinc-800 focus:border-indigo-500 rounded-md bg-blue-50 dark:bg-zinc-950 dark:text-gray-100 transition-colors"
                                    v-model="form.login_email"
                                    autocomplete="off"
                                />
                                <InputError class="mt-2" :message="form.errors.login_email" />
                            </div>

                            <div>
                                <InputLabel for="login_password" value="Contraseña de Acceso" class="font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput
                                    id="login_password"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 dark:border-zinc-800 focus:border-indigo-500 rounded-md bg-blue-50 dark:bg-zinc-950 dark:text-gray-100 transition-colors"
                                    v-model="form.login_password"
                                    placeholder="Mínimo 6 caracteres"
                                    autocomplete="new-password"
                                />
                                <InputError class="mt-2" :message="form.errors.login_password" />
                            </div>
                        </div>
                    </div>
                    <!-- Si ya lo tiene -->
                    <div v-else class="card bg-gray-50 dark:bg-zinc-950/50 shadow-sm sm:rounded-lg border border-gray-200 dark:border-zinc-800 p-6 flex items-center justify-between">
                        <div>
                            <h3 class="text-md font-bold text-green-700 dark:text-emerald-400">Cuenta de Portal Vinculada</h3>
                            <p class="text-xs text-gray-500 dark:text-zinc-500">Este cliente ya posee credenciales y está enlazado a la plataforma para el sistema de tickets.</p>
                        </div>
                        <div class="text-2xl">✅</div>
                    </div>

                    <div class="flex justify-end pt-4 space-x-4">
                        <Link
                            :href="route('clients.index')"
                            class="bg-white dark:bg-zinc-900 border border-gray-300 dark:border-zinc-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-zinc-800 font-bold py-3 px-6 rounded shadow-sm transition"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            class="btn bg-[#264ab3] hover:bg-[#193074] text-white font-bold py-3 px-8 rounded shadow-lg transition"
                            :class="{ 'opacity-50': form.processing }"
                            :disabled="form.processing"
                        >
                            Actualizar Cliente
                        </button>
                    </div>

                </form>
            </div>
        </div>

        <!-- MODAL CONTRASEÑA -->
        <Modal :show="confirmingPassword" @close="closeModal">
            <div class="p-6 dark:bg-zinc-900">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Confirmación de Seguridad
                </h2>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
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
