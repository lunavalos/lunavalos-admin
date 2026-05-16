<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: Object, required: true },
    addonsByCategory: { type: Object, required: true },
    categoryLabels: { type: Object, required: true },
    cycleLabels: { type: Object, required: true },
    requiredCategories: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue', 'next', 'back']);

const fmt = (n) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n) || 0);

const findRow = (id) => props.modelValue.addons.find(a => a.service_addon_id === id);

const toggle = (addon, checked) => {
    const list = [...props.modelValue.addons];
    const idx = list.findIndex(a => a.service_addon_id === addon.id);
    if (checked) {
        if (idx === -1) {
            list.push({
                service_addon_id: addon.id,
                quantity: 1,
                is_required: props.requiredCategories.includes(addon.category),
            });
        }
    } else if (idx !== -1) {
        list.splice(idx, 1);
    }
    emit('update:modelValue', { ...props.modelValue, addons: list });
};

const setQty = (id, qty) => {
    const list = props.modelValue.addons.map(a =>
        a.service_addon_id === id ? { ...a, quantity: Math.max(1, Number(qty) || 1) } : a
    );
    emit('update:modelValue', { ...props.modelValue, addons: list });
};

// Para cada categoría obligatoria: ¿está cubierta con al menos 1 addon seleccionado?
const categoryStatus = computed(() => {
    return props.requiredCategories.map((cat) => {
        const groupIds = (props.addonsByCategory[cat] || []).map(a => a.id);
        const met = props.modelValue.addons.some(a => groupIds.includes(a.service_addon_id));
        return { cat, met };
    });
});

const allRequiredMet = computed(() => categoryStatus.value.every(c => c.met));
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">2. Agrega servicios adicionales</h3>
            <div v-if="requiredCategories.length" class="flex flex-wrap gap-2">
                <span
                    v-for="status in categoryStatus" :key="status.cat"
                    class="text-xs px-3 py-1 rounded-full font-semibold"
                    :class="status.met ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                >
                    {{ status.met ? '✓' : '!' }} {{ categoryLabels[status.cat] || status.cat }}
                </span>
            </div>
        </div>

        <div v-for="(group, key) in addonsByCategory" :key="key" class="border border-gray-200 dark:border-zinc-700 rounded-lg">
            <div class="px-4 py-2 bg-gray-50 dark:bg-zinc-800/60 font-semibold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                {{ categoryLabels[key] || key }}
                <span v-if="requiredCategories.includes(key)" class="text-[10px] uppercase bg-amber-200 text-amber-900 px-2 py-0.5 rounded">Obligatorio</span>
            </div>
            <ul class="divide-y divide-gray-100 dark:divide-zinc-800">
                <li v-for="addon in group" :key="addon.id" class="flex items-center gap-4 p-3">
                    <input
                        type="checkbox"
                        :checked="!!findRow(addon.id)"
                        @change="toggle(addon, $event.target.checked)"
                        class="rounded border-gray-300"
                    />
                    <div class="flex-1">
                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ addon.name }}</div>
                        <div class="text-xs text-gray-500">{{ cycleLabels[addon.billing_cycle] || addon.billing_cycle }} · {{ fmt(addon.price) }}</div>
                    </div>
                    <input
                        v-if="findRow(addon.id)"
                        type="number"
                        min="1"
                        :value="findRow(addon.id).quantity"
                        @input="setQty(addon.id, $event.target.value)"
                        class="w-20 rounded border-gray-300 dark:border-zinc-600 dark:bg-zinc-900"
                    />
                </li>
            </ul>
        </div>

        <div class="flex justify-between">
            <button type="button" @click="$emit('back')" class="px-5 py-2 rounded border border-gray-300 dark:border-zinc-600">← Atrás</button>
            <button
                type="button"
                :disabled="!allRequiredMet"
                @click="$emit('next')"
                class="px-5 py-2 rounded bg-primary text-white font-semibold disabled:opacity-40"
            >Siguiente →</button>
        </div>
    </div>
</template>
