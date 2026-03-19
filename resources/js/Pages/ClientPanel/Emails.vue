<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { 
    EnvelopeIcon, 
    DevicePhoneMobileIcon, 
    AtSymbolIcon, 
    ServerIcon, 
    ShieldCheckIcon,
    ClipboardIcon,
    CheckIcon
} from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const props = defineProps({
    client: Object
});

const copied = ref('');
const usePop3 = ref(false);

const copyToClipboard = (text, type) => {
    if (!text) return;
    navigator.clipboard.writeText(text);
    copied.value = type;
    setTimeout(() => {
        copied.value = '';
    }, 2000);
};

</script>

<template>
    <Head title="Configuración de Emails" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 flex items-center">
                    <EnvelopeIcon class="h-6 w-6 text-gray-500 mr-2" />
                    Manual de Configuración de Correo
                </h2>
                <!-- Protocol Switcher -->
                <div class="inline-flex p-1 bg-gray-100 rounded-lg shadow-inner">
                    <button 
                        @click="usePop3 = false"
                        :class="[!usePop3 ? 'bg-white text-[#264ab3] shadow-sm' : 'text-gray-500 hover:text-gray-700']"
                        class="px-4 py-1.5 rounded-md text-xs font-bold transition-all duration-200"
                    >
                        RECOMENDADO (IMAP)
                    </button>
                    <button 
                        @click="usePop3 = true"
                        :class="[usePop3 ? 'bg-white text-orange-600 shadow-sm' : 'text-gray-500 hover:text-gray-700']"
                        class="px-4 py-1.5 rounded-md text-xs font-bold transition-all duration-200"
                    >
                        ALTERNATIVO (POP3)
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
                
                <!-- Protocol Explanation Alert -->
                <div v-if="!usePop3" class="bg-blue-50 border-l-4 border-[#264ab3] p-4 rounded-r-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <ShieldCheckIcon class="h-5 w-5 text-[#264ab3]" />
                        </div>
                        <div class="ml-3">
                            <p class="text-xs text-[#264ab3] font-bold">Protocolo IMAP Detectado (Recomendado)</p>
                            <p class="text-[11px] text-blue-700 mt-1">Este protocolo sincroniza tus correos en todos tus dispositivos (Celular, Outlook, Webmail). Lo que borres o leas en uno, se reflejará en todos.</p>
                        </div>
                    </div>
                </div>
                <div v-else class="bg-orange-50 border-l-4 border-orange-500 p-4 rounded-r-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <ShieldCheckIcon class="h-5 w-5 text-orange-500" />
                        </div>
                        <div class="ml-3">
                            <p class="text-xs text-orange-700 font-bold">Protocolo POP3 Detectado (Manual)</p>
                            <p class="text-[11px] text-orange-600 mt-1 italic">Atención: POP3 descarga los correos a tu PC y suele borrarlos del servidor. No se recomienda si usas más de un dispositivo.</p>
                        </div>
                    </div>
                </div>

                <!-- Email Server Info Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-t-4" :class="[usePop3 ? 'border-orange-500' : 'border-[#264ab3]']">
                    <div class="p-6">
                        <div class="flex items-center mb-6">
                            <ServerIcon class="h-6 w-6 mr-2" :class="[usePop3 ? 'text-orange-500' : 'text-[#264ab3]']" />
                            <h3 class="text-lg font-bold text-gray-800">Tus Datos de Servidor ({{ usePop3 ? 'POP3' : 'IMAP' }})</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Incoming Server (Dynamic: IMAP or POP3) -->
                            <div v-if="!usePop3" class="p-5 bg-blue-50 rounded-2xl border border-blue-100 relative group transition-all duration-300">
                                <h4 class="text-xs font-bold uppercase text-[#264ab3] mb-4 flex items-center">
                                    <span class="p-1 bg-blue-600 text-white rounded mr-2 h-5 w-5 flex items-center justify-center text-[10px]">1</span>
                                    Servidor Entrante (IMAP)
                                </h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-blue-200">
                                        <span class="text-xs text-gray-500 font-semibold tracking-wide">Host:</span>
                                        <div class="flex items-center">
                                            <span class="text-sm font-mono font-bold text-gray-800 truncate">{{ client.imap_host }}</span>
                                            <button @click="copyToClipboard(client.imap_host, 'imap_host')" class="ml-3 p-1.5 bg-gray-50 hover:bg-blue-100 rounded-lg text-gray-400 hover:text-blue-600 transition-all shrink-0 border border-transparent hover:border-blue-200">
                                                <CheckIcon v-if="copied === 'imap_host'" class="h-4 w-4 text-green-500" />
                                                <ClipboardIcon v-else class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-blue-200">
                                            <span class="text-xs text-gray-500 font-semibold">Puerto:</span>
                                            <span class="text-sm font-mono font-bold text-gray-800">{{ client.imap_port }}</span>
                                        </div>
                                        <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-blue-200">
                                            <span class="text-xs text-gray-500 font-semibold">Seguridad:</span>
                                            <span class="text-[10px] font-black px-2 py-1 rounded-lg bg-green-100 text-green-800 uppercase">{{ client.imap_tls ? 'SSL/TLS' : 'NINGUNA' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="p-5 bg-orange-50 rounded-2xl border border-orange-100 relative group transition-all duration-300">
                                <h4 class="text-xs font-bold uppercase text-orange-700 mb-4 flex items-center">
                                    <span class="p-1 bg-orange-600 text-white rounded mr-2 h-5 w-5 flex items-center justify-center text-[10px]">1</span>
                                    Servidor Entrante (POP3)
                                </h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-orange-200">
                                        <span class="text-xs text-gray-500 font-semibold tracking-wide">Host:</span>
                                        <div class="flex items-center overflow-hidden">
                                            <span class="text-sm font-mono font-bold text-gray-800 truncate">{{ client.pop_host || 'No configurado' }}</span>
                                            <button v-if="client.pop_host" @click="copyToClipboard(client.pop_host, 'pop_host')" class="ml-3 p-1.5 bg-gray-50 hover:bg-orange-100 rounded-lg text-gray-400 hover:text-orange-600 transition-all shrink-0 border border-transparent hover:border-orange-200">
                                                <CheckIcon v-if="copied === 'pop_host'" class="h-4 w-4 text-green-500" />
                                                <ClipboardIcon v-else class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-orange-200">
                                            <span class="text-xs text-gray-500 font-semibold">Puerto:</span>
                                            <span class="text-sm font-mono font-bold text-gray-800">{{ client.pop_port || 'N/A' }}</span>
                                        </div>
                                        <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-orange-200">
                                            <span class="text-xs text-gray-500 font-semibold">Seguridad:</span>
                                            <span class="text-[10px] font-black px-2 py-1 rounded-lg bg-green-100 text-green-800 uppercase">{{ (client.pop_host && client.pop_tls) ? 'SSL/TLS' : 'NINGUNA' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SMTP Server (Always Outgoing) -->
                            <div class="p-5 bg-indigo-50 rounded-2xl border border-indigo-100 relative group transition-all duration-300">
                                <h4 class="text-xs font-bold uppercase text-indigo-700 mb-4 flex items-center">
                                    <span class="p-1 bg-indigo-600 text-white rounded mr-2 h-5 w-5 flex items-center justify-center text-[10px]">2</span>
                                    Servidor Saliente (SMTP)
                                </h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-indigo-200">
                                        <span class="text-xs text-gray-500 font-semibold tracking-wide">Host:</span>
                                        <div class="flex items-center overflow-hidden">
                                            <span class="text-sm font-mono font-bold text-gray-800 truncate">{{ client.smtp_host }}</span>
                                            <button @click="copyToClipboard(client.smtp_host, 'smtp_host')" class="ml-3 p-1.5 bg-gray-50 hover:bg-indigo-100 rounded-lg text-gray-400 hover:text-indigo-600 transition-all shrink-0 border border-transparent hover:border-indigo-200">
                                                <CheckIcon v-if="copied === 'smtp_host'" class="h-4 w-4 text-green-500" />
                                                <ClipboardIcon v-else class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-indigo-200">
                                            <span class="text-xs text-gray-500 font-semibold">Puerto:</span>
                                            <span class="text-sm font-mono font-bold text-gray-800">{{ client.smtp_port }}</span>
                                        </div>
                                        <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-indigo-200">
                                            <span class="text-xs text-gray-500 font-semibold">Seguridad:</span>
                                            <span class="text-[10px] font-black px-2 py-1 rounded-lg bg-green-100 text-green-800 uppercase">{{ client.smtp_tls ? 'SSL/TLS' : 'NINGUNA' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-8 p-4 bg-gray-50 border border-gray-100 rounded-2xl flex items-start">
                           <ShieldCheckIcon class="h-6 w-6 text-blue-600 mr-4 mt-0.5 shrink-0" />
                           <div class="space-y-1">
                               <p class="text-sm font-bold text-gray-800">Cifrado de cuenta:</p>
                               <p class="text-xs text-gray-600 leading-relaxed">Tu <b>Nombre de usuario</b> es tu correo electrónico completo (ej: contacto@tuempresa.com). Utiliza tu contraseña vinculada en todos los servidores (Entrante y Saliente).</p>
                           </div>
                        </div>
                    </div>
                </div>

                <!-- Tutorial Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Outlook Desktop -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden flex flex-col transition-all duration-300 hover:shadow-md">
                        <div class="bg-[#0078d4] p-5 flex items-center justify-between">
                            <div class="flex items-center">
                                <AtSymbolIcon class="h-6 w-6 text-white mr-3" />
                                <h3 class="text-white font-bold tracking-wide">Microsoft Outlook</h3>
                            </div>
                        </div>
                        <div class="p-8 flex-1">
                            <ol class="space-y-5 text-sm text-gray-600 list-none pl-0">
                                <li class="flex">
                                    <span class="font-black text-[#0078d4] mr-4 text-xs mt-1">01</span>
                                    <span>Ve a <b>Archivo > Agregar cuenta</b> e ingresa tu correo electrónico.</span>
                                </li>
                                <li class="flex">
                                    <span class="font-black text-[#0078d4] mr-4 text-xs mt-1">02</span>
                                    <span>En opciones avanzadas, marca <b>Configurar manualmente mi cuenta</b>.</span>
                                </li>
                                <li class="flex">
                                    <span class="font-black text-[#0078d4] mr-4 text-xs mt-1">03</span>
                                    <span>Selecciona el tipo de cuenta <b>{{ usePop3 ? 'POP' : 'IMAP' }}</b>.</span>
                                </li>
                                <li class="flex">
                                    <span class="font-black text-[#0078d4] mr-4 text-xs mt-1">04</span>
                                    <span><b>Entrante:</b> servidor <b>{{ usePop3 ? client.pop_host : client.imap_host }}</b>, puerto <b>{{ usePop3 ? client.pop_port : client.imap_port }}</b> y tipo de cifrado <b>SSL/TLS</b>.</span>
                                </li>
                                <li class="flex">
                                    <span class="font-black text-[#0078d4] mr-4 text-xs mt-1">05</span>
                                    <span><b>Saliente:</b> servidor <b>{{ client.smtp_host }}</b>, puerto <b>{{ client.smtp_port }}</b> y cifrado <b>SSL/TLS</b>.</span>
                                </li>
                            </ol>
                        </div>
                    </div>

                    <!-- iPhone / iPad -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden flex flex-col transition-all duration-300 hover:shadow-md">
                        <div class="bg-black p-5 flex items-center">
                            <DevicePhoneMobileIcon class="h-6 w-6 text-white mr-3" />
                            <h3 class="text-white font-bold tracking-wide">iPhone / Apple Mail</h3>
                        </div>
                        <div class="p-8 flex-1">
                            <ol class="space-y-5 text-sm text-gray-600 list-none pl-0">
                                <li class="flex">
                                    <span class="font-black text-gray-400 mr-4 text-xs mt-1">01</span>
                                    <span>Ve a <b>Ajustes > Mail > Cuentas > Añadir cuenta > Otra</b>.</span>
                                </li>
                                <li class="flex">
                                    <span class="font-black text-gray-400 mr-4 text-xs mt-1">02</span>
                                    <span>Selecciona <b>{{ usePop3 ? 'POP' : 'IMAP' }}</b> en la parte superior del formulario.</span>
                                </li>
                                <li class="flex">
                                    <span class="font-black text-gray-400 mr-4 text-xs mt-1">03</span>
                                    <span>Ingresa tus datos y en servidores copia el host entrante (<b>{{ usePop3 ? client.pop_host : client.imap_host }}</b>) y el saliente (<b>{{ client.smtp_host }}</b>).</span>
                                </li>
                                <li class="flex">
                                    <span class="font-black text-gray-400 mr-4 text-xs mt-1">04</span>
                                    <span class="text-orange-700 font-semibold">Ojo: En ambos servidores (entrante y saliente) debes escribir nuevamente tu usuario (email) y contraseña completa.</span>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
                
                <!-- Extra help links -->
                <div class="text-center pb-12">
                    <p class="text-gray-500 text-sm">¿Necesitas ayuda adicional?</p>
                    <Link :href="route('tickets.index')" class="text-[#264ab3] font-bold hover:underline">Abrir un Ticket de Soporte</Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
