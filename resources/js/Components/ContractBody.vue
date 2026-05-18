<script setup>
defineProps({ cd: Object });
</script>

<template>
<div class="space-y-5 text-justify">
    <!-- Parties -->
    <div class="bg-gray-50 border-l-4 border-[#264ab3] p-4 rounded-r-lg space-y-1">
        <p>Entre:</p>
        <p><strong>{{ cd.providerName }} / {{ cd.providerSite }}</strong> <em>("EL PRESTADOR")</em></p>
        <p>y</p>
        <p><strong>{{ cd.clientName }}</strong> <em>("EL CLIENTE")</em></p>
        <p class="mt-2">Se celebra el presente Contrato de Prestación de Servicios bajo las siguientes cláusulas:</p>
    </div>

    <!-- PRIMERA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Primera. Objeto del Contrato</p>
        <p class="mb-2">EL PRESTADOR se compromete a desarrollar y/o prestar los servicios solicitados por EL CLIENTE conforme a la cotización aceptada No. <strong>{{ cd.quoteId }}</strong>, la cual forma parte integral del presente contrato.</p>
        <p class="font-semibold mb-1">Servicios contratados:</p>
        <ul class="list-none space-y-0.5 pl-3">
            <li v-for="item in cd.items" :key="item.id" class="flex gap-2">
                <span class="text-[#264ab3]">•</span>
                <span>{{ item.concept }}<span v-if="item.description" class="text-gray-400 font-sans"> — {{ item.description }}</span></span>
            </li>
        </ul>
    </div>

    <!-- SEGUNDA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Segunda. Monto y Forma de Pago</p>
        <p class="mb-2">El monto total de los servicios es:</p>
        <ul class="list-none pl-3 space-y-0.5 mb-3">
            <li><span class="text-[#264ab3]">•</span> Subtotal: <strong>{{ cd.subtotal }} MXN</strong></li>
            <li><span class="text-[#264ab3]">•</span> IVA: <strong>{{ cd.iva }} MXN</strong></li>
            <li><span class="text-[#264ab3]">•</span> Total: <strong>{{ cd.total }} MXN</strong></li>
        </ul>
        <p class="font-semibold mb-1">Forma de pago:</p>
        <ul class="list-none pl-3 space-y-0.5 mb-3">
            <li><span class="text-[#264ab3]">•</span> Anticipo: <strong>{{ cd.anticipo }}</strong></li>
            <li><span class="text-[#264ab3]">•</span> Mensualidades ({{ cd.months }} meses): <strong>{{ cd.monthly }}/mes</strong></li>
        </ul>
        <p>Los trabajos iniciarán una vez confirmado el anticipo correspondiente.</p>
    </div>

    <!-- TERCERA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Tercera. Tiempos de Entrega</p>
        <p class="mb-2">El tiempo estimado para la entrega será de <strong>15 días hábiles</strong>, contados a partir de:</p>
        <ul class="list-none pl-3 space-y-0.5 mb-3">
            <li><span class="text-[#264ab3]">•</span> Recepción del anticipo</li>
            <li><span class="text-[#264ab3]">•</span> Recepción de información y materiales necesarios por parte del cliente</li>
        </ul>
        <p class="mb-1">Los tiempos podrán modificarse si:</p>
        <ul class="list-none pl-3 space-y-0.5">
            <li><span class="text-[#264ab3]">•</span> EL CLIENTE retrasa entrega de contenido</li>
            <li><span class="text-[#264ab3]">•</span> Se solicitan cambios fuera del alcance inicial</li>
            <li><span class="text-[#264ab3]">•</span> Existen causas de fuerza mayor</li>
        </ul>
    </div>

    <!-- CUARTA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Cuarta. Alcance del Servicio</p>
        <p class="mb-2">El servicio incluye únicamente los conceptos establecidos en la cotización aceptada.</p>
        <p>Cualquier funcionalidad, modificación, integración o servicio adicional no contemplado será considerado trabajo extra y podrá generar una nueva cotización.</p>
    </div>

    <!-- QUINTA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Quinta. Cambios y Revisiones</p>
        <p class="mb-2">EL CLIENTE tendrá derecho a <strong>2 rondas de ajustes menores</strong>, siempre que estén relacionados con el alcance contratado.</p>
        <p>Cambios que alteren estructura, funcionalidades o nuevos requerimientos podrán generar costos adicionales.</p>
    </div>

    <!-- SEXTA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Sexta. Propiedad Intelectual</p>
        <p class="mb-2">Los derechos del trabajo desarrollado serán transferidos a EL CLIENTE una vez liquidado el monto total del contrato.</p>
        <p>EL PRESTADOR podrá mostrar el proyecto en su portafolio, redes sociales o material promocional, salvo solicitud escrita en contrario.</p>
    </div>

    <!-- SÉPTIMA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Séptima. Dominios, Hosting y Servicios de Terceros</p>
        <p class="mb-2">Los costos relacionados con dominio, hosting, servicios de correo, APIs, licencias, plataformas externas y publicidad pagada podrán requerir pagos adicionales y/o renovaciones periódicas.</p>
        <p>EL PRESTADOR no será responsable por fallas atribuibles a terceros.</p>
    </div>

    <!-- OCTAVA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Octava. Cancelación</p>
        <p class="mb-2">En caso de cancelación por parte de EL CLIENTE:</p>
        <ul class="list-none pl-3 space-y-0.5">
            <li><span class="text-[#264ab3]">•</span> El anticipo no será reembolsable.</li>
            <li><span class="text-[#264ab3]">•</span> Los trabajos ya realizados deberán ser cubiertos proporcionalmente.</li>
        </ul>
    </div>

    <!-- NOVENA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Novena. Soporte y Garantía</p>
        <p class="mb-2">EL PRESTADOR brindará soporte técnico por <strong>30 días</strong> posteriores a la entrega final para corrección de errores atribuibles al desarrollo original.</p>
        <p class="mb-1">No incluye:</p>
        <ul class="list-none pl-3 space-y-0.5">
            <li><span class="text-[#264ab3]">•</span> Nuevas funcionalidades</li>
            <li><span class="text-[#264ab3]">•</span> Modificaciones posteriores</li>
            <li><span class="text-[#264ab3]">•</span> Errores causados por terceros</li>
        </ul>
    </div>

    <!-- DÉCIMA -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-2">Décima. Aceptación</p>
        <p>Las partes aceptan el presente contrato y reconocen que la firma electrónica o aceptación digital tendrá validez legal equivalente a firma autógrafa, de conformidad con la legislación aplicable en materia de comercio electrónico.</p>
    </div>

    <!-- Signature block -->
    <div class="grid grid-cols-2 gap-8 mt-6 pt-6 border-t border-gray-200">
        <div class="space-y-1">
            <p class="font-bold uppercase text-[#264ab3] text-xs tracking-wider mb-2">El Prestador</p>
            <p>Nombre: <strong>Luna Avalos</strong></p>
            <p>Sitio web: {{ cd.providerSite }}</p>
            <p class="mt-8 pt-2 border-t border-gray-300 text-xs text-gray-500">Firma autógrafa</p>
        </div>
        <div class="space-y-1">
            <p class="font-bold uppercase text-[#264ab3] text-xs tracking-wider mb-2">El Cliente</p>
            <p>Nombre: <strong>{{ cd.clientName }}</strong></p>
            <p v-if="cd.legalRep">Representante: {{ cd.legalRep }}</p>
            <p>RFC: {{ cd.taxId }}</p>
            <template v-if="cd.signedAt">
                <p class="mt-4 pt-2 border-t border-gray-300 text-xs text-emerald-700 font-medium">Firmado electrónicamente</p>
                <p class="text-xs text-gray-400">{{ cd.signedAt }}</p>
                <p class="text-xs text-gray-400">IP: {{ cd.signatureIp }}</p>
            </template>
            <p v-else class="mt-8 pt-2 border-t border-gray-300 text-xs text-gray-500">Firma electrónica pendiente</p>
        </div>
    </div>
</div>
</template>
