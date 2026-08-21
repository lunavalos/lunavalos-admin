<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import {
    KeyIcon,
    TrashIcon,
    ArrowPathIcon,
    ClipboardDocumentIcon,
    ExclamationTriangleIcon,
    PowerIcon,
    PencilSquareIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

const props = defineProps({
    integraciones: { type: Array, default: () => [] },
    clientes: { type: Array, default: () => [] },
    permisosDisponibles: { type: Array, default: () => [] },
    baseUrl: { type: String, default: '' },
});

const page = usePage();

// Solo existe justo después de emitir. Una recarga la desaparece, que es
// exactamente lo que debe pasar con un secreto.
const credenciales = computed(() => page.props.flash?.credenciales ?? null);

const creando = ref(false);
const editando = ref(null);
const rotando = ref(null);
const copiado = ref(null);

const alta = useForm({
    name: '',
    client_id: null,
    webhook_url: '',
    permisos: ['mensajes:enviar'],
    expira_dias: null,
});

const edicion = useForm({
    name: '',
    client_id: null,
    webhook_url: '',
});

const rotacion = useForm({
    permisos: ['mensajes:enviar'],
    expira_dias: null,
});

const abrirAlta = () => {
    editando.value = null;
    rotando.value = null;
    alta.reset();
    alta.clearErrors();
    creando.value = true;
};

const abrirEdicion = (i) => {
    creando.value = false;
    rotando.value = null;
    edicion.clearErrors();
    edicion.name = i.name;
    edicion.client_id = i.client_id;
    edicion.webhook_url = i.webhook_url ?? '';
    editando.value = i.id;
};

const abrirRotacion = (i) => {
    creando.value = false;
    editando.value = null;
    rotacion.clearErrors();
    rotacion.permisos = i.token?.permisos?.length ? [...i.token.permisos] : ['mensajes:enviar'];
    rotacion.expira_dias = null;
    rotando.value = i.id;
};

const cerrar = () => {
    creando.value = false;
    editando.value = null;
    rotando.value = null;
};

const guardarAlta = () => alta.post(route('integraciones.store'), {
    preserveScroll: true,
    onSuccess: () => { creando.value = false; alta.reset(); },
});

const guardarEdicion = (id) => edicion.put(route('integraciones.update', id), {
    preserveScroll: true,
    onSuccess: () => { editando.value = null; },
});

const rotarToken = (id) => rotacion.post(route('integraciones.token', id), {
    preserveScroll: true,
    onSuccess: () => { rotando.value = null; },
});

const rotarSecreto = (i) => {
    if (!confirm(`Se emitirá un secreto nuevo para «${i.name}». El actual dejará de validar las firmas hasta que lo actualices del otro lado. ¿Continuar?`)) return;
    router.post(route('integraciones.secreto', i.id), {}, { preserveScroll: true });
};

const alternar = (i) => {
    const verbo = i.activo ? 'desactivar' : 'reactivar';
    if (!confirm(`¿Seguro que quieres ${verbo} «${i.name}»?`)) return;
    router.post(route('integraciones.toggle', i.id), {}, { preserveScroll: true });
};

const eliminar = (i) => {
    if (!confirm(`Se borrará «${i.name}» y su token dejará de funcionar de inmediato. Esto no se puede deshacer. ¿Continuar?`)) return;
    router.delete(route('integraciones.destroy', i.id), { preserveScroll: true });
};

const copiar = async (texto, cual) => {
    try {
        await navigator.clipboard.writeText(texto);
        copiado.value = cual;
        setTimeout(() => { copiado.value = null; }, 2000);
    } catch {
        copiado.value = null;
    }
};

const fecha = (iso) => iso
    ? new Date(iso).toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' })
    : null;

const alcance = (i) => i.atado
    ? (i.cliente ?? `Cliente #${i.client_id}`)
    : 'Interna — nombra el cliente en cada petición';
</script>

<template>
    <Head title="Integraciones" />

    <AuthenticatedLayout>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex items-start justify-between gap-4 mb-2">
                <div class="flex items-center gap-3">
                    <KeyIcon class="w-7 h-7 text-[#264ab3] dark:text-blue-400" />
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-zinc-100">Integraciones</h1>
                </div>
                <button
                    @click="abrirAlta"
                    class="shrink-0 px-4 py-2 rounded-lg bg-[#264ab3] text-white text-sm font-semibold hover:bg-blue-800 transition-colors"
                >
                    Nueva integración
                </button>
            </div>

            <p class="text-sm text-gray-500 dark:text-zinc-400 mb-6">
                Sistemas externos que usan nuestro WhatsApp por
                <code class="text-xs bg-gray-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded">{{ baseUrl }}</code>
                sin tener su propia app de Meta. El token de Meta nunca sale de este servidor.
            </p>

            <!-- Credenciales recién emitidas -->
            <div
                v-if="credenciales"
                class="mb-6 rounded-lg border-2 border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-5"
            >
                <div class="flex items-start gap-3 mb-4">
                    <ExclamationTriangleIcon class="w-6 h-6 text-amber-600 dark:text-amber-400 shrink-0" />
                    <div>
                        <h2 class="font-bold text-amber-900 dark:text-amber-200">
                            Credenciales de «{{ credenciales.integracion }}»
                        </h2>
                        <p class="text-sm text-amber-800 dark:text-amber-300">
                            Se muestran <strong>una sola vez</strong>. Cópialas ahora: no se pueden volver a
                            consultar, solo reemitir.
                        </p>
                    </div>
                </div>

                <div v-if="credenciales.token" class="mb-3">
                    <label class="block text-xs font-bold uppercase tracking-wide text-amber-900 dark:text-amber-300 mb-1">
                        Token
                    </label>
                    <div class="flex gap-2">
                        <code class="flex-1 text-xs bg-white dark:bg-zinc-900 border border-amber-200 dark:border-amber-800 rounded px-3 py-2 break-all text-gray-800 dark:text-zinc-200">
                            {{ credenciales.token }}
                        </code>
                        <button
                            @click="copiar(credenciales.token, 'token')"
                            class="shrink-0 px-3 py-2 rounded bg-amber-600 text-white text-xs font-semibold hover:bg-amber-700 flex items-center gap-1"
                        >
                            <ClipboardDocumentIcon class="w-4 h-4" />
                            {{ copiado === 'token' ? 'Copiado' : 'Copiar' }}
                        </button>
                    </div>
                    <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                        Se manda como <code>Authorization: Bearer &lt;token&gt;</code>
                    </p>
                </div>

                <div v-if="credenciales.secreto">
                    <label class="block text-xs font-bold uppercase tracking-wide text-amber-900 dark:text-amber-300 mb-1">
                        Secreto del webhook
                    </label>
                    <div class="flex gap-2">
                        <code class="flex-1 text-xs bg-white dark:bg-zinc-900 border border-amber-200 dark:border-amber-800 rounded px-3 py-2 break-all text-gray-800 dark:text-zinc-200">
                            {{ credenciales.secreto }}
                        </code>
                        <button
                            @click="copiar(credenciales.secreto, 'secreto')"
                            class="shrink-0 px-3 py-2 rounded bg-amber-600 text-white text-xs font-semibold hover:bg-amber-700 flex items-center gap-1"
                        >
                            <ClipboardDocumentIcon class="w-4 h-4" />
                            {{ copiado === 'secreto' ? 'Copiado' : 'Copiar' }}
                        </button>
                    </div>
                    <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                        Con él se verifica la cabecera <code>X-LunAvalos-Signature</code> de cada entrega.
                    </p>
                </div>
            </div>

            <!-- Alta -->
            <div v-if="creando" class="mb-6 rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
                <h2 class="font-bold text-gray-900 dark:text-zinc-100 mb-4">Nueva integración</h2>

                <div class="grid sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-zinc-300 mb-1">Nombre</label>
                        <input
                            v-model="alta.name"
                            type="text"
                            placeholder="landing-macadam"
                            class="w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 text-sm"
                        />
                        <p v-if="alta.errors.name" class="text-xs text-red-600 mt-1">{{ alta.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-zinc-300 mb-1">Cliente</label>
                        <select
                            v-model="alta.client_id"
                            class="w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 text-sm"
                        >
                            <option :value="null">Interna de LunAvalos (varios clientes)</option>
                            <option v-for="c in clientes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">
                            Atada a un cliente solo puede operar sobre él, mande lo que mande.
                        </p>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-700 dark:text-zinc-300 mb-1">
                        Webhook de entrada <span class="font-normal text-gray-400">(opcional)</span>
                    </label>
                    <input
                        v-model="alta.webhook_url"
                        type="url"
                        placeholder="https://macadam.mx/api/whatsapp/entrante"
                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 text-sm"
                    />
                    <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">
                        A dónde le avisamos cuando el contacto responde. Si lo pones, se genera su secreto de firma.
                    </p>
                    <p v-if="alta.errors.webhook_url" class="text-xs text-red-600 mt-1">{{ alta.errors.webhook_url }}</p>
                </div>

                <div class="grid sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-zinc-300 mb-2">Permisos</label>
                        <label
                            v-for="p in permisosDisponibles"
                            :key="p"
                            class="flex items-center gap-2 mb-1.5 text-sm text-gray-700 dark:text-zinc-300"
                        >
                            <input type="checkbox" :value="p" v-model="alta.permisos" class="rounded border-gray-300 dark:border-zinc-600" />
                            <code class="text-xs">{{ p }}</code>
                        </label>
                        <p v-if="alta.errors.permisos" class="text-xs text-red-600 mt-1">{{ alta.errors.permisos }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-zinc-300 mb-1">
                            Caduca en <span class="font-normal text-gray-400">(días, opcional)</span>
                        </label>
                        <input
                            v-model="alta.expira_dias"
                            type="number"
                            min="1"
                            placeholder="Sin caducidad"
                            class="w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 text-sm"
                        />
                    </div>
                </div>

                <div class="flex gap-2">
                    <button
                        @click="guardarAlta"
                        :disabled="alta.processing"
                        class="px-4 py-2 rounded-lg bg-[#264ab3] text-white text-sm font-semibold hover:bg-blue-800 disabled:opacity-50"
                    >
                        {{ alta.processing ? 'Creando…' : 'Crear y emitir token' }}
                    </button>
                    <button @click="cerrar" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-zinc-700 text-sm text-gray-700 dark:text-zinc-300">
                        Cancelar
                    </button>
                </div>
            </div>

            <!-- Listado -->
            <div v-if="integraciones.length === 0 && !creando" class="rounded-lg border border-dashed border-gray-300 dark:border-zinc-700 p-12 text-center">
                <KeyIcon class="w-10 h-10 mx-auto text-gray-300 dark:text-zinc-700 mb-3" />
                <p class="text-gray-500 dark:text-zinc-400">Todavía no hay integraciones.</p>
            </div>

            <div v-else class="space-y-4">
                <div
                    v-for="i in integraciones"
                    :key="i.id"
                    :class="[
                        'rounded-lg border bg-white dark:bg-zinc-900 p-5',
                        i.activo
                            ? 'border-gray-200 dark:border-zinc-700'
                            : 'border-gray-200 dark:border-zinc-800 opacity-60'
                    ]"
                >
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-bold text-gray-900 dark:text-zinc-100">{{ i.name }}</h3>
                                <code class="text-xs text-gray-400">{{ i.slug }}</code>
                                <span
                                    :class="[
                                        'text-[10px] font-bold uppercase px-2 py-0.5 rounded-full',
                                        i.activo
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-gray-200 text-gray-600 dark:bg-zinc-800 dark:text-zinc-400'
                                    ]"
                                >
                                    {{ i.activo ? 'Activa' : 'Desactivada' }}
                                </span>
                                <span
                                    v-if="i.token?.caducado"
                                    class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400"
                                >
                                    Token caducado
                                </span>
                                <span
                                    v-if="!i.token"
                                    class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400"
                                >
                                    Sin token
                                </span>
                            </div>

                            <p class="text-sm text-gray-600 dark:text-zinc-400 mt-1">{{ alcance(i) }}</p>

                            <div class="flex flex-wrap gap-1.5 mt-2">
                                <code
                                    v-for="p in (i.token?.permisos ?? [])"
                                    :key="p"
                                    class="text-[11px] bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-zinc-300 px-2 py-0.5 rounded"
                                >{{ p }}</code>
                            </div>
                        </div>

                        <div class="flex items-center gap-1 shrink-0">
                            <button @click="abrirEdicion(i)" title="Editar" class="p-2 rounded hover:bg-gray-100 dark:hover:bg-zinc-800 text-gray-500 dark:text-zinc-400">
                                <PencilSquareIcon class="w-5 h-5" />
                            </button>
                            <button @click="abrirRotacion(i)" title="Reemitir token" class="p-2 rounded hover:bg-gray-100 dark:hover:bg-zinc-800 text-gray-500 dark:text-zinc-400">
                                <ArrowPathIcon class="w-5 h-5" />
                            </button>
                            <button
                                @click="alternar(i)"
                                :title="i.activo ? 'Desactivar' : 'Reactivar'"
                                :class="['p-2 rounded hover:bg-gray-100 dark:hover:bg-zinc-800', i.activo ? 'text-amber-600' : 'text-green-600']"
                            >
                                <PowerIcon class="w-5 h-5" />
                            </button>
                            <button @click="eliminar(i)" title="Eliminar" class="p-2 rounded hover:bg-red-50 dark:hover:bg-red-900/20 text-red-500">
                                <TrashIcon class="w-5 h-5" />
                            </button>
                        </div>
                    </div>

                    <dl class="grid sm:grid-cols-3 gap-x-6 gap-y-2 mt-4 pt-4 border-t border-gray-100 dark:border-zinc-800 text-xs">
                        <div>
                            <dt class="text-gray-400 dark:text-zinc-500 uppercase tracking-wide font-semibold">Última llamada</dt>
                            <dd class="text-gray-700 dark:text-zinc-300 mt-0.5">{{ fecha(i.last_used_at) ?? 'Nunca' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-400 dark:text-zinc-500 uppercase tracking-wide font-semibold">Token caduca</dt>
                            <dd class="text-gray-700 dark:text-zinc-300 mt-0.5">{{ fecha(i.token?.expira_el) ?? 'Nunca' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-400 dark:text-zinc-500 uppercase tracking-wide font-semibold">Creada por</dt>
                            <dd class="text-gray-700 dark:text-zinc-300 mt-0.5">{{ i.creada_por ?? '—' }}</dd>
                        </div>
                    </dl>

                    <div v-if="i.webhook_url" class="mt-3 flex items-center justify-between gap-3 flex-wrap">
                        <div class="min-w-0">
                            <span class="text-[10px] text-gray-400 dark:text-zinc-500 uppercase tracking-wide font-semibold">Webhook</span>
                            <code class="block text-xs text-gray-600 dark:text-zinc-300 break-all">{{ i.webhook_url }}</code>
                        </div>
                        <button
                            @click="rotarSecreto(i)"
                            class="shrink-0 text-xs text-[#264ab3] dark:text-blue-400 hover:underline font-semibold"
                        >
                            Rotar secreto
                        </button>
                    </div>

                    <!-- Edición -->
                    <div v-if="editando === i.id" class="mt-4 pt-4 border-t border-gray-100 dark:border-zinc-800">
                        <div class="grid sm:grid-cols-2 gap-4 mb-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-zinc-300 mb-1">Nombre</label>
                                <input v-model="edicion.name" type="text" class="w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 text-sm" />
                                <p v-if="edicion.errors.name" class="text-xs text-red-600 mt-1">{{ edicion.errors.name }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-zinc-300 mb-1">Cliente</label>
                                <select v-model="edicion.client_id" class="w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 text-sm">
                                    <option :value="null">Interna de LunAvalos</option>
                                    <option v-for="c in clientes" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="block text-xs font-semibold text-gray-700 dark:text-zinc-300 mb-1">Webhook</label>
                            <input v-model="edicion.webhook_url" type="url" class="w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 text-sm" />
                            <p v-if="edicion.errors.webhook_url" class="text-xs text-red-600 mt-1">{{ edicion.errors.webhook_url }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button @click="guardarEdicion(i.id)" :disabled="edicion.processing" class="px-3 py-1.5 rounded-lg bg-[#264ab3] text-white text-xs font-semibold hover:bg-blue-800 disabled:opacity-50">
                                Guardar
                            </button>
                            <button @click="cerrar" class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-zinc-700 text-xs text-gray-700 dark:text-zinc-300">
                                Cancelar
                            </button>
                        </div>
                    </div>

                    <!-- Reemisión de token -->
                    <div v-if="rotando === i.id" class="mt-4 pt-4 border-t border-gray-100 dark:border-zinc-800">
                        <p class="text-xs text-amber-700 dark:text-amber-400 mb-3 flex items-start gap-1.5">
                            <ExclamationTriangleIcon class="w-4 h-4 shrink-0 mt-px" />
                            El token actual dejará de funcionar en cuanto emitas el nuevo. Ten a la mano dónde
                            actualizarlo del otro lado.
                        </p>
                        <div class="grid sm:grid-cols-2 gap-4 mb-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-zinc-300 mb-2">Permisos del token nuevo</label>
                                <label v-for="p in permisosDisponibles" :key="p" class="flex items-center gap-2 mb-1.5 text-sm text-gray-700 dark:text-zinc-300">
                                    <input type="checkbox" :value="p" v-model="rotacion.permisos" class="rounded border-gray-300 dark:border-zinc-600" />
                                    <code class="text-xs">{{ p }}</code>
                                </label>
                                <p v-if="rotacion.errors.permisos" class="text-xs text-red-600 mt-1">{{ rotacion.errors.permisos }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-zinc-300 mb-1">Caduca en (días)</label>
                                <input v-model="rotacion.expira_dias" type="number" min="1" placeholder="Sin caducidad" class="w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 text-sm" />
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button @click="rotarToken(i.id)" :disabled="rotacion.processing" class="px-3 py-1.5 rounded-lg bg-amber-600 text-white text-xs font-semibold hover:bg-amber-700 disabled:opacity-50">
                                {{ rotacion.processing ? 'Emitiendo…' : 'Reemitir token' }}
                            </button>
                            <button @click="cerrar" class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-zinc-700 text-xs text-gray-700 dark:text-zinc-300">
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
