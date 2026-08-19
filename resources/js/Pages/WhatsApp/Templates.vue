<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    DocumentTextIcon,
    ArrowPathIcon,
    TrashIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    cuentas: { type: Array, default: () => [] },
    cuentaId: { type: Number, default: null },
    plantillas: { type: Array, default: () => [] },
    categorias: { type: Array, default: () => [] },
});

const mostrarForm = ref(false);

// Va como binding y no como atributo literal: Vue interpretaría las llaves
// dobles de un placeholder estático.
const ejemploCuerpo = 'Hola {{1}}, tu pedido {{2}} ya está listo.';

const form = useForm({
    name: '',
    language: 'es_MX',
    category: 'UTILITY',
    header: '',
    body: '',
    footer: '',
    ejemplos: [],
});

const cuenta = computed(() => props.cuentas.find((c) => c.id === props.cuentaId) ?? null);

// Los {{n}} del cuerpo son lo que después habrá que rellenar al enviar. Se
// cuentan en vivo para que quien escribe la plantilla lo vea mientras la
// escribe, no cuando Meta la rechace.
const variables = computed(() => {
    const encontradas = [...form.body.matchAll(/\{\{\s*(\d+)\s*\}\}/g)].map((m) => Number(m[1]));
    return encontradas.length ? Math.max(...encontradas) : 0;
});

// Meta pide un ejemplo por cada {{n}}. Si el usuario quita una variable del
// cuerpo, su ejemplo tiene que desaparecer con ella o el envío no cuadra.
watch(variables, (n) => {
    form.ejemplos = Array.from({ length: n }, (_, i) => form.ejemplos[i] ?? '');
});

// Sustituye los huecos por los ejemplos. Es la mejor forma de explicar qué es
// {{1}}: en vez de describirlo, se ve el mensaje final.
const sustituir = (texto) =>
    (texto || '').replace(
        /\{\{\s*(\d+)\s*\}\}/g,
        (hueco, n) => form.ejemplos[Number(n) - 1] || hueco,
    );

const vistaPreviaCuerpo = computed(() => sustituir(form.body));

const ejemplosCompletos = computed(
    () => form.ejemplos.length === variables.value
        && form.ejemplos.every((e) => e && e.trim())
);

const cambiarCuenta = (id) => {
    router.get(route('whatsapp.templates.index'), { cuenta: id }, { preserveState: false });
};

const sincronizar = () => {
    router.post(route('whatsapp.templates.sync', props.cuentaId), {}, { preserveScroll: true });
};

const crear = () => {
    form.post(route('whatsapp.templates.store', props.cuentaId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            mostrarForm.value = false;
        },
    });
};

const eliminar = (plantilla) => {
    if (!confirm(`¿Eliminar la plantilla "${plantilla.name}"? Meta borra todos sus idiomas.`)) return;
    router.delete(route('whatsapp.templates.destroy', plantilla.id), { preserveScroll: true });
};

const estilosEstado = {
    APPROVED: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
    PENDING: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    REJECTED: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    PAUSED: 'bg-gray-100 text-gray-700 dark:bg-zinc-800 dark:text-zinc-300',
    DISABLED: 'bg-gray-100 text-gray-700 dark:bg-zinc-800 dark:text-zinc-300',
};
</script>

