<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, nextTick } from 'vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { EyeIcon, EyeSlashIcon, ArrowLeftIcon, ServerStackIcon, LightBulbIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    client: Object,
});

const showClientVault = ref(false);
const confirmingPassword = ref(false);
const passwordInput = ref(null);

const confirmForm = useForm({
    password: '',
});

const toggleClientVault = () => {
    if (showClientVault.value) {
        showClientVault.value = false;
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
            showClientVault.value = true;
            closePasswordModal();
        },
        onError: () => passwordInput.value?.focus(),
        onFinish: () => confirmForm.reset(),
    });
};

const closePasswordModal = () => {
    confirmingPassword.value = false;
    confirmForm.clearErrors();
    confirmForm.reset();
};
</script>

<template>
    <Head :title="'Detalles: ' + client.business_name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 flex items-center">
                    <Link :href="route('clients.index')" class="mr-3 text-gray-500 hover:text-gray-700 transition">
                        <ArrowLeftIcon class="h-5 w-5 inline-block" />
                    </Link>
                    <span>Expediente del Cliente:</span> 
                    <span class="ml-2 text-[#264ab3]">{{ client.business_name }}</span>
                </h2>
                <div class="flex space-x-3">
                    <Link
                        v-if="$page.props.auth.user.is_admin || $page.props.auth.user.permissions.includes('Editar Clientes')"
                        :href="route('clients.edit', client.id)"
                        class="btn bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded shadow transition-colors"
                    >
                        Editar Cliente
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="container mx-auto px-4 max-w-6xl space-y-8">

                <!-- 1. Technical Data (Old Lightbox) -->
                <div class="card bg-white shadow-sm sm:rounded-lg overflow-hidden border border-gray-100">
                    <div class="border-b border-gray-100 p-6 bg-blue-50/50 flex items-center">
                        <ServerStackIcon class="h-6 w-6 mr-2 text-blue-600" />
                        <h3 class="text-lg font-bold text-gray-900">Activos Técnicos</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 text-gray-700 text-sm">
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-100">
                                <strong class="text-blue-900 block mb-1">Dominio(s):</strong> 
                                <span class="font-medium text-gray-800">{{ client.domain_names || 'N/A' }}</span>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-100">
                                <strong class="text-blue-900 block mb-1">Prov. Dominio:</strong> 
                                <span class="text-gray-600">{{ client.domain_provider || 'N/A' }}</span>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-100">
                                <strong class="text-blue-900 block mb-1">Prov. Hosting:</strong> 
                                <span class="text-gray-600">{{ client.hosting_provider || 'N/A' }}</span>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-100">
                                <strong class="text-blue-900 block mb-1">Prov. Correos:</strong> 
                                <span class="text-gray-600">{{ client.email_provider || 'N/A' }}</span>
                            </div>
                        </div>
                        
                        <div class="mt-8 pt-6 border-t border-gray-100" v-if="client.login_credentials || client.notes">
                            <div class="flex justify-between items-center mb-3">
                                <strong class="text-blue-900 text-lg">Bóveda de Accesos / Notas</strong>
                                <button v-if="client.login_credentials" type="button" @click="toggleClientVault" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-100 transition-colors">
                                    <template v-if="!showClientVault">
                                        <EyeIcon class="h-4 w-4 mr-1.5" /> Desbloquear Bóveda
                                    </template>
                                    <template v-else>
                                        <EyeSlashIcon class="h-4 w-4 mr-1.5" /> Ocultar Bóveda
                                    </template>
                                </button>
                            </div>
                            
                            <div v-if="client.login_credentials" class="mb-6 shadow-inner rounded-xl overflow-hidden border border-gray-200">
                                <pre v-if="showClientVault" class="bg-gray-900 text-green-400 p-6 text-sm overflow-x-auto whitespace-pre-wrap font-mono">{{ client.login_credentials }}</pre>
                                <pre v-else class="bg-gray-100 text-transparent select-none p-6 text-sm font-mono flex items-center justify-center min-h-[100px]" style="background-image: repeating-linear-gradient(45deg, #f3f4f6 25%, transparent 25%, transparent 75%, #f3f4f6 75%, #f3f4f6), repeating-linear-gradient(45deg, #f3f4f6 25%, #ffffff 25%, #ffffff 75%, #f3f4f6 75%, #f3f4f6); background-position: 0 0, 10px 10px; background-size: 20px 20px;">
                                    <div class="bg-white/90 backdrop-blur-sm px-6 py-3 rounded-full text-gray-500 font-bold border border-gray-200 shadow-sm flex items-center">
                                        <EyeSlashIcon class="h-5 w-5 mr-2" />
                                        Contenido encriptado y bloqueado
                                    </div>
                                </pre>
                            </div>
                            
                            <div class="bg-yellow-50/80 p-5 rounded-xl text-sm border border-yellow-200/60 shadow-sm" v-if="client.notes">
                                <strong class="text-yellow-800 text-base mb-2 block font-bold">Notas Generales:</strong>
                                <p class="text-yellow-700 whitespace-pre-wrap">{{ client.notes }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Briefing Creativo -->
                <div class="card bg-white shadow-sm sm:rounded-lg overflow-hidden border border-gray-100">
                    <div class="border-b border-gray-100 p-6 bg-yellow-50/50 flex items-center justify-between">
                        <div class="flex items-center">
                            <LightBulbIcon class="h-6 w-6 mr-2 text-yellow-600" />
                            <h3 class="text-lg font-bold text-gray-900">Briefing Creativo</h3>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2">
                                <h4 class="font-bold text-gray-800 text-sm">1. Cuéntanos sobre tu empresa (contexto, giro, trayectoria)</h4>
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-100 min-h-[100px] text-gray-700 whitespace-pre-wrap">
                                    {{ client.briefing_context || 'No especificado por el cliente.' }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <h4 class="font-bold text-gray-800 text-sm">2. ¿A qué tipo de clientes están enfocados tus servicios/productos?</h4>
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-100 min-h-[100px] text-gray-700 whitespace-pre-wrap">
                                    {{ client.briefing_target_audience || 'No especificado por el cliente.' }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <h4 class="font-bold text-gray-800 text-sm">3. Principales competidores locales/nacionales</h4>
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-100 min-h-[100px] text-gray-700 whitespace-pre-wrap">
                                    {{ client.briefing_competitors || 'No especificado por el cliente.' }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <h4 class="font-bold text-gray-800 text-sm">4. Sitios web de referencia visual para tu diseño/rediseño</h4>
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-100 min-h-[100px] text-gray-700 whitespace-pre-wrap">
                                    {{ client.briefing_references || 'No especificado por el cliente.' }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <h4 class="font-bold text-gray-800 text-sm">5. Métodos de contacto que utiliza tu empresa</h4>
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-100 min-h-[100px] text-gray-700 whitespace-pre-wrap">
                                    {{ client.briefing_contact_methods || 'No especificado por el cliente.' }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <h4 class="font-bold text-gray-800 text-sm">6. Actualmente, ¿cuántos correos empresariales tienes?</h4>
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-100 text-gray-700">
                                    {{ client.briefing_current_emails || 'No especificado.' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Modal Confirmar Contraseña (sobrepuesto para bóveda) -->
        <Modal :show="confirmingPassword" @close="closePasswordModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-2">
                    Confirmación de Seguridad
                </h2>
                <p class="mt-1 text-sm text-gray-600 mb-6">
                    Por favor, ingresa tu contraseña de administrador para desbloquear la bóveda de accesos del cliente.
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

                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="closePasswordModal">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton
                        class="ms-3"
                        :class="{ 'opacity-25': confirmForm.processing }"
                        :disabled="confirmForm.processing"
                        @click="verifyPassword"
                    >
                        Desbloquear Bóveda
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>
