<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import DangerButton from '@/Components/DangerButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import axios from 'axios';

const props = defineProps({
    enabled: Boolean,
});

const enabling = ref(false);
const confirming = ref(false);
const disabling = ref(false);
const qrCode = ref(null);
const recoveryCodes = ref([]);
const error = ref('');

const confirmationForm = useForm({
    code: '',
});

const passwordConfirmForm = useForm({
    password: '',
});

const enableTwoFactorAuthentication = () => {
    enabling.value = true;
    axios.post(route('two-factor.enable')).then(response => {
        qrCode.value = response.data.qrCode;
        enabling.value = false;
        confirming.value = true;
    }).catch(err => {
        enabling.value = false;
        console.error(err);
    });
};

const confirmTwoFactorAuthentication = () => {
    confirmationForm.post(route('two-factor.confirm'), {
        preserveScroll: true,
        onSuccess: () => {
            confirming.value = false;
            qrCode.value = null;
            showRecoveryCodes();
        },
    });
};

const showRecoveryCodes = () => {
    axios.get(route('two-factor.recovery-codes')).then(response => {
        recoveryCodes.value = response.data.recoveryCodes;
    });
};

const performDisable = () => {
    passwordConfirmForm.delete(route('two-factor.disable'), {
        preserveScroll: true,
        onSuccess: () => {
            disabling.value = false;
            passwordConfirmForm.reset();
        },
    });
};

const cancelConfirmation = () => {
    confirming.value = false;
    qrCode.value = null;
    confirmationForm.reset();
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Autenticación de dos pasos</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Añade seguridad adicional a tu cuenta mediante la autenticación de dos pasos (TOTP).
            </p>
        </header>

        <div class="mt-6">
            <div v-if="enabled && !confirming" class="text-sm font-medium text-green-600 mb-4">
                ✅ La autenticación de dos pasos está activa.
            </div>
            <div v-else-if="confirming" class="text-sm font-medium text-blue-600 mb-4">
                ⚠️ Finaliza la configuración escaneando el código QR.
            </div>
            <div v-else class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                La autenticación de dos pasos no está habilitada.
            </div>

            <div class="max-w-xl text-sm text-gray-600 dark:text-gray-400 space-y-4">
                <p>
                    Cuando la autenticación de dos pasos está habilitada, se pedirá un token seguro durante el inicio de sesión. Puedes usar aplicaciones como Google Authenticator, Authy o Apple Keychain.
                    <br>Descargar Google Authenticator <a target="_blank" href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2&hl=es&pli=1" class="text-blue-500 hover:underline">Google Play</a> o <a target="_blank" href="https://apps.apple.com/us/app/google-authenticator/id388497605" class="text-blue-500 hover:underline">App Store</a>
                </p>
            </div>

            <div v-if="confirming" class="mt-6 p-4 bg-gray-50 dark:bg-zinc-950 rounded-lg border border-gray-200 dark:border-zinc-800">
                <p class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-4">
                    1. Escanea este código QR con tu aplicación:
                </p>
                <div class="p-2 bg-white inline-block rounded shadow-sm mb-4" v-html="qrCode"></div>
                
                <p class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-2">
                    2. Ingresa el código de 6 dígitos que aparece en tu app:
                </p>
                <div class="max-w-xs">
                    <TextInput
                        v-model="confirmationForm.code"
                        type="text"
                        class="block w-full"
                        placeholder="000000"
                        @keyup.enter="confirmTwoFactorAuthentication"
                    />
                    <InputError :message="confirmationForm.errors.code" class="mt-2" />
                </div>
            </div>

            <div v-if="recoveryCodes.length > 0" class="mt-6">
                <div class="p-4 bg-red-50 dark:bg-rose-900/20 border border-red-100 dark:border-rose-900/30 rounded-lg">
                    <p class="text-sm font-bold text-red-800 dark:text-rose-300 mb-2">
                        Códigos de Recuperación
                    </p>
                    <p class="text-xs text-red-600 dark:text-rose-400 mb-4">
                        Guarda estos códigos en un lugar seguro. Solo se mostrarán una vez.
                    </p>
                    <div class="grid grid-cols-2 gap-2 font-mono text-xs text-red-900 dark:text-rose-200">
                        <div v-for="code in recoveryCodes" :key="code.code || code" class="bg-white/50 dark:bg-zinc-900/50 p-1 rounded">
                            {{ typeof code === 'object' ? code.code : code }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-4">
                <div v-if="!enabled && !confirming">
                    <PrimaryButton :disabled="enabling" @click="enableTwoFactorAuthentication">
                        Configurar 2FA
                    </PrimaryButton>
                </div>

                <div v-else-if="confirming" class="flex gap-2">
                    <PrimaryButton :disabled="confirmationForm.processing" @click="confirmTwoFactorAuthentication">
                        Activar ahora
                    </PrimaryButton>
                    <SecondaryButton @click="cancelConfirmation">
                        Cancelar
                    </SecondaryButton>
                </div>

                <div v-else class="flex gap-2">
                    <DangerButton @click="disabling = true">
                        Desactivar 2FA
                    </DangerButton>
                    <SecondaryButton v-if="recoveryCodes.length === 0" @click="showRecoveryCodes">
                        Códigos de recuperación
                    </SecondaryButton>
                </div>
            </div>
        </div>

        <Modal :show="disabling" @close="disabling = false">
            <div class="p-6 bg-white dark:bg-zinc-900 rounded-2xl">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    ¿Desactivar autenticación de dos pasos?
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Para confirmar, ingresa tu contraseña actual.
                </p>
                <div class="mt-6">
                    <InputLabel for="password_confirm" value="Contraseña" class="sr-only" />
                    <TextInput
                        id="password_confirm"
                        type="password"
                        v-model="passwordConfirmForm.password"
                        class="mt-1 block w-full"
                        placeholder="Ingresa tu contraseña"
                        @keyup.enter="performDisable"
                    />
                    <InputError :message="passwordConfirmForm.errors.password" class="mt-2" />
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="disabling = false"> Cancelar </SecondaryButton>
                    <DangerButton :disabled="passwordConfirmForm.processing" @click="performDisable">
                        Desactivar
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
