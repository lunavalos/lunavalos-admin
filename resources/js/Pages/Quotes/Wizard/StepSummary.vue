<script setup>
import { computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    modelValue: { type: Object, required: true },
    selectedPackage: { type: Object, default: null },
    selectedAddons: { type: Array, required: true },
    cycleLabels: { type: Object, required: true },
    submitting: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'submit', 'back']);

const fmt = (n) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n) || 0);
const pct = (n) => `${Number(n || 0).toFixed(2)}%`;

const subtotal = computed(() => {
    const pkg = Number(props.selectedPackage?.price || 0);
    const adds = props.selectedAddons.reduce((s, a) => s + Number(a.unit_price) * Number(a.quantity), 0);
    return pkg + adds;
});
const taxableBase = computed(() => Math.max(0, subtotal.value - Number(props.modelValue.discount_amount || 0)));

const ivaAmount = computed(() => props.modelValue.applies_iva           ? taxableBase.value * (Number(props.modelValue.iva_rate || 0) / 100)             : 0);
const isrRet    = computed(() => props.modelValue.applies_isr_retention ? taxableBase.value * (Number(props.modelValue.isr_retention_rate || 0) / 100)  : 0);
const ivaRet    = computed(() => props.modelValue.applies_iva_retention ? taxableBase.value * (Number(props.modelValue.iva_retention_rate || 0) / 100)  : 0);

const total = computed(() => Math.max(0, taxableBase.value + ivaAmount.value - isrRet.value - ivaRet.value));

const update = (patch) => emit('update:modelValue', { ...props.modelValue, ...patch });
</script>

<template>
    <div class="space-y-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">4. Resumen y confirmación</h3>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-4">
                <div class="rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
                    <h4 class="font-semibold mb-2">Paquete</h4>
                    <div v-if="selectedPackage" class="flex justify-between">
                        <div>
                            <div class="font-bold">{{ selectedPackage.name }}</div>
                            <div class="text-xs text-gray-500">Plan: {{ modelValue.package_payment_plan_months }} meses</div>
                        </div>
                        <div class="font-bold text-primary">{{ fmt(selectedPackage.price) }}</div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
                    <h4 class="font-semibold mb-2">Servicios adicionales</h4>
                    <ul v-if="selectedAddons.length" class="divide-y divide-gray-100 dark:divide-zinc-800">
                        <li v-for="a in selectedAddons" :key="a.service_addon_id" class="py-2 flex justify-between text-sm">
                            <div>
                                <span class="font-medium">{{ a.name }}</span>
                                <span class="text-xs text-gray-500 ml-2">{{ cycleLabels[a.billing_cycle] || a.billing_cycle }} · x{{ a.quantity }}</span>
                            </div>
                            <div class="font-semibold">{{ fmt(a.unit_price * a.quantity) }}</div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-400">Sin addons.</p>
                </div>

                <div>
                    <InputLabel value="Observaciones internas" />
                    <textarea
                        :value="modelValue.observations"
                        @input="update({ observations: $event.target.value })"
                        rows="3"
                        class="mt-1 block w-full rounded border-gray-300 dark:border-zinc-600 dark:bg-zinc-900"
                    />
                </div>

                <div>
                    <InputLabel value="Notas visibles en el PDF" />
                    <textarea
                        :value="modelValue.notes"
                        @input="update({ notes: $event.target.value })"
                        rows="3"
                        class="mt-1 block w-full rounded border-gray-300 dark:border-zinc-600 dark:bg-zinc-900"
                    />
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <Checkbox :checked="modelValue.include_payment_terms" @update:checked="v => update({ include_payment_terms: v })" />
                    Incluir condiciones de pago en el PDF
                </label>
            </div>

            <aside class="rounded-lg border border-gray-200 dark:border-zinc-700 p-4 bg-gray-50 dark:bg-zinc-800/40 h-fit sticky top-4 space-y-3">
                <h4 class="font-semibold">Totales</h4>

                <div class="flex justify-between text-sm">
                    <span>Subtotal</span><span>{{ fmt(subtotal) }}</span>
                </div>

                <div>
                    <InputLabel value="Descuento" />
                    <TextInput type="number" min="0"
                        :modelValue="modelValue.discount_amount"
                        @update:modelValue="v => update({ discount_amount: v })"
                        class="w-full" />
                </div>

                <div class="flex justify-between text-sm border-t pt-2 border-gray-200 dark:border-zinc-700">
                    <span>Base gravable</span><span>{{ fmt(taxableBase) }}</span>
                </div>

                <div v-if="modelValue.applies_iva" class="flex justify-between text-sm text-blue-700 dark:text-blue-300">
                    <span>+ IVA {{ pct(modelValue.iva_rate) }}</span><span>{{ fmt(ivaAmount) }}</span>
                </div>
                <div v-if="modelValue.applies_isr_retention" class="flex justify-between text-sm text-rose-600 dark:text-rose-400">
                    <span>− Ret. ISR {{ pct(modelValue.isr_retention_rate) }}</span><span>-{{ fmt(isrRet) }}</span>
                </div>
                <div v-if="modelValue.applies_iva_retention" class="flex justify-between text-sm text-rose-600 dark:text-rose-400">
                    <span>− Ret. IVA {{ pct(modelValue.iva_retention_rate) }}</span><span>-{{ fmt(ivaRet) }}</span>
                </div>

                <div class="flex justify-between text-base font-bold border-t pt-2 border-gray-200 dark:border-zinc-700">
                    <span>Total</span><span class="text-green-600">{{ fmt(total) }}</span>
                </div>

                <div>
                    <InputLabel value="Guardar como" />
                    <select :value="modelValue.status" @change="update({ status: $event.target.value })"
                        class="mt-1 block w-full rounded border-gray-300 dark:border-zinc-600 dark:bg-zinc-900">
                        <option value="Borrador">Borrador</option>
                        <option value="Enviada">Enviada</option>
                    </select>
                </div>
            </aside>
        </div>

        <div class="flex justify-between">
            <button type="button" @click="$emit('back')" class="px-5 py-2 rounded border border-gray-300 dark:border-zinc-600">← Atrás</button>
            <button
                type="button"
                :disabled="submitting"
                @click="$emit('submit')"
                class="px-6 py-2 rounded bg-green-600 hover:bg-green-700 text-white font-semibold disabled:opacity-40"
            >{{ submitting ? 'Guardando…' : 'Guardar cotización' }}</button>
        </div>
    </div>
</template>
