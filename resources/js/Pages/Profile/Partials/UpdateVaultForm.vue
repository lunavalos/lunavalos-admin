<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';
import { ref, nextTick } from 'vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const user = usePage().props.auth.user;

const form = useForm({
    vault_credentials: user.vault_credentials || '',
});

const confirmForm = useForm({
    password: '',
});

const showCredentials = ref(false);
const confirmingPassword = ref(false);
const passwordInput = ref(null);

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

const updateVault = () => {
    form.patch(route('profile.vault.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Se muestra el mensaje por default abajo
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Mi Bóveda de Accesos Personales
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Guarda aquí de forma segura tus contraseñas, pasafrases y notas de acceso a otras plataformas. Esta información solo es visible para ti.
            </p>
        </header>

        <form @submit.prevent="updateVault" class="mt-6 space-y-6">
            <div class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                <div class="flex justify-between items-center mb-2">
                    <InputLabel for="vault_credentials" value="Bóveda de Accesos (Plataformas, Contraseñas, etc.)" class="font-bold text-gray-800" />
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
                    id="vault_credentials"
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 rounded-md shadow-sm font-mono text-sm transition-all"
                    :class="{'text-transparent bg-gray-200 select-none' : !showCredentials, 'bg-white text-gray-800' : showCredentials}"
                    v-model="form.vault_credentials"
                    rows="8"
                    placeholder="Escribe aquí los usuarios y contraseñas..."
                ></textarea>
                <InputError class="mt-2" :message="form.errors.vault_credentials" />
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Guardar Bóveda</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600"
                    >
                        Guardado exitosamente.
                    </p>
                </Transition>
            </div>
        </form>

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
                        id="password"
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
    </section>
</template>
