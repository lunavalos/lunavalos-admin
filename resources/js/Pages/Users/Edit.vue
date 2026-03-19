<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    user: Object,
    roles: Array,
});

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    roles: props.user.roles.map(r => r.name),
});

const submit = () => {
    form.put(route('users.update', props.user.id));
};
</script>

<template>
    <Head title="Editar Usuario" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Editar Usuario: {{ user.name }}
                </h2>
                <Link
                    :href="route('users.index')"
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
                                <InputLabel for="name" value="Nombre" class="font-bold text-gray-700" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    v-model="form.name"
                                    required
                                    autofocus
                                    autocomplete="name"
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <div>
                                <InputLabel for="email" value="Correo Electrónico" class="font-bold text-gray-700" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    v-model="form.email"
                                    required
                                    autocomplete="username"
                                />
                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>

                            <div>
                                <InputLabel for="password" value="Contraseña (Dejar en blanco para no cambiar)" class="font-bold text-gray-700" />
                                <TextInput
                                    id="password"
                                    type="password"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    v-model="form.password"
                                    autocomplete="new-password"
                                />
                                <InputError class="mt-2" :message="form.errors.password" />
                            </div>

                            <div v-if="roles && roles.length > 0">
                                <h2 class="sub-title block font-medium text-sm text-gray-700 mb-2 font-bold !text-xl !not-italic">
                                    Roles:
                                </h2>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <label v-for="role in roles" :key="role.id" class="flex items-center space-x-2 bg-gray-50 rounded p-2 hover:bg-gray-100 transition border mb-1 cursor-pointer">
                                        <input 
                                            type="checkbox" 
                                            :value="role.name" 
                                            v-model="form.roles"
                                            class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary"
                                        >
                                        <span class="text-sm font-medium text-gray-700">{{ role.name }}</span>
                                    </label>
                                </div>
                                <InputError class="mt-2" :message="form.errors.roles" />
                            </div>

                            <div class="flex items-center justify-end mt-4">
                                <button class="btn ml-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Actualizar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
