<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    SparklesIcon,
    ExclamationTriangleIcon,
    TrashIcon,
    EyeIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

const props = defineProps({
    agentes: { type: Array, default: () => [] },
    clientesSinAgente: { type: Array, default: () => [] },
    puedeCrearPropio: { type: Boolean, default: false },
    hayCredenciales: { type: Boolean, default: false },
    modelos: { type: Array, default: () => [] },
});

const page = usePage();

// Qué agente está abierto para editar. Null = ninguno.
const editando = ref(null);
const creando = ref(false);

const form = useForm({
    client_id: null,
    name: '',
    model: 'claude-opus-5',
    system_prompt: '',
    disclosure: '',
    monthly_token_limit: 0,
    enabled: false,
});

const preview = computed(() => page.props.flash?.preview ?? null);

const abrir = (agente) => {
    creando.value = false;
    editando.value = agente.id;
    form.defaults();
    form.clearErrors();
    form.client_id = agente.client_id;
    form.name = agente.name;
    form.model = agente.model;
    form.system_prompt = agente.system_prompt ?? '';
    form.disclosure = agente.disclosure ?? '';
    form.monthly_token_limit = agente.monthly_token_limit ?? 0;
    form.enabled = agente.enabled;
};

const abrirAlta = () => {
    editando.value = null;
    creando.value = true;
    form.reset();
    form.clearErrors();
};

const cerrar = () => {
    editando.value = null;
    creando.value = false;
};

const guardar = () => {
    if (creando.value) {
        form.post(route('ai.agents.store'), { preserveScroll: true, onSuccess: cerrar });
        return;
    }

    form.put(route('ai.agents.update', editando.value), { preserveScroll: true, onSuccess: cerrar });
};

const verPrompt = (agente) => {
    form.post(route('ai.agents.preview', agente.id), { preserveScroll: true });
};

const eliminar = (agente) => {
    // Sin confirm(): un diálogo del navegador bloquea la pestaña. El botón
    // exige dos pasos en su lugar.
    if (confirmandoBorrado.value !== agente.id) {
        confirmandoBorrado.value = agente.id;
        return;
    }

    form.delete(route('ai.agents.destroy', agente.id), {
        preserveScroll: true,
        onSuccess: () => { confirmandoBorrado.value = null; },
    });
};

const confirmandoBorrado = ref(null);

const miles = (n) => new Intl.NumberFormat('es-MX').format(n ?? 0);

// El color de la barra de consumo. Amarillo antes de llegar, no al llegar:
// enterarse de que un agente se quedó mudo cuando ya pasó no sirve de nada.
const colorConsumo = (pct) => {
    if (pct === null) return 'bg-gray-300 dark:bg-zinc-700';
    if (pct >= 100) return 'bg-red-500';
    if (pct >= 80) return 'bg-amber-500';
    return 'bg-emerald-500';
};
</script>

