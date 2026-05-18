<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { MegaphoneIcon, PhotoIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    client: { type: Object, required: true },
    accounts: { type: Array, default: () => [] },
    post: { type: Object, default: null },
});

const providerLabels = {
    facebook: 'Facebook', instagram: 'Instagram', linkedin: 'LinkedIn', tiktok: 'TikTok', youtube: 'YouTube',
};

const form = useForm({
    title: props.post?.title || '',
    body: props.post?.body || '',
    scheduled_at: props.post?.scheduled_at ? props.post.scheduled_at.slice(0, 16) : '',
    account_ids: props.post?.targets?.map(t => t.social_account_id) || [],
    media: [],
    action: 'save_draft',
    options: {},
});

const filePreviews = ref([]);
function onFileChange(e) {
    const files = Array.from(e.target.files);
    form.media = files;
    filePreviews.value = files.map(f => ({ name: f.name, url: URL.createObjectURL(f), type: f.type }));
}
function removeFile(i) {
    form.media.splice(i, 1);
    filePreviews.value.splice(i, 1);
}

function submit(action) {
    form.action = action;
    if (action === 'schedule' && !form.scheduled_at) {
        alert('Selecciona fecha de programación.');
        return;
    }
    const url = props.post
        ? route('social.posts.update', [props.client.id, props.post.id])
        : route('social.posts.store', props.client.id);
    form.post(url, { forceFormData: true });
}

function toggleAccount(id) {
    const idx = form.account_ids.indexOf(id);
    if (idx >= 0) form.account_ids.splice(idx, 1);
    else form.account_ids.push(id);
}
</script>

<template>
    <Head :title="post ? 'Editar post' : 'Nuevo post'" />
    <AuthenticatedLayout>
        <div class="p-6 max-w-4xl mx-auto space-y-6">
            <div>
                <Link :href="route('social.clients.show', client.id)" class="text-xs text-primary hover:underline">← Volver</Link>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2 mt-1">
                    <MegaphoneIcon class="w-6 h-6 text-primary" />
                    {{ post ? 'Editar post' : 'Nuevo post' }} — {{ client.business_name }}
                </h1>
            </div>

            <form @submit.prevent="submit('save_draft')" class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-6 space-y-5">

                <!-- Accounts -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-200 mb-2">Publicar en</label>
                    <div v-if="!accounts.length" class="text-xs text-rose-600">
                        No hay cuentas activas. Conecta al menos una red.
                    </div>
                    <div v-else class="flex flex-wrap gap-2">
                        <button v-for="a in accounts" :key="a.id" type="button"
                            @click="toggleAccount(a.id)"
                            :class="['inline-flex items-center gap-1 rounded-md px-3 py-1.5 text-xs border',
                                     form.account_ids.includes(a.id)
                                       ? 'bg-primary text-white border-primary'
                                       : 'bg-white dark:bg-zinc-800 text-gray-700 dark:text-zinc-200 border-gray-300 dark:border-zinc-600']">
                            {{ providerLabels[a.provider] }} — {{ a.name }}
                        </button>
                    </div>
                    <p v-if="form.errors.account_ids" class="text-xs text-rose-600 mt-1">{{ form.errors.account_ids }}</p>
                </div>

                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-200 mb-1">Título (opcional)</label>
                    <input v-model="form.title" type="text" maxlength="255"
                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-sm" />
                </div>

                <!-- Body -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-200 mb-1">Contenido</label>
                    <textarea v-model="form.body" rows="6"
                        class="w-full rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-sm"></textarea>
                    <p class="text-xs text-gray-400 mt-1">{{ (form.body || '').length }} / 2200 caracteres aprox.</p>
                </div>

                <!-- Media -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-200 mb-1">Media (imágenes / video)</label>
                    <label class="flex items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 dark:border-zinc-700 rounded-md cursor-pointer hover:bg-gray-50 dark:hover:bg-zinc-800/40">
                        <input type="file" multiple class="hidden" @change="onFileChange" accept="image/*,video/*" />
                        <div class="text-center text-gray-500">
                            <PhotoIcon class="w-8 h-8 mx-auto" />
                            <p class="text-xs mt-1">Click para adjuntar archivos</p>
                        </div>
                    </label>
                    <div v-if="filePreviews.length" class="grid grid-cols-3 md:grid-cols-4 gap-2 mt-3">
                        <div v-for="(p, i) in filePreviews" :key="i" class="relative border rounded-md overflow-hidden">
                            <img v-if="p.type.startsWith('image/')" :src="p.url" class="w-full h-24 object-cover" />
                            <div v-else class="w-full h-24 flex items-center justify-center bg-gray-100 dark:bg-zinc-800 text-xs">
                                {{ p.name }}
                            </div>
                            <button type="button" @click="removeFile(i)" class="absolute top-1 right-1 bg-white/90 rounded-full p-0.5">
                                <XMarkIcon class="w-3 h-3" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Schedule -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-200 mb-1">Programar (opcional)</label>
                    <input v-model="form.scheduled_at" type="datetime-local"
                        class="rounded-md border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-sm" />
                </div>

                <!-- Actions -->
                <div class="flex flex-wrap justify-end gap-2 pt-4 border-t border-gray-100 dark:border-zinc-800">
                    <button type="button" @click="submit('save_draft')" :disabled="form.processing"
                        class="px-4 py-2 rounded-md border border-gray-300 dark:border-zinc-600 text-sm text-gray-700 dark:text-zinc-200 hover:bg-gray-50 dark:hover:bg-zinc-800">
                        Guardar borrador
                    </button>
                    <button type="button" @click="submit('schedule')" :disabled="form.processing || !form.scheduled_at"
                        class="px-4 py-2 rounded-md bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium disabled:opacity-50">
                        Programar
                    </button>
                    <button type="button" @click="submit('publish_now')" :disabled="form.processing"
                        class="px-4 py-2 rounded-md bg-primary hover:bg-secondary text-white text-sm font-medium disabled:opacity-50">
                        Publicar ahora
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
