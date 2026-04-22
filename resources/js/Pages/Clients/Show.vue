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
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100 flex items-center">
                    <Link :href="route('clients.index')" class="mr-3 text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-gray-200 transition">
                        <ArrowLeftIcon class="h-5 w-5 inline-block" />
                    </Link>
                    <span>Expediente del Cliente:</span> 
                    <span class="ml-2 text-[#264ab3] dark:text-blue-400">{{ client.business_name }}</span>
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
                <div class="card bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg overflow-hidden border border-gray-100 dark:border-zinc-800">
                    <div class="border-b border-gray-100 dark:border-zinc-800 p-6 bg-blue-50/50 dark:bg-blue-900/10 flex items-center">
                        <ServerStackIcon class="h-6 w-6 mr-2 text-blue-600 dark:text-blue-400" />
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Activos Técnicos</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 text-gray-700 dark:text-gray-300 text-sm">
                            <div class="bg-gray-50 dark:bg-zinc-950 p-4 rounded-lg border border-gray-100 dark:border-zinc-800">
                                <strong class="text-blue-900 dark:text-blue-300 block mb-1">Dominio(s):</strong> 
                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ client.domain_names || 'N/A' }}</span>
                            </div>
                            <div class="bg-gray-50 dark:bg-zinc-950 p-4 rounded-lg border border-gray-100 dark:border-zinc-800">
                                <strong class="text-blue-900 dark:text-blue-300 block mb-1">Prov. Dominio:</strong> 
                                <span class="text-gray-600 dark:text-zinc-400">{{ client.domain_provider || 'N/A' }}</span>
                            </div>
                            <div class="bg-gray-50 dark:bg-zinc-950 p-4 rounded-lg border border-gray-100 dark:border-zinc-800">
                                <strong class="text-blue-900 dark:text-blue-300 block mb-1">Prov. Hosting:</strong> 
                                <span class="text-gray-600 dark:text-zinc-400">{{ client.hosting_provider || 'N/A' }}</span>
                            </div>
                            <div class="bg-gray-50 dark:bg-zinc-950 p-4 rounded-lg border border-gray-100 dark:border-zinc-800">
                                <strong class="text-blue-900 dark:text-blue-300 block mb-1">Prov. Correos:</strong> 
                                <span class="text-gray-600 dark:text-zinc-400">{{ client.email_provider || 'N/A' }}</span>
                            </div>
                        </div>
                        
                        <div class="mt-8 pt-6 border-t border-gray-100 dark:border-zinc-800">
                            <div class="flex justify-between items-center mb-4">
                                <div class="flex items-center">
                                    <ServerStackIcon class="h-6 w-6 mr-2 text-blue-600 dark:text-blue-400" />
                                    <strong class="text-blue-900 dark:text-blue-400 text-lg">Bóveda de Accesos / Notas</strong>
                                </div>
                                <button type="button" @click="toggleClientVault" class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 flex items-center bg-blue-50 dark:bg-blue-900/20 px-3 py-1.5 rounded-lg border border-blue-100 dark:border-blue-900/30 transition-colors">
                                    <template v-if="!showClientVault">
                                        <EyeIcon class="h-4 w-4 mr-1.5" /> Desbloquear Bóveda
                                    </template>
                                    <template v-else>
                                        <EyeSlashIcon class="h-4 w-4 mr-1.5" /> Ocultar Bóveda
                                    </template>
                                </button>
                            </div>
                            
                            <!-- Contenido de la Bóveda -->
                            <div v-if="showClientVault" class="mt-4 space-y-6">
                                <!-- 1. Bóveda Estructurada -->
                                <div v-if="client.vault_credentials?.length > 0" class="mb-4">
                                    <h4 class="text-sm font-bold text-indigo-500 dark:text-indigo-400 uppercase tracking-widest mb-3 flex items-center">
                                        <span class="w-2 h-2 bg-indigo-500 dark:bg-indigo-400 rounded-full mr-2"></span>
                                        Accesos Directos
                                    </h4>
                                    <div class="overflow-x-auto border border-indigo-100 dark:border-indigo-900/30 rounded-2xl shadow-sm">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800">
                                            <thead class="bg-indigo-50 dark:bg-indigo-900/20">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-indigo-400 uppercase tracking-wider">Servicio</th>
                                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-indigo-400 uppercase tracking-wider">Usuario</th>
                                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-indigo-400 uppercase tracking-wider">Contraseña</th>
                                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-indigo-400 uppercase tracking-wider">Notas / URL</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-zinc-900 divide-y divide-gray-100 dark:divide-zinc-800">
                                                <tr v-for="(vault, idx) in client.vault_credentials" :key="idx" class="hover:bg-indigo-50/30 dark:hover:bg-indigo-500/10 transition-colors">
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-gray-100">{{ vault.service || vault.platform }}</td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">{{ vault.username || vault.user }}</td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-mono text-indigo-600 dark:text-indigo-400 font-bold">{{ vault.password }}</td>
                                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-zinc-400 italic">{{ vault.notes || vault.url || '-' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- 2. Bóveda en Bloque / Texto (Textarea) -->
                                <div class="shadow-md rounded-2xl overflow-hidden border border-gray-200 dark:border-zinc-800">
                                    <div class="bg-gray-800 dark:bg-zinc-950 px-4 py-2 border-b border-gray-700 dark:border-zinc-900 text-[10px] font-bold text-gray-400 dark:text-zinc-500 uppercase tracking-widest flex justify-between">
                                        <span>Bóveda de Texto Libre</span>
                                        <span v-if="!client.login_credentials" class="text-red-400 dark:text-rose-500 italic normal-case font-normal">(Vacía)</span>
                                    </div>
                                    <textarea 
                                        readonly 
                                        class="w-full bg-[#1a1b1e] text-emerald-400 p-6 text-sm font-mono border-0 focus:ring-0 resize-none block" 
                                        rows="10"
                                        placeholder="No hay credenciales de texto registradas..."
                                    >{{ client.login_credentials }}</textarea>
                                </div>
                            </div>

                            <!-- Estado Bloqueado -->
                            <div v-else class="mt-4">
                                <div class="bg-gray-100 dark:bg-zinc-950/50 rounded-2xl p-10 flex flex-col items-center justify-center border-2 border-dashed border-gray-200 dark:border-zinc-800">
                                    <div class="bg-white dark:bg-zinc-900 p-4 rounded-full shadow-sm mb-4">
                                        <EyeSlashIcon class="h-8 w-8 text-gray-400 dark:text-zinc-500" />
                                    </div>
                                    <p class="text-gray-500 dark:text-zinc-400 font-medium">La bóveda se encuentra bloqueada.</p>
                                    <p class="text-gray-400 dark:text-zinc-600 text-xs mt-1">Presiona "Desbloquear Bóveda" para visualizar los accesos.</p>
                                </div>
                            </div>
                            
                            <div class="mt-6 bg-yellow-50/80 dark:bg-yellow-950/20 p-5 rounded-xl text-sm border border-yellow-200/60 dark:border-yellow-900/30 shadow-sm" v-if="client.notes">
                                <strong class="text-yellow-800 dark:text-yellow-400 text-base mb-2 block font-bold font-heading">Notas Generales:</strong>
                                <div class="text-yellow-700 dark:text-yellow-500/80 whitespace-pre-wrap">{{ client.notes }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Briefing Creativo -->
                <div class="card bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg overflow-hidden border border-gray-100 dark:border-zinc-800">
                    <div class="border-b border-gray-100 dark:border-zinc-800 p-6 bg-yellow-50/50 dark:bg-yellow-950/10 flex items-center justify-between">
                        <div class="flex items-center">
                            <LightBulbIcon class="h-6 w-6 mr-2 text-yellow-600 dark:text-yellow-400" />
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Briefing Creativo</h3>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2">
                                <h4 class="font-bold text-gray-800 dark:text-gray-200 text-sm">1. Cuéntanos sobre tu empresa (contexto, giro, trayectoria)</h4>
                                <div class="bg-gray-50 dark:bg-zinc-950 p-4 rounded-lg border border-gray-100 dark:border-zinc-800 min-h-[100px] text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                                    {{ client.briefing_context || 'No especificado por el cliente.' }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <h4 class="font-bold text-gray-800 dark:text-gray-200 text-sm">2. ¿A qué tipo de clientes están enfocados tus servicios/productos?</h4>
                                <div class="bg-gray-50 dark:bg-zinc-950 p-4 rounded-lg border border-gray-100 dark:border-zinc-800 min-h-[100px] text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                                    {{ client.briefing_target_audience || 'No especificado por el cliente.' }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <h4 class="font-bold text-gray-800 dark:text-gray-200 text-sm">3. Principales competidores locales/nacionales</h4>
                                <div class="bg-gray-50 dark:bg-zinc-950 p-4 rounded-lg border border-gray-100 dark:border-zinc-800 min-h-[100px] text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                                    {{ client.briefing_competitors || 'No especificado por el cliente.' }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <h4 class="font-bold text-gray-800 dark:text-gray-200 text-sm">4. Sitios web de referencia visual para tu diseño/rediseño</h4>
                                <div class="bg-gray-50 dark:bg-zinc-950 p-4 rounded-lg border border-gray-100 dark:border-zinc-800 min-h-[100px] text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                                    {{ client.briefing_references || 'No especificado por el cliente.' }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <h4 class="font-bold text-gray-800 dark:text-gray-200 text-sm">5. Métodos de contacto que utiliza tu empresa</h4>
                                <div class="bg-gray-50 dark:bg-zinc-950 p-4 rounded-lg border border-gray-100 dark:border-zinc-800 min-h-[100px] text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                                    {{ client.briefing_contact_methods || 'No especificado por el cliente.' }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <h4 class="font-bold text-gray-800 dark:text-gray-200 text-sm">6. Actualmente, ¿cuántos correos empresariales tienes?</h4>
                                <div class="bg-gray-50 dark:bg-zinc-950 p-4 rounded-lg border border-gray-100 dark:border-zinc-800 text-gray-700 dark:text-gray-300">
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
