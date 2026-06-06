<script setup>
defineProps({ cd: Object });
</script>

<template>
<div class="space-y-6 text-justify">

    <!-- Partes -->
    <div class="bg-gray-50 border-l-4 border-[#264ab3] p-4 rounded-r-lg space-y-1">
        <p>Entre:</p>
        <p><strong>{{ cd.providerName }}</strong> — <span class="text-gray-600">{{ cd.providerSite }}</span> <em>("EL PRESTADOR")</em></p>
        <p>y</p>
        <p><strong>{{ cd.clientName }}</strong><span v-if="cd.taxId && cd.taxId !== '[RFC]'"> · RFC: {{ cd.taxId }}</span> <em>("EL CLIENTE")</em></p>
        <p class="mt-2 text-gray-600">Se celebra el presente Contrato de Prestación de Servicios bajo las siguientes cláusulas:</p>
    </div>

    <!-- PRIMERA: Objeto -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-3">Primera. Objeto del Contrato</p>
        <p class="mb-3">
            EL PRESTADOR se obliga a mantener activos y en correcto funcionamiento los servicios digitales
            contratados por EL CLIENTE, garantizando que operen de manera continua durante la vigencia del
            presente contrato, con el respaldo técnico necesario para su funcionamiento.
        </p>

        <p class="font-semibold mb-2">Servicios contratados:</p>
        <ul v-if="cd.services.length" class="list-none space-y-1 pl-3 mb-3">
            <li v-for="(s, i) in cd.services" :key="i" class="flex gap-2">
                <span class="text-[#264ab3] shrink-0">•</span>
                <span>
                    {{ s.name }}
                    <span v-if="s.detail" class="text-gray-500 font-sans"> — {{ s.detail }}</span>
                </span>
            </li>
        </ul>
        <p v-else class="text-gray-400 italic pl-3 mb-3">— Servicios descritos en cotización adjunta —</p>

        <p v-if="cd.quoteId" class="text-sm text-gray-500">
            Con referencia a la cotización No. <strong>{{ cd.quoteId }}</strong>, que forma parte integral del presente contrato.
        </p>
    </div>

    <!-- SEGUNDA: Vigencia -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-3">Segunda. Vigencia del Servicio</p>
        <p class="mb-2">
            El presente contrato tiene una vigencia de <strong>doce (12) meses</strong>
            <template v-if="cd.startDate && cd.startDate !== '—'">
                , a partir del <strong>{{ cd.startDate }}</strong>
                <template v-if="cd.endDate && cd.endDate !== '—'"> con vencimiento el <strong>{{ cd.endDate }}</strong></template>
            </template>.
        </p>
        <p>
            Al término de la vigencia, EL CLIENTE podrá optar por renovar el servicio por un periodo
            adicional mediante el pago de la tarifa correspondiente. La no renovación implica la suspensión
            de los servicios al concluir el periodo pagado.
        </p>
    </div>

    <!-- TERCERA: Monto y Pago — DUAL MODE -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-3">Tercera. Monto y Forma de Pago</p>

        <!-- RENOVACIÓN ANUAL: pago único total -->
        <template v-if="cd.isRenewal">
            <p class="mb-3">El monto correspondiente a la renovación anual de los servicios es:</p>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-3">
                <p class="text-2xl font-bold text-[#264ab3]">{{ cd.total }} {{ cd.currency }}</p>
                <p class="text-sm text-gray-600 mt-1">Pago único de renovación anual</p>
            </div>
            <p>
                El pago deberá realizarse previo al vencimiento del servicio para garantizar la continuidad
                sin interrupciones. EL PRESTADOR notificará a EL CLIENTE con anticipación la fecha de
                vencimiento y el monto de renovación vigente.
            </p>
        </template>

        <!-- PROYECTO A MESES: anticipo + plan de pagos -->
        <template v-else>
            <p class="mb-3">El monto total de los servicios contratados es:</p>
            <ul class="list-none pl-3 space-y-1 mb-4">
                <li v-if="cd.subtotal"><span class="text-[#264ab3]">•</span> Subtotal: <strong>{{ cd.subtotal }} {{ cd.currency }}</strong></li>
                <li v-if="cd.iva"><span class="text-[#264ab3]">•</span> IVA (16%): <strong>{{ cd.iva }} {{ cd.currency }}</strong></li>
                <li><span class="text-[#264ab3]">•</span> <strong>Total: {{ cd.total }} {{ cd.currency }}</strong></li>
            </ul>
            <p class="font-semibold mb-2">Plan de pago:</p>
            <ul class="list-none pl-3 space-y-1 mb-3">
                <li><span class="text-[#264ab3]">•</span> Anticipo al inicio: <strong>{{ cd.anticipo }} {{ cd.currency }}</strong></li>
                <li v-if="cd.months > 1">
                    <span class="text-[#264ab3]">•</span>
                    Saldo restante en <strong>{{ cd.months - 1 }} mensualidades</strong> de <strong>{{ cd.monthly }} {{ cd.currency }}</strong>
                </li>
            </ul>
            <p>Los trabajos iniciarán una vez confirmado el anticipo correspondiente.</p>
        </template>
    </div>

    <!-- CUARTA: Compromisos del Prestador -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-3">Cuarta. Compromisos del Prestador</p>
        <p class="mb-2">EL PRESTADOR se compromete a:</p>
        <ul class="list-none pl-3 space-y-1">
            <li><span class="text-[#264ab3]">•</span> Mantener los servicios contratados activos y operando correctamente durante la vigencia del contrato.</li>
            <li><span class="text-[#264ab3]">•</span> Atender reportes de fallas o solicitudes de soporte en un plazo de <strong>5 a 15 días hábiles</strong> a partir de la notificación de EL CLIENTE.</li>
            <li><span class="text-[#264ab3]">•</span> Notificar con anticipación cualquier mantenimiento programado, cambio relevante o próximo vencimiento del servicio.</li>
            <li><span class="text-[#264ab3]">•</span> Custodiar de manera confidencial los accesos, contraseñas y activos digitales relacionados con el servicio.</li>
        </ul>
    </div>

    <!-- QUINTA: Compromisos del Cliente -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-3">Quinta. Compromisos del Cliente</p>
        <p class="mb-2">EL CLIENTE se compromete a:</p>
        <ul class="list-none pl-3 space-y-1">
            <li><span class="text-[#264ab3]">•</span> Realizar los pagos en tiempo y forma según lo acordado en este contrato.</li>
            <li><span class="text-[#264ab3]">•</span> Proporcionar oportunamente la información, materiales y accesos necesarios para la correcta prestación del servicio.</li>
            <li><span class="text-[#264ab3]">•</span> Notificar a EL PRESTADOR cualquier cambio en sus datos de contacto o requerimientos del servicio.</li>
            <li><span class="text-[#264ab3]">•</span> No compartir accesos o credenciales del servicio con terceros sin autorización de EL PRESTADOR.</li>
        </ul>
    </div>

    <!-- SEXTA: Cancelación -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-3">Sexta. Cancelación</p>
        <p class="mb-2">
            EL CLIENTE podrá cancelar el servicio en cualquier momento notificando por escrito a EL PRESTADOR.
            En caso de cancelación:
        </p>
        <ul class="list-none pl-3 space-y-1">
            <li><span class="text-[#264ab3]">•</span> Los servicios permanecerán activos hasta la fecha de vencimiento del periodo ya pagado, sin reembolso proporcional.</li>
            <li><span class="text-[#264ab3]">•</span> Si EL CLIENTE cancela el servicio antes del vencimiento del plazo contractual, deberá pagar una multa para la transferencia de los activos digitales.
                El monto será determinado por EL PRESTADOR según el tipo y la complejidad de los activos a transferir, con un cargo mínimo de $2,800 MXN.</li>
            <li><span class="text-[#264ab3]">•</span> EL PRESTADOR realizará la entrega de los activos digitales del cliente (archivos, accesos y credenciales) dentro de los 10 días hábiles posteriores a la solicitud.</li>
            <li><span class="text-[#264ab3]">•</span> En caso de adeudo pendiente, la entrega de activos se efectuará una vez liquidado el saldo.</li>
        </ul>
    </div>

    <!-- SÉPTIMA: Propiedad y Confidencialidad -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-3">Séptima. Propiedad y Confidencialidad</p>
        <p class="mb-2">
            Los derechos sobre los activos digitales desarrollados exclusivamente para EL CLIENTE serán
            transferidos a su favor una vez liquidado el monto total del contrato.
        </p>
        <p>
            Ambas partes se obligan a mantener confidencialidad sobre la información, accesos y datos
            intercambiados durante la relación comercial, tanto durante la vigencia como tras la terminación
            del contrato.
        </p>
    </div>

    <!-- OCTAVA: Aceptación -->
    <div>
        <p class="font-bold uppercase text-[#264ab3] border-b border-blue-100 pb-1 mb-3">Octava. Aceptación</p>
        <p>
            Las partes declaran haber leído y entendido el presente contrato en su totalidad, aceptando
            sus términos de manera libre y voluntaria. La firma electrónica o aceptación digital tiene
            validez legal equivalente a firma autógrafa, de conformidad con el
            <strong>Código de Comercio</strong> y la <strong>Ley de Firma Electrónica Avanzada</strong>
            vigentes en México.
        </p>
    </div>

    <!-- Bloque de firmas -->
    <div class="grid grid-cols-2 gap-8 mt-6 pt-6 border-t border-gray-200 text-sm">
        <div class="space-y-1">
            <p class="font-bold uppercase text-[#264ab3] text-xs tracking-wider mb-2">El Prestador</p>
            <p>Nombre: <strong>{{ cd.providerName }}</strong></p>
            <p>Sitio web: {{ cd.providerSite }}</p>
            <p class="mt-8 pt-2 border-t border-gray-300 text-xs text-gray-500">Firma autógrafa</p>
        </div>
        <div class="space-y-1">
            <p class="font-bold uppercase text-[#264ab3] text-xs tracking-wider mb-2">El Cliente</p>
            <p>Nombre: <strong>{{ cd.clientName }}</strong></p>
            <p v-if="cd.legalRep">Representante Legal: {{ cd.legalRep }}</p>
            <p v-if="cd.taxId && cd.taxId !== '[RFC]'">RFC: {{ cd.taxId }}</p>
            <template v-if="cd.signedAt">
                <p class="mt-4 pt-2 border-t border-gray-300 text-xs text-emerald-700 font-medium">✓ Firmado electrónicamente</p>
                <p class="text-xs text-gray-400">{{ cd.signedAt }}</p>
                <p class="text-xs text-gray-400">IP: {{ cd.signatureIp }}</p>
            </template>
            <p v-else class="mt-8 pt-2 border-t border-gray-300 text-xs text-gray-500">Firma electrónica pendiente</p>
        </div>
    </div>

</div>
</template>
