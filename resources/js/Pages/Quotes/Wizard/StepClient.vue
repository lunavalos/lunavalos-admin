<script setup>
import { computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    modelValue: { type: Object, required: true },
    clients: { type: Array, required: true },
    taxRegimes: { type: Object, default: () => ({}) },
    cfdiUses: { type: Object, default: () => ({}) },
    taxDefaults: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['update:modelValue', 'next', 'back']);

const update = (patch) => emit('update:modelValue', { ...props.modelValue, ...patch });

const onPickClient = (e) => {
    const id = e.target.value ? Number(e.target.value) : null;
    if (!id) {
        update({ client_id: null });
        return;
    }
    const c = props.clients.find(x => x.id === id);
    if (!c) return;
    update({
        client_id: c.id,
        client_name: c.business_name || props.modelValue.client_name,
        contact_name: c.contact_name || props.modelValue.contact_name,
        email: c.email || props.modelValue.email,
        phone: c.phone || props.modelValue.phone,
        // Hereda config fiscal (override permitido en este mismo paso).
        tax_regime: c.tax_regime || '',
        applies_iva: c.applies_iva ?? true,
        iva_rate: c.iva_rate != null ? Number(c.iva_rate) : (props.taxDefaults.iva_rate ?? 16),
        applies_isr_retention: c.applies_isr_retention ?? false,
        isr_retention_rate: c.isr_retention_rate != null ? Number(c.isr_retention_rate) : 0,
        applies_iva_retention: c.applies_iva_retention ?? false,
        iva_retention_rate: c.iva_retention_rate != null ? Number(c.iva_retention_rate) : 0,
    });
};

// Presets típicos.
const presetPMtoPM       = () => update({ applies_iva: true, iva_rate: 16, applies_isr_retention: false, isr_retention_rate: 0, applies_iva_retention: false, iva_retention_rate: 0 });
const presetPMtoPF       = () => update({ applies_iva: true, iva_rate: 16, applies_isr_retention: true,  isr_retention_rate: props.taxDefaults.isr_ret_honorarios ?? 10, applies_iva_retention: true, iva_retention_rate: props.taxDefaults.iva_ret_2_3 ?? 10.6667 });
const presetResicoPF     = () => update({ applies_iva: true, iva_rate: 16, applies_isr_retention: true,  isr_retention_rate: props.taxDefaults.isr_ret_resico_pf ?? 1.25, applies_iva_retention: false, iva_retention_rate: 0 });
const presetFrontera     = () => update({ applies_iva: true, iva_rate: props.taxDefaults.iva_rate_frontera ?? 8, applies_isr_retention: false, isr_retention_rate: 0, applies_iva_retention: false, iva_retention_rate: 0 });
const presetSinImpuestos = () => update({ applies_iva: false, iva_rate: 0, applies_isr_retention: false, isr_retention_rate: 0, applies_iva_retention: false, iva_retention_rate: 0 });

const canNext = computed(() => props.modelValue.client_name && props.modelValue.issue_date && props.modelValue.valid_until);
</script>