<template>
    <Head title="Agentes de IA" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-zinc-100 leading-tight flex items-center gap-2">
                <SparklesIcon class="h-6 w-6" />
                Agentes de IA
            </h2>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Sin credenciales el agente queda mudo sin dar error. Vale
                     la pena decirlo antes de que alguien lo encienda. -->
                <div
                    v-if="!hayCredenciales"
                    class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg p-6 flex items-start gap-3"
                >
                    <ExclamationTriangleIcon class="h-5 w-5 shrink-0 mt-0.5 text-amber-600" />
                    <div class="text-sm text-gray-600 dark:text-zinc-400">
                        <p class="font-medium text-gray-800 dark:text-zinc-200">
                            Falta configurar <code>ANTHROPIC_API_KEY</code>
                        </p>
                        <p class="mt-1">
                            Los agentes se pueden crear y ajustar, pero ninguno podrá responder
                            hasta que haya credenciales. No dará error: simplemente no contestará.
                        </p>
                    </div>
                </div>

                <div class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-start justify-between gap-4">
                        <p class="text-sm text-gray-600 dark:text-zinc-400">
                            Cada cliente tiene un agente que contesta su WhatsApp.
                            Se activa conversación por conversación desde la bandeja, y
                            <strong>se calla en cuanto alguien del equipo toma la conversación</strong>.
                        </p>

                        <button
                            v-if="clientesSinAgente.length || puedeCrearPropio"
                            type="button"
                            class="shrink-0 rounded-md bg-[#264ab3] px-3 py-2 text-sm font-medium text-white hover:bg-[#1e3a8a]"
                            @click="abrirAlta"
                        >
                            Nuevo agente
                        </button>
                    </div>
                </div>

                <!-- Alta -->
                <div v-if="creando" class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg p-6 space-y-4">
                    <h3 class="font-medium text-gray-800 dark:text-zinc-100">Nuevo agente</h3>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700 dark:text-zinc-300">Cliente</span>
                        <select
                            v-model="form.client_id"
                            class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                        >
                            <option v-if="puedeCrearPropio" :value="null">LunAvalos (número propio)</option>
                            <option v-for="c in clientesSinAgente" :key="c.id" :value="c.id">
                                {{ c.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.client_id" class="mt-1 text-sm text-red-600">
                            {{ form.errors.client_id }}
                        </p>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700 dark:text-zinc-300">Nombre</span>
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="Asistente de Grupo Macadam"
                            class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </label>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-md bg-[#264ab3] px-3 py-2 text-sm font-medium text-white hover:bg-[#1e3a8a] disabled:opacity-50"
                            :disabled="form.processing"
                            @click="guardar"
                        >
                            Crear
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-3 py-2 text-sm text-gray-600 dark:text-zinc-400"
                            @click="cerrar"
                        >
                            Cancelar
                        </button>
                    </div>
                </div>

                <!-- Lista -->
                <div
                    v-for="agente in agentes"
                    :key="agente.id"
                    class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg p-6 space-y-4"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="font-medium text-gray-800 dark:text-zinc-100">{{ agente.name }}</h3>
                            <p class="text-sm text-gray-500 dark:text-zinc-400">{{ agente.cliente }}</p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span
                                :class="[
                                    'text-xs px-2 py-1 rounded-full font-medium',
                                    agente.puede_responder
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400'
                                        : 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-zinc-400',
                                ]"
                            >
                                {{ agente.puede_responder ? 'Puede responder' : 'No responde' }}
                            </span>
                        </div>
                    </div>

                    <!-- Consumo del mes -->
                    <div>
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="text-gray-600 dark:text-zinc-400">
                                Consumo de {{ agente.consumo.periodo }}
                            </span>
                            <span class="text-gray-800 dark:text-zinc-200 tabular-nums">
                                {{ miles(agente.consumo.gastado) }}
                                <template v-if="agente.monthly_token_limit">
                                    / {{ miles(agente.monthly_token_limit) }}
                                </template>
                                tokens
                            </span>
                        </div>

                        <div class="mt-1.5 h-1.5 w-full rounded-full bg-gray-200 dark:bg-zinc-800 overflow-hidden">
                            <div
                                :class="['h-full rounded-full transition-all', colorConsumo(agente.consumo.porcentaje)]"
                                :style="{ width: (agente.consumo.porcentaje ?? 100) + '%' }"
                            />
                        </div>

                        <p class="mt-1.5 text-xs text-gray-500 dark:text-zinc-500">
                            {{ miles(agente.consumo.mensajes) }} respuestas ·
                            {{ miles(agente.consumo.cache) }} leídos de caché (no cuentan al tope)
                            <template v-if="!agente.monthly_token_limit"> · sin tope</template>
                        </p>

                        <p v-if="agente.consumo.superado" class="mt-2 text-sm text-red-600">
                            Alcanzó su tope del mes y dejó de responder. Las conversaciones quedan para el equipo.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-2 border-t border-gray-100 dark:border-zinc-800">
                        <button
                            type="button"
                            class="text-sm text-[#264ab3] dark:text-blue-400 hover:underline"
                            @click="editando === agente.id ? cerrar() : abrir(agente)"
                        >
                            {{ editando === agente.id ? 'Cerrar' : 'Ajustar' }}
                        </button>

                        <button
                            type="button"
                            class="text-sm text-gray-600 dark:text-zinc-400 hover:underline flex items-center gap-1"
                            @click="verPrompt(agente)"
                        >
                            <EyeIcon class="h-4 w-4" />
                            Ver el prompt real
                        </button>

                        <button
                            type="button"
                            class="ml-auto text-sm text-red-600 hover:underline flex items-center gap-1"
                            @click="eliminar(agente)"
                        >
                            <TrashIcon class="h-4 w-4" />
                            {{ confirmandoBorrado === agente.id ? '¿Seguro? Toca otra vez' : 'Eliminar' }}
                        </button>
                    </div>

                    <!-- Edición -->
                    <div v-if="editando === agente.id" class="space-y-4 pt-4 border-t border-gray-100 dark:border-zinc-800">
                        <label class="flex items-center gap-2">
                            <input v-model="form.enabled" type="checkbox" class="rounded border-gray-300 dark:border-zinc-700" />
                            <span class="text-sm text-gray-700 dark:text-zinc-300">Agente activo</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-zinc-300">Nombre</span>
                            <input
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                            />
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-zinc-300">Modelo</span>
                            <select
                                v-model="form.model"
                                class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                            >
                                <option v-for="m in modelos" :key="m.id" :value="m.id">
                                    {{ m.nombre }} — {{ m.costo }}
                                </option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-zinc-300">
                                Tope mensual en tokens
                            </span>
                            <input
                                v-model.number="form.monthly_token_limit"
                                type="number"
                                min="0"
                                step="10000"
                                class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                            />
                            <span class="mt-1 block text-xs text-gray-500 dark:text-zinc-500">
                                0 = sin tope. Como referencia, 500,000 tokens son del orden de 250 respuestas.
                                Al alcanzarlo el agente deja de responder y las conversaciones quedan para el equipo.
                            </span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-zinc-300">
                                Instrucciones del agente
                            </span>
                            <textarea
                                v-model="form.system_prompt"
                                rows="10"
                                class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 font-mono"
                            />
                            <span class="mt-1 block text-xs text-gray-500 dark:text-zinc-500">
                                Si lo dejas vacío se arma solo con la ficha del cliente. Usa
                                «Ver el prompt real» para saber qué se le está mandando al modelo.
                            </span>
                            <p v-if="form.errors.system_prompt" class="mt-1 text-sm text-red-600">
                                {{ form.errors.system_prompt }}
                            </p>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-zinc-300">
                                Aviso de automatización
                            </span>
                            <input
                                v-model="form.disclosure"
                                type="text"
                                :placeholder="agente.disclosure_efectivo"
                                class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                            />
                            <span class="mt-1 block text-xs text-gray-500 dark:text-zinc-500">
                                Va en el primer mensaje del agente en cada conversación, y solo en el primero.
                                Si lo dejas vacío se usa el de arriba — nunca se manda sin aviso.
                            </span>
                        </label>

                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="rounded-md bg-[#264ab3] px-3 py-2 text-sm font-medium text-white hover:bg-[#1e3a8a] disabled:opacity-50"
                                :disabled="form.processing"
                                @click="guardar"
                            >
                                Guardar
                            </button>
                            <button
                                type="button"
                                class="rounded-md px-3 py-2 text-sm text-gray-600 dark:text-zinc-400"
                                @click="cerrar"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="!agentes.length"
                    class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg p-6 text-sm text-gray-500 dark:text-zinc-400"
                >
                    Todavía no hay ningún agente.
                </div>

                <!-- El prompt tal cual lo recibe el modelo -->
                <div v-if="preview" class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-medium text-gray-800 dark:text-zinc-100">
                        Esto es lo que recibe el modelo
                    </h3>
                    <pre class="mt-3 whitespace-pre-wrap text-xs text-gray-700 dark:text-zinc-300 bg-gray-50 dark:bg-zinc-800 rounded-md p-4 overflow-x-auto">{{ preview }}</pre>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
