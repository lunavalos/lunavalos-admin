<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    permissions: Array,
});

const form = useForm({
    name: '',
    permissions: [],
});

const submit = () => {
    form.post(route('roles.store'));
};
</script>

<template>
    <Head title="Crear Rol" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Crear Rol
                </h2>
                <Link
                    :href="route('roles.index')"
                    class="btn bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded"
                >
                    Volver
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="container mx-auto">
                <div class="card overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 border-b border-gray-200">
                        <form @submit.prevent="submit" class="space-y-6">
                            
                            <div>
                                <InputLabel for="name" value="Nombre del Rol" class="font-bold text-gray-700" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    v-model="form.name"
                                    required
                                    autofocus
                                    autocomplete="off"
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <div v-if="permissions && permissions.length > 0">
                                <h2 class="sub-title block font-medium text-sm text-gray-700 mb-2 font-bold !text-xl !not-italic">
                                    Permisos:
                                </h2>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <label v-for="perm in permissions" :key="perm.id" class="flex items-center space-x-2 bg-gray-50 rounded p-2 hover:bg-gray-100 transition border mb-1 cursor-pointer">
                                        <input 
                                            type="checkbox" 
                                            :value="perm.name" 
                                            v-model="form.permissions"
                                            class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary"
                                        >
                                        <span class="text-sm font-medium text-gray-700">{{ perm.name }}</span>
                                    </label>
                                </div>
                                <InputError class="mt-2" :message="form.errors.permissions" />
                            </div>

                            <div class="flex items-center justify-end mt-4">
                                <button class="btn ml-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Guardar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
