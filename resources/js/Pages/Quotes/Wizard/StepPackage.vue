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

const quoteCurrency = computed(() => (props.modelValue.currency || defaultCurrency.value || 'MXN').toUpperCase());
const svcCurrency = (svc) => (svc?.currency || 'MXN').toUpperCase();
const convertedPrice = (svc) => {
    const f = svcCurrency(svc);
    if (f === quoteCurrency.value) return null;
    return convert(Number(svc.price || 0), f, quoteCurrency.value);
};
const convertedRenewal = (svc) => {
    if (!svc?.renewal_price) return null;
    const f = svcCurrency(svc);
    if (f === quoteCurrency.value) return Number(svc.renewal_price);
    return convert(Number(svc.renewal_price), f, quoteCurrency.value);
};

const setCurrency = (cur) => emit('update:modelValue', { ...props.modelValue, currency: cur });

const packages = computed(() => props.services.filter(s => s.is_package));
const others   = computed(() => props.services.filter(s => !s.is_package));
const selected = computed(() => props.services.find(s => s.id === props.modelValue.package_service_id));

const months = computed(() => Number(props.modelValue.package_payment_plan_months) || 1);

// Etiqueta y color según tipo de cobro
const billingInfo = (svc) => {
    const hasRenewal = Number(svc.renewal_price) > 0;
    if (svc.billing_type === 'monthly') {
        return { label: 'Mensualidad', color: 'bg-blue-100 text-blue-700', icon: '↻' };
    }
    if (hasRenewal) {
        return { label: 'Inicio + renovación anual', color: 'bg-amber-100 text-amber-700', icon: '⟳' };
    }
    return { label: 'Pago único', color: 'bg-gray-100 text-gray-600', icon: '○' };
};

// Vista previa del cronograma para el servicio seleccionado
const schedulePreview = computed(() => {
    if (!selected.value) return [];
    const svc      = selected.value;
    const cur      = quoteCurrency.value;
    const price    = convertedPrice(svc) ?? Number(svc.price || 0);
    const renewal  = convertedRenewal(svc);
    const n        = months.value;
    const isMonthly = svc.billing_type === 'monthly';

    const items = [];

    if (isMonthly) {
        // Mensualidad recurrente: N meses de duración
        items.push({ label: `${n} mensualidades de`, amount: price, currency: cur, note: 'cobro recurrente mensual', type: 'recurring' });
    } else {
        // Pago único o anual: dividir en N cuotas mensuales
        const perInstallment = n > 0 ? price / n : price;
        items.push({ label: 'Anticipo (1er pago)', amount: perInstallment, currency: cur, note: 'al cerrar la venta', type: 'anticipo' });
        if (n > 1) {
            items.push({ label: `${n - 1} cuota${n > 2 ? 's' : ''} mensual${n > 2 ? 'es' : ''} de`, amount: perInstallment, currency: cur, note: 'mes ' + (n > 2 ? '2 al ' + n : '2'), type: 'installment' });
        }
        if (renewal != null && renewal > 0) {
            items.push({ label: 'Renovación anual (año 2 en adelante)', amount: renewal, currency: cur, note: 'dominio · hosting · mantenimiento', type: 'renewal' });
        }
    }

    return items;
});

const requiredCategoriesOf = (svc) => {
    if (!svc) return [];
    if (Array.isArray(svc.required_addon_categories_list) && svc.required_addon_categories_list.length) return svc.required_addon_categories_list;
    if (Array.isArray(svc.required_addon_categories) && svc.required_addon_categories.length) return svc.required_addon_categories;
    return svc.required_addon_category ? [svc.required_addon_category] : [];
};

const select = (svc) => {
    emit('update:modelValue', {
        ...props.modelValue,
        package_service_id: svc.id,
        package_payment_plan_months: Math.min(svc.payment_plan_months || 1, props.maxPaymentMonths),
    });
};

const setMonths = (v) => emit('update:modelValue', { ...props.modelValue, package_payment_plan_months: Number(v) });
</script>