<template>
    <div class="space-y-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">3. Datos del cliente y configuración fiscal</h3>

        <div>
            <InputLabel value="Cliente existente (opcional)" />
            <select
                :value="modelValue.client_id || ''"
                @change="onPickClient"
                class="mt-1 block w-full rounded border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 text-gray-900 dark:text-gray-100"
            >
                <option value="">— Cotización nueva sin cliente registrado —</option>
                <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.business_name }}</option>
            </select>
            <p class="text-xs text-gray-500 mt-1">Al seleccionar un cliente se precarga su configuración SAT; podrás ajustarla solo para esta cotización.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <InputLabel value="Razón social / Cliente *" />
                <TextInput :modelValue="modelValue.client_name" @update:modelValue="v => update({ client_name: v })" class="w-full" />
                <InputError :message="errors.client_name" />
            </div>
            <div>
                <InputLabel value="Contacto" />
                <TextInput :modelValue="modelValue.contact_name" @update:modelValue="v => update({ contact_name: v })" class="w-full" />
            </div>
            <div>
                <InputLabel value="Email" />
                <TextInput type="email" :modelValue="modelValue.email" @update:modelValue="v => update({ email: v })" class="w-full" />
                <InputError :message="errors.email" />
            </div>
            <div>
                <InputLabel value="Teléfono" />
                <TextInput :modelValue="modelValue.phone" @update:modelValue="v => update({ phone: v })" class="w-full" />
            </div>
            <div>
                <InputLabel value="Fecha de emisión *" />
                <TextInput type="date" :modelValue="modelValue.issue_date" @update:modelValue="v => update({ issue_date: v })" class="w-full" />
                <InputError :message="errors.issue_date" />
            </div>
            <div>
                <InputLabel value="Válida hasta *" />
                <TextInput type="date" :modelValue="modelValue.valid_until" @update:modelValue="v => update({ valid_until: v })" class="w-full" />
                <InputError :message="errors.valid_until" />
            </div>
            <div class="md:col-span-2">
                <InputLabel value="Descripción del compromiso (texto libre para PDF)" />
                <TextInput :modelValue="modelValue.duration" @update:modelValue="v => update({ duration: v })" placeholder="Ej. Contrato a 12 meses con renovación automática" class="w-full" />
                <p class="text-xs text-gray-500 mt-1">
                    Solo descriptivo. El <strong>plan de pagos real</strong> ({{ modelValue.package_payment_plan_months }} {{ modelValue.package_payment_plan_months === 1 ? 'mensualidad' : 'mensualidades' }})
                    se configura en el paso <em>Paquete</em>.
                </p>
            </div>
        </div>

        <!-- Configuración fiscal -->
        <div class="rounded-lg border border-blue-200 dark:border-blue-900/50 bg-blue-50/40 dark:bg-blue-900/10 p-4 space-y-4">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <h4 class="font-semibold text-blue-900 dark:text-blue-300">Configuración fiscal (CFDI 4.0)</h4>
                <div class="flex flex-wrap gap-1 text-xs">
                    <button type="button" @click="presetPMtoPM"        class="px-2 py-1 rounded bg-white border border-gray-300 hover:bg-gray-50 dark:bg-zinc-900 dark:border-zinc-600">PM → PM (IVA 16%)</button>
                    <button type="button" @click="presetPMtoPF"        class="px-2 py-1 rounded bg-white border border-gray-300 hover:bg-gray-50 dark:bg-zinc-900 dark:border-zinc-600">PM → PF honorarios</button>
                    <button type="button" @click="presetResicoPF"      class="px-2 py-1 rounded bg-white border border-gray-300 hover:bg-gray-50 dark:bg-zinc-900 dark:border-zinc-600">RESICO PF</button>
                    <button type="button" @click="presetFrontera"      class="px-2 py-1 rounded bg-white border border-gray-300 hover:bg-gray-50 dark:bg-zinc-900 dark:border-zinc-600">Zona fronteriza (IVA 8%)</button>
                    <button type="button" @click="presetSinImpuestos"  class="px-2 py-1 rounded bg-white border border-gray-300 hover:bg-gray-50 dark:bg-zinc-900 dark:border-zinc-600">Sin impuestos</button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <InputLabel value="Régimen fiscal" />
                    <select
                        :value="modelValue.tax_regime"
                        @change="update({ tax_regime: $event.target.value })"
                        class="mt-1 block w-full rounded border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 text-gray-900 dark:text-gray-100"
                    >
                        <option value="">— No especificado —</option>
                        <option v-for="(reg, code) in taxRegimes" :key="code" :value="code">{{ code }} · {{ reg.label }}</option>
                    </select>
                </div>

                <div class="grid grid-cols-[auto_1fr] items-end gap-3">
                    <label class="flex items-center gap-2 pb-2">
                        <Checkbox :checked="modelValue.applies_iva" @update:checked="v => update({ applies_iva: v })" />
                        <span class="text-sm">Trasladar IVA</span>
                    </label>
                    <div>
                        <InputLabel value="Tasa IVA (%)" />
                        <TextInput type="number" step="0.01" min="0" max="100"
                            :modelValue="modelValue.iva_rate"
                            @update:modelValue="v => update({ iva_rate: Number(v) })"
                            class="w-full" :disabled="!modelValue.applies_iva" />
                    </div>
                </div>

                <div class="grid grid-cols-[auto_1fr] items-end gap-3">
                    <label class="flex items-center gap-2 pb-2">
                        <Checkbox :checked="modelValue.applies_isr_retention" @update:checked="v => update({ applies_isr_retention: v })" />
                        <span class="text-sm">Ret. ISR</span>
                    </label>
                    <div>
                        <InputLabel value="Tasa ISR ret. (%)" />
                        <TextInput type="number" step="0.0001" min="0" max="100"
                            :modelValue="modelValue.isr_retention_rate"
                            @update:modelValue="v => update({ isr_retention_rate: Number(v) })"
                            class="w-full" :disabled="!modelValue.applies_isr_retention" />
                    </div>
                </div>

                <div class="grid grid-cols-[auto_1fr] items-end gap-3 md:col-start-2">
                    <label class="flex items-center gap-2 pb-2">
                        <Checkbox :checked="modelValue.applies_iva_retention" @update:checked="v => update({ applies_iva_retention: v })" />
                        <span class="text-sm">Ret. IVA</span>
                    </label>
                    <div>
                        <InputLabel value="Tasa IVA ret. (%)" />
                        <TextInput type="number" step="0.0001" min="0" max="100"
                            :modelValue="modelValue.iva_retention_rate"
                            @update:modelValue="v => update({ iva_retention_rate: Number(v) })"
                            class="w-full" :disabled="!modelValue.applies_iva_retention" />
                    </div>
                </div>
            </div>

            <p class="text-xs text-gray-500">
                Esta configuración se guarda como <strong>snapshot</strong> en la cotización. Si cambias los datos fiscales del cliente más adelante,
                las cotizaciones anteriores no se modifican.
            </p>
        </div>

        <div class="flex justify-between">
            <button type="button" @click="$emit('back')" class="px-5 py-2 rounded border border-gray-300 dark:border-zinc-600">← Atrás</button>
            <button type="button" :disabled="!canNext" @click="$emit('next')" class="px-5 py-2 rounded bg-primary text-white font-semibold disabled:opacity-40">Siguiente →</button>
        </div>
    </div>
</template>
