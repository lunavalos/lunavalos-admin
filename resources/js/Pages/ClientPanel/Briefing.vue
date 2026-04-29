<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LightBulbIcon } from '@heroicons/vue/24/outline';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    briefing_details: Object,
});

const briefingForm = useForm({
    briefing_context: props.briefing_details?.briefing_context || '',
    briefing_target_audience: props.briefing_details?.briefing_target_audience || '',
    briefing_competitors: props.briefing_details?.briefing_competitors || '',
    briefing_references: props.briefing_details?.briefing_references || '',
    briefing_contact_methods: props.briefing_details?.briefing_contact_methods || '',
    briefing_current_emails: props.briefing_details?.briefing_current_emails || '',
});

const submitBriefing = () => {
    briefingForm.post(route('client.briefing.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Briefing Creativo" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">
                Briefing Creativo
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 dark:border-zinc-800 bg-gray-50/50 dark:bg-yellow-950/10">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 flex items-center">
                            <LightBulbIcon class="h-6 w-6 mr-2 text-yellow-500 dark:text-yellow-400" />
                            Información de tu Proyecto
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-zinc-400 mt-1">Completa esta información para ayudarnos a entender mejor tu negocio y poder ofrecerte un diseño más adecuado a tus necesidades. Estos datos serán utilizados por nuestro equipo creativo.</p>
                    </div>
                    
                    <div class="p-6">
                        <form @submit.prevent="submitBriefing" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="md:col-span-2">
                                    <InputLabel for="briefing_context" value="1. Cuéntanos sobre tu empresa (breve contexto sobre el negocio, giro, trayectoria etc)" class="font-bold text-gray-700 dark:text-zinc-300" />
                                    <textarea id="briefing_context" class="mt-1 block w-full border-gray-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-2xl shadow-sm h-32" v-model="briefingForm.briefing_context" placeholder="Háblanos de tu historia, qué haces y qué te hace único..."></textarea>
                                </div>
                                
                                <div class="md:col-span-1">
                                    <InputLabel for="briefing_target_audience" value="2. ¿A qué tipo de clientes están enfocados tus servicios/productos?" class="font-bold text-gray-700 dark:text-zinc-300" />
                                    <textarea id="briefing_target_audience" class="mt-1 block w-full border-gray-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-2xl shadow-sm h-32" v-model="briefingForm.briefing_target_audience" placeholder="Describe a tu cliente ideal (edad, ubicación, intereses, etc)..."></textarea>
                                </div>
                                
                                <div class="md:col-span-1">
                                    <InputLabel for="briefing_competitors" value="3. Principales competidores locales/nacionales" class="font-bold text-gray-700 dark:text-zinc-300" />
                                    <textarea id="briefing_competitors" class="mt-1 block w-full border-gray-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-2xl shadow-sm h-32" v-model="briefingForm.briefing_competitors" placeholder="¿Quiénes son tus competidores directos?"></textarea>
                                </div>
                                
                                <div class="md:col-span-1">
                                    <InputLabel for="briefing_references" value="4. Sitios web de referencia visual para tu diseño/rediseño" class="font-bold text-gray-700 dark:text-zinc-300" />
                                    <textarea id="briefing_references" class="mt-1 block w-full border-gray-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-2xl shadow-sm h-32" v-model="briefingForm.briefing_references" placeholder="Pega aquí los enlaces de sitios web que te gusten estéticamente..."></textarea>
                                </div>
                                
                                <div class="md:col-span-1">
                                    <InputLabel for="briefing_contact_methods" value="5. Cuales son los metodos de contacto que utiliza tu empresa para conectar con tus clientes" class="font-bold text-gray-700 dark:text-zinc-300" />
                                    <textarea id="briefing_contact_methods" class="mt-1 block w-full border-gray-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-2xl shadow-sm h-32" v-model="briefingForm.briefing_contact_methods" placeholder="WhatsApp, Redes Sociales, Llamadas, etc..."></textarea>
                                </div>
                                
                                <div class="md:col-span-2">
                                    <InputLabel for="briefing_current_emails" value="6. Actualmente, cuantos correos empresariales tienes" class="font-bold text-gray-700 dark:text-zinc-300" />
                                    <TextInput id="briefing_current_emails" type="text" class="mt-1 block w-full border-gray-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 focus:border-[#264ab3] focus:ring-[#264ab3] rounded-2xl shadow-sm" v-model="briefingForm.briefing_current_emails" placeholder="Ej: 5 correos" />
                                </div>
                            </div>
                            
                            <div class="flex justify-end pt-4 border-t border-gray-100 dark:border-zinc-800">
                                <PrimaryButton 
                                    class="rounded-2xl px-8 py-3 !bg-yellow-500 hover:!bg-yellow-600 border-none shadow-lg shadow-yellow-200/50 dark:shadow-none text-white" 
                                    :class="{ 'opacity-25': briefingForm.processing }" 
                                    :disabled="briefingForm.processing"
                                >
                                    Guardar Briefing
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