<template>
    <div class="space-y-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">1. Selecciona el paquete o servicio</h3>

        <!-- Moneda de la cotización -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-900/60">
            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">Moneda de la cotización</label>
            <select :value="quoteCurrency" @change="setCurrency($event.target.value)"
                    class="border-gray-300 dark:border-zinc-700 rounded-md text-sm bg-white dark:bg-zinc-950">
                <option v-for="opt in currencyOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <p class="text-xs text-gray-500 dark:text-zinc-400">
                Los servicios en otra moneda se convertirán a {{ quoteCurrency }} con la tasa Banxico.
            </p>
        </div>

        <div v-if="packages.length === 0" class="p-4 rounded bg-yellow-50 text-yellow-800 text-sm">
            No hay servicios marcados como paquete.
        </div>

        <!-- Tarjetas de servicios -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="svc in [...packages, ...others]" :key="svc.id"
                 @click="select(svc)"
                 class="cursor-pointer rounded-xl border-2 p-4 transition-all shadow-sm hover:shadow-md bg-white dark:bg-zinc-900 flex flex-col"
                 :class="modelValue.package_service_id === svc.id
                     ? 'border-primary ring-2 ring-primary/20'
                     : 'border-gray-200 dark:border-zinc-700 hover:border-primary/40'">

                <!-- Encabezado -->
                <div class="flex items-start justify-between gap-2 mb-2">
                    <h4 class="font-bold text-gray-900 dark:text-gray-100 leading-tight">{{ svc.name }}</h4>
                    <div class="flex flex-col items-end gap-1 shrink-0">
                        <span v-if="svc.is_package" class="text-[9px] uppercase font-bold bg-primary text-white px-2 py-0.5 rounded">Paquete</span>
                        <span class="text-[9px] uppercase font-bold px-2 py-0.5 rounded"
                              :class="billingInfo(svc).color">
                            {{ billingInfo(svc).icon }} {{ billingInfo(svc).label }}
                        </span>
                    </div>
                </div>

                <!-- Descripción truncada -->
                <p v-if="svc.description" class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2 mb-3">{{ svc.description }}</p>

                <!-- Sub-servicios (si es paquete) -->
                <div v-if="svc.is_package && svc.services?.length"
                     class="mb-3 p-2 rounded bg-gray-50 dark:bg-zinc-950 border border-gray-100 dark:border-zinc-800">
                    <div class="text-[10px] uppercase font-bold text-gray-500 mb-1">Incluye:</div>
                    <ul class="space-y-0.5">
                        <li v-for="sub in svc.services" :key="sub.id"
                            class="text-xs text-gray-700 dark:text-gray-300 flex justify-between gap-2">
                            <span class="truncate">• {{ sub.name }}</span>
                            <span class="text-gray-400 whitespace-nowrap">{{ fmt(sub.price) }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Features -->
                <ul v-if="svc.features?.length" class="mb-3 space-y-1">
                    <li v-for="f in svc.features.slice(0, 3)" :key="f.id"
                        class="text-xs text-gray-700 dark:text-gray-300 flex gap-1">
                        <span class="text-green-500 shrink-0">✓</span>{{ f.label }}
                    </li>
                    <li v-if="svc.features.length > 3" class="text-xs italic text-gray-400">+ {{ svc.features.length - 3 }} más…</li>
                </ul>

                <!-- Precios -->
                <div class="mt-auto pt-3 border-t border-gray-100 dark:border-zinc-800 space-y-1">
                    <!-- Precio principal -->
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-[11px] text-gray-400 uppercase font-semibold">
                            {{ svc.billing_type === 'monthly' ? 'Mensualidad' : 'Precio inicial' }}
                        </span>
                        <div class="text-right">
                            <div class="text-lg font-bold text-primary">
                                {{ fmt(svc.price, svcCurrency(svc)) }}
                                <span v-if="svc.billing_type === 'monthly'" class="text-xs font-normal text-gray-400">/mes</span>
                            </div>
                            <div v-if="convertedPrice(svc) !== null" class="text-[10px] text-gray-400">
                                ≈ {{ fmt(convertedPrice(svc), quoteCurrency) }}
                            </div>
                        </div>
                    </div>
                    <!-- Precio de renovación (si aplica) -->
                    <div v-if="Number(svc.renewal_price) > 0"
                         class="flex items-baseline justify-between gap-2 pt-1 border-t border-dashed border-amber-200 dark:border-amber-900/40">
                        <span class="text-[11px] text-amber-600 uppercase font-semibold">↻ Renovación anual</span>
                        <div class="text-right">
                            <div class="text-sm font-bold text-amber-600">
                                {{ fmt(svc.renewal_price, svcCurrency(svc)) }}<span class="text-xs font-normal">/año</span>
                            </div>
                        </div>
                    </div>
                    <!-- Addon requerido -->
                    <div v-if="requiredCategoriesOf(svc).length" class="pt-1">
                        <div class="text-[9px] uppercase font-bold text-amber-700">Requiere addon:</div>
                        <div class="text-[10px] text-amber-600 font-semibold">
                            {{ requiredCategoriesOf(svc).map(c => categoryLabels[c] || c).join(', ') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detalle del servicio seleccionado -->
        <div v-if="selected" class="rounded-xl border border-primary/30 bg-primary/5 dark:bg-primary/10 p-5 space-y-5">
            <h4 class="font-bold text-gray-800 dark:text-gray-100 text-base">
                Configuración de pago — <span class="text-primary">{{ selected.name }}</span>
            </h4>

            <!-- Sub-servicios del paquete -->
            <div v-if="selected.is_package && selected.services?.length">
                <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-2">Servicios incluidos:</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <div v-for="sub in selected.services" :key="sub.id"
                         class="p-2 rounded-lg bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800">
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ sub.name }}</span>
                            <span class="text-xs font-semibold text-primary whitespace-nowrap">{{ fmt(sub.price) }}</span>
                        </div>
                        <p v-if="sub.description" class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ sub.description }}</p>
                    </div>
                </div>
            </div>

            <!-- Plan de pago -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1">
                    {{ selected.billing_type === 'monthly' ? 'Duración de la suscripción (meses)' : 'Dividir pago inicial en (meses)' }}
                </label>
                <div class="flex items-center gap-3">
                    <input type="number" min="1" :max="maxPaymentMonths"
                           :value="modelValue.package_payment_plan_months"
                           @input="setMonths($event.target.value)"
                           class="w-24 rounded-lg border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 text-center font-bold text-lg" />
                    <span class="text-sm text-gray-500">
                        {{ selected.billing_type === 'monthly'
                            ? `mes${months !== 1 ? 'es' : ''} de suscripción`
                            : `cuota${months !== 1 ? 's' : ''} mensual${months !== 1 ? 'es' : ''}` }}
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-1">
                    Sugerido para este servicio: <strong>{{ selected.payment_plan_months || 1 }}</strong> mes{{ (selected.payment_plan_months || 1) !== 1 ? 'es' : '' }}.
                    Máximo {{ maxPaymentMonths }}.
                </p>
            </div>

            <!-- Vista previa del cronograma -->
            <div>
                <p class="text-xs font-bold text-gray-500 dark:text-zinc-400 uppercase tracking-wide mb-2">
                    Vista previa del cronograma de cobro
                </p>
                <div class="space-y-2">
                    <div v-for="(item, i) in schedulePreview" :key="i"
                         class="flex items-center justify-between gap-4 rounded-lg px-3 py-2"
                         :class="{
                             'bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800': item.type === 'anticipo',
                             'bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800': item.type === 'installment' || item.type === 'recurring',
                             'bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800': item.type === 'renewal',
                         }">
                        <div>
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ item.label }}</div>
                            <div class="text-xs text-gray-500 dark:text-zinc-400">{{ item.note }}</div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="font-bold"
                                 :class="{
                                     'text-emerald-700 dark:text-emerald-400': item.type === 'anticipo',
                                     'text-blue-700 dark:text-blue-400': item.type === 'installment' || item.type === 'recurring',
                                     'text-amber-700 dark:text-amber-400': item.type === 'renewal',
                                 }">
                                {{ fmt(item.amount, item.currency) }}
                            </div>
                            <div v-if="item.type !== 'renewal'" class="text-[10px] text-gray-400">sin impuestos</div>
                            <div v-else class="text-[10px] text-amber-500">+ impuestos según régimen</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="button" :disabled="!modelValue.package_service_id" @click="$emit('next')"
                    class="px-6 py-2.5 rounded-lg bg-primary text-white font-semibold disabled:opacity-40 disabled:cursor-not-allowed hover:bg-secondary transition-colors">
                Siguiente →
            </button>
        </div>
    </div>
</template>
