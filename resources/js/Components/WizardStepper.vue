<script setup>
import { computed } from 'vue';

const props = defineProps({
    steps: { type: Array, required: true },
    current: { type: Number, required: true },
});

const emit = defineEmits(['goto']);

const safeGoto = (idx) => {
    if (idx < props.current) emit('goto', idx);
};
</script>

<template>
    <ol class="flex w-full items-center justify-between mb-8 select-none">
        <li
            v-for="(label, idx) in steps"
            :key="idx"
            class="flex-1 flex items-center"
        >
            <button
                type="button"
                @click="safeGoto(idx)"
                :disabled="idx > current"
                class="flex items-center gap-3 group"
                :class="{ 'cursor-pointer': idx < current, 'cursor-default': idx >= current }"
            >
                <span
                    class="flex items-center justify-center w-9 h-9 rounded-full text-sm font-bold border-2 transition"
                    :class="[
                        idx < current ? 'bg-green-500 border-green-500 text-white' :
                        idx === current ? 'bg-primary border-primary text-white' :
                        'bg-white border-gray-300 text-gray-400 dark:bg-zinc-800 dark:border-zinc-700'
                    ]"
                >
                    <span v-if="idx < current">✓</span>
                    <span v-else>{{ idx + 1 }}</span>
                </span>
                <span
                    class="text-sm font-medium whitespace-nowrap"
                    :class="idx <= current ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500'"
                >
                    {{ label }}
                </span>
            </button>
            <div
                v-if="idx < steps.length - 1"
                class="flex-1 h-0.5 mx-3"
                :class="idx < current ? 'bg-green-500' : 'bg-gray-200 dark:bg-zinc-700'"
            />
        </li>
    </ol>
</template>
