<script setup>
import { useMoney } from '@/Composables/useMoney';
import { computed } from 'vue';

const props = defineProps({
    services: { type: Array, required: true },
    modelValue: { type: Object, required: true },
    maxPaymentMonths: { type: Number, default: 24 },
    categoryLabels: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['update:modelValue', 'next']);

const { fmt: _fmt, convert, options: currencyOptions, defaultCurrency, hasRate } = useMoney();
const fmt = (n, c) => _fmt(n, c);

// Moneda activa de la cotización (la del modelValue).
const quoteCurrency = computed(() => (props.modelValue.currency || defaultCurrency.value || 'MXN').toUpperCase());

// Devuelve la moneda nativa del servicio (default MXN si el catálogo es viejo).
const svcCurrency = (svc) => (svc?.currency || 'MXN').toUpperCase();

// Devuelve el precio convertido a la moneda de la cotización (o null si falta tasa).
const convertedPrice = (svc) => {
    const f = svcCurrency(svc);
    if (f === quoteCurrency.value) return null;
    return convert(Number(svc.price || 0), f, quoteCurrency.value);
};

const setCurrency = (cur) => {
    emit('update:modelValue', { ...props.modelValue, currency: cur });
};

const packages = computed(() => props.services.filter(s => s.is_package));
const others = computed(() => props.services.filter(s => !s.is_package));

const selected = computed(() => props.services.find(s => s.id === props.modelValue.package_service_id));

const requiredCategoriesOf = (svc) => {
    if (!svc) return [];
    if (Array.isArray(svc.required_addon_categories_list) && svc.required_addon_categories_list.length) {
        return svc.required_addon_categories_list;
    }
    if (Array.isArray(svc.required_addon_categories) && svc.required_addon_categories.length) {
        return svc.required_addon_categories;
    }
    return svc.required_addon_category ? [svc.required_addon_category] : [];
};

const select = (svc) => {
    emit('update:modelValue', {
        ...props.modelValue,
        package_service_id: svc.id,
        package_payment_plan_months: Math.min(svc.payment_plan_months || 1, props.maxPaymentMonths),
    });
};

const setMonths = (v) => {
    emit('update:modelValue', { ...props.modelValue, package_payment_plan_months: Number(v) });
};
</script>

<template>
    <div class="space-y-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">1. Selecciona el paquete o servicio</h3>

        <!-- Selector global de moneda de la cotización -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-900/60">
            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">Moneda de la cotización</label>
            <select
                :value="quoteCurrency"
                @change="setCurrency($event.target.value)"
                class="border-gray-300 dark:border-zinc-700 rounded-md text-sm bg-white dark:bg-zinc-950"
            >
                <option v-for="opt in currencyOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <p class="text-xs text-gray-500 dark:text-zinc-400">
                Los servicios y addons en otra moneda se convertirán automáticamente a {{ quoteCurrency }} usando la tasa Banxico más reciente.
            </p>
        </div>

        <div v-if="packages.length === 0" class="p-4 rounded bg-yellow-50 text-yellow-800 text-sm">
            No hay servicios marcados como paquete. Marca un servicio con <code>is_package</code> para mostrarlo aquí.
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="svc in [...packages, ...others]"
                :key="svc.id"
                @click="select(svc)"
                class="cursor-pointer rounded-lg border-2 p-4 transition shadow-sm hover:shadow-md bg-white dark:bg-zinc-900"
                :class="modelValue.package_service_id === svc.id
                    ? 'border-primary ring-2 ring-primary/30'
                    : 'border-gray-200 dark:border-zinc-700'"
            >
                <div class="flex items-start justify-between mb-2 gap-2">
                    <h4 class="font-bold text-gray-900 dark:text-gray-100">{{ svc.name }}</h4>
                    <span v-if="svc.is_package" class="text-[10px] uppercase font-bold bg-primary text-white px-2 py-0.5 rounded">Paquete</span>
                </div>
                <p v-if="svc.description" class="text-xs text-gray-500 dark:text-gray-400 line-clamp-3">{{ svc.description }}</p>

                <!-- Sub-servicios incluidos cuando es paquete -->
                <div v-if="svc.is_package && svc.services && svc.services.length" class="mt-3 p-2 rounded bg-gray-50 dark:bg-zinc-950 border border-gray-100 dark:border-zinc-800">
                    <div class="text-[10px] uppercase font-bold text-gray-500 dark:text-zinc-400 mb-1">Incluye {{ svc.services.length }} servicios:</div>
                    <ul class="space-y-0.5">
                        <li v-for="sub in svc.services" :key="sub.id" class="text-xs text-gray-700 dark:text-gray-300 flex justify-between gap-2">
                            <span class="truncate">• {{ sub.name }}</span>
                            <span class="text-gray-400 dark:text-zinc-500 whitespace-nowrap">{{ fmt(sub.price) }}</span>
                        </li>
                    </ul>
                </div>

                <ul v-if="svc.features?.length" class="mt-3 space-y-1">
                    <li v-for="f in svc.features.slice(0, 4)" :key="f.id" class="text-xs text-gray-700 dark:text-gray-300 flex gap-1">
                        <span class="text-green-500">✓</span>{{ f.label }}
                    </li>
                    <li v-if="svc.features.length > 4" class="text-xs italic text-gray-400">+ {{ svc.features.length - 4 }} más…</li>
                </ul>

                <div class="mt-4 flex items-end justify-between gap-2">
                    <div>
                        <div class="text-lg font-bold text-primary">{{ fmt(svc.price, svcCurrency(svc)) }}</div>
                        <div v-if="convertedPrice(svc) !== null" class="text-[11px] text-gray-500 dark:text-zinc-400">
                            ≈ {{ fmt(convertedPrice(svc), quoteCurrency) }}
                        </div>
                        <div v-else-if="svcCurrency(svc) !== quoteCurrency && !hasRate(svcCurrency(svc))" class="text-[10px] text-amber-600">
                            Sin tasa para {{ svcCurrency(svc) }} → {{ quoteCurrency }}
                        </div>
                    </div>
                    <div v-if="requiredCategoriesOf(svc).length" class="text-right">
                        <div class="text-[9px] uppercase font-bold text-amber-700">Requiere addon de:</div>
                        <div class="text-[10px] text-amber-600 font-semibold">
                            {{ requiredCategoriesOf(svc).map(c => categoryLabels[c] || c).join(', ') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detalle del paquete seleccionado -->
        <div v-if="selected" class="p-4 rounded border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800/40 space-y-4">
            <div v-if="selected.is_package && selected.services && selected.services.length">
                <h5 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">Servicios incluidos en {{ selected.name }}</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <div v-for="sub in selected.services" :key="sub.id" class="p-2 rounded bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800">
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ sub.name }}</span>
                            <span class="text-xs font-semibold text-primary whitespace-nowrap">{{ fmt(sub.price) }}</span>
                        </div>
                        <p v-if="sub.description" class="text-xs text-gray-500 dark:text-zinc-400 mt-1 line-clamp-2">{{ sub.description }}</p>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-200">Plan de pago (meses)</label>
                <input
                    type="number"
                    min="1"
                    :max="maxPaymentMonths"
                    :value="modelValue.package_payment_plan_months"
                    @input="setMonths($event.target.value)"
                    class="w-32 rounded border-gray-300 dark:border-zinc-600 dark:bg-zinc-900"
                />
                <p class="text-xs text-gray-500 mt-1">Entre 1 y {{ maxPaymentMonths }} meses. Sugerido para este servicio: {{ selected.payment_plan_months || 1 }}.</p>
            </div>
        </div>

        <div class="flex justify-end">
            <button
                type="button"
                :disabled="!modelValue.package_service_id"
                @click="$emit('next')"
                class="px-5 py-2 rounded bg-primary text-white font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
            >Siguiente →</button>
        </div>
    </div>
</template>
