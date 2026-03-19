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
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('agencies.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
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

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600">ID</th>
                                    <th class="p-3 uppercase font-medium text-sm text-gray-600 w-full">Nombre del Origen / Agencia</th>
                                    <th class="p-3 text-right uppercase font-medium text-sm text-gray-600 w-32 whitespace-nowrap">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="agency in agencies" :key="agency.id" class="border-b hover:bg-gray-50 transition">
                                    <td class="p-3 font-semibold text-gray-500">#{{ agency.id }}</td>
                                    <td class="p-3 font-semibold text-primary">{{ agency.name }}</td>
                                    <td class="p-3 text-right space-x-1 whitespace-nowrap">
                                        <div class="group relative inline-block">
                                            <button @click="openEditModal(agency)" class="text-gray-600 hover:text-blue-600 hover:bg-blue-50 p-2 rounded-full transition-colors inline-flex items-center">
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
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">
                    {{ isEditing ? 'Editar Origen' : 'Nuevo Origen' }}
                </h2>
                
                <form @submit.prevent="saveAgency">
                    <div class="mb-4">
                        <InputLabel value="Nombre" />
                        <TextInput
                            type="text"
                            v-model="form.name"
                            class="mt-1 block w-full"
                            autofocus
                        />
                        <div v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="closeModal" class="px-4 py-2 border rounded text-gray-600 hover:bg-gray-100">
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
