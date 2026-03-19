<script setup>
import { ref, watch, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashMessage = computed(() => page.props.flash?.message);
const flashError = computed(() => page.props.flash?.error);

const showToast = ref(false);
const message = ref('');
const type = ref('success'); // 'success' or 'error'

watch([flashSuccess, flashMessage, flashError], ([s, m, e]) => {
    const val = s || m || e;
    if (val) {
        message.value = val;
        type.value = e ? 'error' : 'success';
        showToast.value = true;
        setTimeout(() => {
            showToast.value = false;
        }, 4000);
    }
}, { immediate: true });
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transform transition ease-out duration-300"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showToast" 
                class="fixed top-5 right-5 z-[9999] max-w-sm w-full bg-white shadow-2xl rounded-2xl border-l-4 p-4 flex items-center"
                :class="type === 'error' ? 'border-red-500' : 'border-green-500'"
            >
                <div :class="type === 'error' ? 'p-2 bg-red-100 rounded-full mr-3' : 'p-2 bg-green-100 rounded-full mr-3'">
                    <svg v-if="type === 'success'" class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    <svg v-else class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-gray-900">{{ type === 'error' ? '¡Atención!' : '¡Éxito!' }}</p>
                    <p class="text-xs text-gray-500">{{ message }}</p>
                </div>
                <button @click="showToast = false" class="ml-auto text-gray-400 hover:text-gray-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </Transition>
    </Teleport>
</template>
