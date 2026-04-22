<script setup>
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { PencilSquareIcon, TrashIcon, TagIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    agencies: Array,
});

const page = usePage();
const flashMessage = computed(() => page.props.flash?.message);

const form = useForm({
    id: null,
    name: '',
});

const showModal = ref(false);
const isEditing = ref(false);

const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (agency) => {
    isEditing.value = true;
    form.reset();
    form.clearErrors();
    form.id = agency.id;
    form.name = agency.name;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const saveAgency = () => {
    if (isEditing.value) {
        form.put(route('agencies.update', form.id), {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                form.reset();
            },
        });
    } else {
        form.post(route('agencies.store'), {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                form.reset();
            },
        });
    }
};

const deleteAgency = (id) => {
    if (confirm('¿Estás seguro de que deseas eliminar este Origen?')) {
        form.delete(route('agencies.destroy', id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Orígenes (Agencias)" />

    <SettingsLayout>
        <template #title>
             Orígenes / Agencias
        </template>
        <template #actions>
            <button
                @click="openCreateModal"
                class="bg-[#264ab3] hover:bg-[#193074] text-white font-bold py-2 px-4 rounded shadow inline-flex items-center transition"
            >
                <TagIcon class="w-5 h-5 mr-2" />
                Nuevo Origen
            </button>
        </template>

        <div class="py-12">
            <div class="container mx-auto">
                <div v-if="flashMessage" class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                  <span class="block sm:inline">{{ flashMessage }}</span>
                </div>

                <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-zinc-800">
                    <div class="p-6 text-gray-900 dark:text-gray-100 overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-100 dark:bg-zinc-950 border-b border-gray-200 dark:border-zinc-800">
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600 dark:text-zinc-400">ID</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600 dark:text-zinc-400 w-full">Nombre del Origen / Agencia</th>
                                    <th class="p-3 text-right uppercase font-medium text-sm text-gray-600 dark:text-zinc-400 w-32 whitespace-nowrap">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-for="agency in agencies" :key="agency.id" class="border-b border-gray-100 dark:border-zinc-800 hover:bg-gray-50 dark:hover:bg-zinc-800/50 transition">
                                    <td class="p-3 font-semibold text-gray-500 dark:text-zinc-400">#{{ agency.id }}</td>
                                    <td class="p-3 font-semibold text-primary">{{ agency.name }}</td>
                                    <td class="p-3 text-right space-x-1 whitespace-nowrap">
                                        <div class="group relative inline-block">
                                            <button @click="openEditModal(agency)" class="text-gray-600 dark:text-zinc-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 p-2 rounded-full transition-colors inline-flex items-center">
                                                <PencilSquareIcon class="w-5 h-5" />
                                            </button>
                                            <span class="absolute bottom-full right-0 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 whitespace-nowrap pointer-events-none transition-opacity z-10">Editar</span>
                                        </div>
                                        <div class="group relative inline-block">
                                            <button @click="deleteAgency(agency.id)" class="text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-full transition-colors inline-flex items-center">
                                                <TrashIcon class="w-5 h-5" />
                                            </button>
                                            <span class="absolute bottom-full right-0 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 whitespace-nowrap pointer-events-none transition-opacity z-10">Eliminar</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="agencies.length === 0">
                                    <td colspan="3" class="p-4 text-center text-gray-500">
                                        No hay orígenes/agencias dados de alta aún.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showModal" @close="closeModal">
            <div class="p-6 bg-white dark:bg-zinc-900 rounded-2xl shadow-xl border border-transparent dark:border-zinc-800">
                <h2 class="text-lg font-black text-gray-900 dark:text-gray-100 mb-6 uppercase tracking-wider">
                    {{ isEditing ? 'Editar Origen' : 'Nuevo Origen' }}
                </h2>
                
                <form @submit.prevent="saveAgency">
                    <div class="mb-4">
                        <InputLabel value="Nombre" class="dark:text-gray-300" />
                        <TextInput
                            type="text"
                            v-model="form.name"
                            class="mt-1 block w-full"
                            autofocus
                        />
                        <div v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</div>
                    </div>

                    <div class="flex justify-end gap-2 mt-8">
                        <button type="button" @click="closeModal" class="px-4 py-2 border border-gray-200 dark:border-zinc-800 rounded-xl text-gray-600 dark:text-zinc-400 font-bold hover:bg-gray-100 dark:hover:bg-zinc-800 transition">
                            Cancelar
                        </button>
                        <PrimaryButton type="submit" :disabled="form.processing">
                            Guardar
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </SettingsLayout>
</template>
