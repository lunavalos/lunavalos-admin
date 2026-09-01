<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SocialAvatar from '@/Components/SocialAvatar.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { LinkIcon, ExclamationTriangleIcon, ChevronLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    client: { type: Object, required: true },
    provider: { type: String, required: true },
    accounts: { type: Array, default: () => [] },
});

const providerLabels = {
    facebook: 'Facebook', instagram: 'Instagram', linkedin: 'LinkedIn', tiktok: 'TikTok', youtube: 'YouTube',
};

const seleccion = ref([]);

const form = useForm({ provider_user_ids: [] });

// Una cuenta que hoy pertenece a otro cliente no se mueve sola: hay que
// marcarla a propósito, y la advertencia dice de quién es.
function ownerAviso(cuenta) {
    if (!cuenta.owner) return null;
    if (cuenta.owner.client_id === props.client.id) return null;
    return cuenta.owner.client_name || 'otro cliente';
}

const hayMovimientos = computed(() =>
    props.accounts.some(c => seleccion.value.includes(c.provider_user_id) && ownerAviso(c))
);

function enviar() {
    form.provider_user_ids = seleccion.value;
    form.post(route('social.oauth.select.store', props.client.id));
}
</script>

<template>
    <Head :title="`Elegir cuentas — ${client.business_name}`" />

    <AuthenticatedLayout>
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <Link :href="route('social.clients.show', client.id)"
                  class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-primary mb-3">
                <ChevronLeftIcon class="w-4 h-4" /> Volver
            </Link>

            <h1 class="flex items-center gap-2 text-2xl font-bold text-gray-800 dark:text-gray-100">
                <LinkIcon class="w-6 h-6 text-primary" />
                ¿Qué cuentas de {{ providerLabels[provider] || provider }} conectar?
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                Se conectarán a <strong>{{ client.business_name }}</strong>.
                Autorizaste varias, así que elige solo las de este cliente.
            </p>

            <div class="mt-6 bg-white dark:bg-zinc-900 rounded-xl shadow-sm divide-y divide-gray-100 dark:divide-zinc-800">
                <label v-for="cuenta in accounts" :key="cuenta.provider_user_id"
                       class="flex items-start gap-3 p-4 cursor-pointer hover:bg-gray-50 dark:hover:bg-zinc-800/40">
                    <input type="checkbox" :value="cuenta.provider_user_id" v-model="seleccion"
                           class="mt-1 rounded border-gray-300 text-primary focus:ring-primary" />

                    <SocialAvatar :src="cuenta.avatar_url" :name="cuenta.name" :provider="provider" />

                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-gray-800 dark:text-gray-100 truncate">{{ cuenta.name }}</p>
                        <p v-if="cuenta.handle" class="text-xs text-gray-500 truncate">@{{ cuenta.handle }}</p>

                        <p v-if="ownerAviso(cuenta)"
                           class="mt-1 inline-flex items-center gap-1 text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-full px-2 py-0.5">
                            <ExclamationTriangleIcon class="w-3 h-3" />
                            Hoy pertenece a {{ ownerAviso(cuenta) }} · marcarla la mueve a este cliente
                        </p>
                    </div>
                </label>
            </div>

            <p v-if="hayMovimientos" class="mt-3 text-xs text-amber-700">
                Una cuenta solo puede estar en un cliente a la vez. Las que marcaste con aviso
                dejarán de estar disponibles para el cliente que las tenía.
            </p>

            <div v-if="form.errors.seleccion" class="mt-3 text-sm text-rose-600">{{ form.errors.seleccion }}</div>
            <div v-if="form.errors.provider_user_ids" class="mt-3 text-sm text-rose-600">
                Selecciona al menos una cuenta.
            </div>

            <div class="mt-6 flex items-center justify-between">
                <p class="text-xs text-gray-500">
                    ¿Falta alguna? Vuelve a conectar y pulsa <strong>Edit settings</strong> en el
                    diálogo de {{ providerLabels[provider] || provider }} para autorizar más cuentas.
                </p>
                <button type="button" @click="enviar"
                        :disabled="!seleccion.length || form.processing"
                        class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold disabled:opacity-40">
                    Conectar {{ seleccion.length || '' }}
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
