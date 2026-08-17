<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ChatBubbleLeftRightIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline';
import { onMounted, onUnmounted, ref, computed } from 'vue';

const props = defineProps({
    client: { type: Object, required: true },
    numeros: { type: Array, default: () => [] },
    appId: { type: String, default: null },
    configId: { type: String, default: null },
});

const form = useForm({ code: '', waba_id: null });
const cargandoSdk = ref(true);
const errorSdk = ref(null);
const enProceso = ref(false);

const configurado = computed(() => Boolean(props.appId && props.configId));

// Embedded Signup avisa por postMessage qué WABA y qué número concedió el
// cliente. Se guarda para no tener que deducirlo del token después.
let concedido = { waba_id: null, phone_number_id: null };

const escucharMensajes = (event) => {
    if (!/^https:\/\/(www\.)?facebook\.com$/.test(event.origin)) return;
    try {
        const datos = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
        if (datos?.type === 'WA_EMBEDDED_SIGNUP' && datos?.data) {
            concedido = {
                waba_id: datos.data.waba_id ?? null,
                phone_number_id: datos.data.phone_number_id ?? null,
            };
        }
    } catch (e) {
        // Llegan mensajes de Facebook que no son de este flujo; se ignoran.
    }
};

const cargarSdk = () => {
    if (!configurado.value) {
        cargandoSdk.value = false;
        return;
    }
    if (window.FB) {
        inicializar();
        return;
    }
    const s = document.createElement('script');
    s.src = 'https://connect.facebook.net/en_US/sdk.js';
    s.async = true;
    s.defer = true;
    s.crossOrigin = 'anonymous';
    s.onload = inicializar;
    s.onerror = () => {
        errorSdk.value = 'No se pudo cargar el SDK de Facebook. Revisa tu conexión o un bloqueador de anuncios.';
        cargandoSdk.value = false;
    };
    document.body.appendChild(s);
};

const inicializar = () => {
    window.FB.init({ appId: props.appId, autoLogAppEvents: true, xfbml: false, version: 'v26.0' });
    cargandoSdk.value = false;
};

const conectar = () => {
    if (!window.FB) return;
    enProceso.value = true;

    window.FB.login(
        (respuesta) => {
            const code = respuesta?.authResponse?.code;
            if (!code) {
                enProceso.value = false;
                return; // el cliente cerró el diálogo
            }
            form.code = code;
            form.waba_id = concedido.waba_id;
            form.post(route('whatsapp.connect.store', props.client.id), {
                preserveScroll: true,
                onFinish: () => { enProceso.value = false; },
            });
        },
        {
            config_id: props.configId,
            response_type: 'code',
            override_default_response_type: true,
            extras: { setup: {}, featureType: '', sessionInfoVersion: '3' },
        }
    );
};

const desconectar = (accountId) => {
    if (!confirm('¿Desconectar esta cuenta de WhatsApp? Dejaremos de recibir y enviar mensajes por sus números.')) return;
    router.delete(route('whatsapp.connect.destroy', [props.client.id, accountId]), { preserveScroll: true });
};

const colorCalidad = (r) => ({
    GREEN: 'text-emerald-600',
    YELLOW: 'text-amber-600',
    RED: 'text-red-600',
}[r] ?? 'text-gray-400');

onMounted(() => {
    window.addEventListener('message', escucharMensajes);
    cargarSdk();
});
onUnmounted(() => window.removeEventListener('message', escucharMensajes));
</script>

<template>
    <Head :title="`WhatsApp — ${client.business_name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <Link
                    :href="route('social.clients.show', client.id)"
                    class="text-xs text-[#264ab3] hover:underline"
                >← {{ client.business_name }}</Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-zinc-100 leading-tight flex items-center gap-2 mt-1">
                    <ChatBubbleLeftRightIcon class="h-6 w-6" />
                    WhatsApp — {{ client.business_name }}
                </h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Conectar -->
                <div class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900 dark:text-zinc-100">Conectar la cuenta del cliente</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">
                        El cliente inicia sesión con su cuenta de Facebook y autoriza el acceso a su
                        WhatsApp Business Account. Nosotros nunca vemos ni pedimos su contraseña, y el
                        cliente puede revocarnos el acceso desde su Business Manager en cualquier momento.
                    </p>

                    <div
                        v-if="!configurado"
                        class="mt-4 flex items-start gap-2 rounded-md bg-amber-50 dark:bg-amber-900/20 p-3 text-sm text-amber-800 dark:text-amber-300"
                    >
                        <ExclamationTriangleIcon class="h-5 w-5 shrink-0 mt-0.5" />
                        <span>
                            Falta configurar <code>WHATSAPP_APP_ID</code> y
                            <code>WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID</code>. El
                            <em>configuration_id</em> se crea en el panel de Meta, en el flujo de
                            Embedded Signup.
                        </span>
                    </div>

                    <p v-else-if="errorSdk" class="mt-4 text-sm text-red-600">{{ errorSdk }}</p>

                    <button
                        v-else
                        @click="conectar"
                        :disabled="cargandoSdk || enProceso || form.processing"
                        class="mt-4 inline-flex items-center gap-2 rounded-md bg-[#25D366] px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                    >
                        <ChatBubbleLeftRightIcon class="h-5 w-5" />
                        {{ cargandoSdk ? 'Cargando…' : (enProceso || form.processing ? 'Conectando…' : 'Conectar WhatsApp') }}
                    </button>

                    <p v-if="form.errors.code" class="mt-3 text-sm text-red-600">{{ form.errors.code }}</p>
                </div>

                <!-- Números conectados -->
                <div class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900 dark:text-zinc-100">Números conectados</h3>

                    <p v-if="!numeros.length" class="mt-2 text-sm text-gray-500">
                        Todavía no hay números conectados para este cliente.
                    </p>

                    <ul v-else class="mt-4 divide-y divide-gray-100 dark:divide-zinc-800">
                        <li v-for="n in numeros" :key="n.id" class="py-3 flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="font-medium text-sm text-gray-900 dark:text-zinc-100 flex items-center gap-2">
                                    <CheckCircleIcon v-if="n.is_active" class="h-4 w-4 text-emerald-600" />
                                    {{ n.display_phone_number }}
                                    <span v-if="n.verified_name" class="text-gray-500 font-normal">
                                        · {{ n.verified_name }}
                                    </span>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-zinc-400">
                                    WABA {{ n.waba_id }} · Phone Number ID {{ n.phone_number_id }}
                                </p>
                                <p class="text-xs mt-0.5">
                                    <span :class="colorCalidad(n.quality_rating)">
                                        Calidad: {{ n.quality_rating ?? 'sin dato' }}
                                    </span>
                                    <span v-if="n.account_status === 'revoked'" class="ml-2 text-red-600">
                                        · acceso revocado
                                    </span>
                                </p>
                            </div>
                            <button
                                v-if="n.account_id && n.is_active"
                                @click="desconectar(n.account_id)"
                                class="shrink-0 text-xs text-red-600 hover:underline"
                            >Desconectar</button>
                        </li>
                    </ul>

                    <Link
                        :href="route('conversations.index')"
                        class="mt-5 inline-block text-sm text-[#264ab3] hover:underline"
                    >Ir a Conversaciones →</Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