<template>
    <Head title="Plantillas de WhatsApp" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-zinc-100 leading-tight flex items-center gap-2">
                <DocumentTextIcon class="h-6 w-6" />
                Plantillas de WhatsApp
            </h2>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <div
                    v-if="!cuentas.length"
                    class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg p-6 flex items-start gap-3"
                >
                    <ExclamationTriangleIcon class="h-5 w-5 shrink-0 mt-0.5 text-amber-600" />
                    <div class="text-sm text-gray-600 dark:text-zinc-400">
                        No hay ninguna cuenta de WhatsApp conectada todavía. Conecta la WABA de un
                        cliente desde su ficha para poder crear plantillas.
                    </div>
                </div>

                <template v-else>
                    <!-- Cuenta -->
                    <div class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg p-6">
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <label class="block">
                                <span class="text-sm font-medium text-gray-700 dark:text-zinc-300">Cuenta</span>
                                <select
                                    :value="cuentaId"
                                    @change="cambiarCuenta(Number($event.target.value))"
                                    class="mt-1 block text-sm rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                                >
                                    <option v-for="c in cuentas" :key="c.id" :value="c.id">
                                        {{ c.name }} — WABA {{ c.waba_id }}
                                    </option>
                                </select>
                            </label>

                            <div class="flex gap-2">
                                <button
                                    @click="sincronizar"
                                    class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 dark:border-zinc-700 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300"
                                >
                                    <ArrowPathIcon class="h-4 w-4" />
                                    Sincronizar con Meta
                                </button>
                                <button
                                    @click="mostrarForm = !mostrarForm"
                                    class="rounded-md bg-[#264ab3] px-3 py-2 text-sm text-white"
                                >
                                    {{ mostrarForm ? 'Cancelar' : 'Nueva plantilla' }}
                                </button>
                            </div>
                        </div>

                        <p v-if="cuenta?.numeros?.length" class="mt-2 text-xs text-gray-500">
                            Números: {{ cuenta.numeros.join(', ') }}
                        </p>
                    </div>

                    <!-- Crear -->
                    <form
                        v-if="mostrarForm"
                        @submit.prevent="crear"
                        class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg p-6 space-y-4"
                    >
                        <h3 class="font-semibold text-gray-900 dark:text-zinc-100">Nueva plantilla</h3>
                        <p class="text-sm text-gray-600 dark:text-zinc-400">
                            Meta la revisa antes de aprobarla; puede tardar. Mientras esté en revisión
                            no se puede enviar.
                        </p>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <label class="block sm:col-span-1">
                                <span class="text-sm font-medium text-gray-700 dark:text-zinc-300">Nombre</span>
                                <input
                                    v-model="form.name"
                                    placeholder="pedido_listo"
                                    class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                                />
                                <span class="text-xs text-gray-500">minúsculas, números y _</span>
                                <span v-if="form.errors.name" class="block text-xs text-red-600">{{ form.errors.name }}</span>
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-gray-700 dark:text-zinc-300">Idioma</span>
                                <input
                                    v-model="form.language"
                                    placeholder="es_MX"
                                    class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                                />
                                <span v-if="form.errors.language" class="block text-xs text-red-600">{{ form.errors.language }}</span>
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-gray-700 dark:text-zinc-300">Categoría</span>
                                <select
                                    v-model="form.category"
                                    class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                                >
                                    <option v-for="c in categorias" :key="c" :value="c">{{ c }}</option>
                                </select>
                            </label>
                        </div>

                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-zinc-300">
                                Encabezado <span class="font-normal text-gray-500">(opcional)</span>
                            </span>
                            <input
                                v-model="form.header"
                                maxlength="60"
                                class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                            />
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-zinc-300">Cuerpo</span>
                            <textarea
                                v-model="form.body"
                                rows="4"
                                maxlength="1024"
                                :placeholder="ejemploCuerpo"
                                class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                            ></textarea>
                            <div class="mt-1 rounded-md bg-blue-50 dark:bg-blue-900/20 p-3 text-xs text-blue-900 dark:text-blue-200">
                                <p class="font-semibold">¿Qué son las llaves dobles?</p>
                                <p class="mt-1">
                                    Son <strong>huecos</strong>. El texto de la plantilla es fijo —Meta lo
                                    aprueba una vez y no cambia—, pero los huecos se rellenan en el momento
                                    de enviar, con el dato de cada contacto.
                                </p>
                                <p class="mt-1.5">
                                    Escribe <code v-pre>{{1}}</code> donde vaya el primer dato variable,
                                    <code v-pre>{{2}}</code> el segundo, y así. Numéralos en orden y sin saltarte
                                    ninguno.
                                </p>
                                <p class="mt-1.5">
                                    Ejemplo: <code>{{ ejemploCuerpo }}</code><br />
                                    Al enviar eliges el nombre y el pedido; el resto del texto siempre es igual.
                                </p>
                            </div>
                            <span class="text-xs text-gray-500">
                                Huecos detectados: <strong>{{ variables }}</strong>
                            </span>
                            <span v-if="form.errors.body" class="block text-xs text-red-600">{{ form.errors.body }}</span>
                        </label>

                        <!-- Meta exige un ejemplo por variable: el revisor humano
                             los lee para entender qué va en cada hueco. Sin ellos
                             la plantilla se crea y se rechaza horas después. -->
                        <div v-if="variables" class="rounded-md bg-gray-50 dark:bg-zinc-800/50 p-3 space-y-2">
                            <p class="text-sm font-medium text-gray-700 dark:text-zinc-300">
                                Ejemplos para la revisión de Meta
                            </p>
                            <p class="text-xs text-gray-500">
                                Un valor de muestra por variable. Meta los usa para entender la
                                plantilla; no se envían a nadie.
                            </p>
                            <label v-for="n in variables" :key="n" class="block">
                                <span class="text-xs text-gray-600 dark:text-zinc-400">
                                    Ejemplo para <code v-pre>{{</code>{{ n }}<code v-pre>}}</code>
                                </span>
                                <input
                                    v-model="form.ejemplos[n - 1]"
                                    :placeholder="n === 1 ? 'Ana' : 'A-42'"
                                    class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                                />
                            </label>
                        </div>

                        <!-- Ver el mensaje ya sustituido explica los huecos mejor
                             que cualquier texto de ayuda. -->
                        <div v-if="form.body">
                            <span class="text-sm font-medium text-gray-700 dark:text-zinc-300">
                                Así lo verá el contacto
                            </span>
                            <div class="mt-1 max-w-md rounded-2xl rounded-bl-sm bg-[#dcf8c6] dark:bg-emerald-900/40 px-3 py-2 text-sm text-gray-900 dark:text-zinc-100">
                                <p v-if="form.header" class="font-semibold">{{ form.header }}</p>
                                <p class="whitespace-pre-wrap break-words">{{ vistaPreviaCuerpo }}</p>
                                <p v-if="form.footer" class="mt-1 text-xs opacity-60">{{ form.footer }}</p>
                            </div>
                            <p v-if="variables && !ejemplosCompletos" class="mt-1 text-xs text-amber-600">
                                Los huecos sin ejemplo se quedan como <code v-pre>{{n}}</code>.
                            </p>
                        </div>

                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-zinc-300">
                                Pie <span class="font-normal text-gray-500">(opcional)</span>
                            </span>
                            <input
                                v-model="form.footer"
                                maxlength="60"
                                class="mt-1 block w-full text-sm rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                            />
                        </label>

                        <button
                            type="submit"
                            :disabled="form.processing || !form.name || !form.body || !ejemplosCompletos"
                            class="rounded-md bg-[#264ab3] px-4 py-2 text-sm text-white disabled:opacity-50"
                        >
                            {{ form.processing ? 'Enviando…' : 'Enviar a revisión' }}
                        </button>
                    </form>

                    <!-- Listado -->
                    <div class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-semibold text-gray-900 dark:text-zinc-100">
                            Plantillas de esta cuenta
                        </h3>

                        <p v-if="!plantillas.length" class="mt-2 text-sm text-gray-500">
                            No hay plantillas. Sincroniza con Meta o crea la primera.
                        </p>

                        <ul v-else class="mt-4 divide-y divide-gray-100 dark:divide-zinc-800">
                            <li v-for="p in plantillas" :key="p.id" class="py-3 flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="flex flex-wrap items-center gap-2 text-sm font-medium text-gray-900 dark:text-zinc-100">
                                        {{ p.name }}
                                        <span class="text-xs font-normal text-gray-500">{{ p.language }} · {{ p.category }}</span>
                                        <span :class="['text-[10px] px-1.5 py-0.5 rounded-full', estilosEstado[p.status]]">
                                            {{ p.status }}
                                        </span>
                                    </p>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400 whitespace-pre-wrap break-words">
                                        {{ p.body }}
                                    </p>
                                    <p v-if="p.body_variables" class="text-xs text-gray-500">
                                        {{ p.body_variables }} variable(s) por rellenar al enviar
                                    </p>
                                    <p v-if="p.rejected_reason" class="mt-1 text-xs text-red-600">
                                        Meta la rechazó: {{ p.rejected_reason }}
                                    </p>
                                </div>
                                <button
                                    @click="eliminar(p)"
                                    class="shrink-0 text-gray-400 hover:text-red-600"
                                    title="Eliminar"
                                >
                                    <TrashIcon class="h-4 w-4" />
                                </button>
                            </li>
                        </ul>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
