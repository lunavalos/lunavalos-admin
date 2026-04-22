<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps({
    receipt: Object,
    format: {
        type: String,
        default: 'letter'
    }
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(val);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'long',
        year: 'numeric'
    });
};

const formatShortDate = (date) => {
    return new Date(date).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: '2-digit'
    });
};

</script>

<template>
    <Head :title="'Recibo ' + (format === 'ticket' ? 'Ticket' : 'Carta')" />

    <!-- LETTER FORMAT (Original) -->
    <div v-if="format === 'letter'" class="min-h-screen bg-gray-100 p-8 print:p-0 print:bg-white font-sans">
        <div class="max-w-4xl mx-auto bg-white shadow-lg p-12 print:shadow-none print:max-w-full">
            <!-- Header -->
            <div class="flex justify-between items-start border-b-2 border-gray-900 pb-8 mb-8">
                <div class="flex flex-col">
                    <img src="/logo.svg" alt="Logo" class="h-16 w-auto mb-2 self-start" />
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Recibo de Nómina / Payroll Receipt</p>
                </div>
                <div class="text-right">
                    <p class="text-xl font-black text-gray-900 tracking-wider">FOLIO #{{ receipt.id.toString().padStart(5, '0') }}</p>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-widest mt-1">{{ formatDate(receipt.payment_date) }}</p>
                </div>
            </div>

            <!-- Employee Info -->
            <div class="grid grid-cols-2 gap-12 mb-12">
                <div class="bg-gray-50/50 p-6 rounded-2xl border border-gray-100 print:bg-white print:border-gray-200">
                    <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4 border-b border-gray-100 pb-2">DATOS DEL EMPLEADO</h3>
                    <p class="text-xl font-black text-gray-900 mb-1 tracking-tight">{{ receipt.employee.user.name }}</p>
                    <p class="text-xs font-black text-[#264ab3] uppercase tracking-wider">{{ receipt.employee.position }}</p>
                    <p class="text-[10px] text-gray-400 mt-1 uppercase font-bold tracking-tight">{{ receipt.employee.department }}</p>
                </div>
                <div class="space-y-3 py-2">
                    <div class="flex justify-between text-[11px] border-b border-gray-100 pb-1.5">
                        <span class="font-bold text-gray-400 uppercase tracking-widest">No. Empleado:</span>
                        <span class="font-black text-gray-900">{{ receipt.employee.employee_number }}</span>
                    </div>
                    <div class="flex justify-between text-[11px] border-b border-gray-100 pb-1.5">
                        <span class="font-bold text-gray-400 uppercase tracking-widest">RFC:</span>
                        <span class="font-black text-gray-900 uppercase">{{ receipt.employee.rfc }}</span>
                    </div>
                    <div class="flex justify-between text-[11px] border-b border-gray-100 pb-1.5">
                        <span class="font-bold text-gray-400 uppercase tracking-widest">CURP:</span>
                        <span class="font-black text-gray-900 uppercase">{{ receipt.employee.curp }}</span>
                    </div>
                    <div class="flex justify-between text-[11px]">
                        <span class="font-bold text-gray-400 uppercase tracking-widest">NSS:</span>
                        <span class="font-black text-gray-900">{{ receipt.employee.nss }}</span>
                    </div>
                </div>
            </div>

            <!-- Period -->
            <div class="bg-gray-50/50 p-6 rounded-2xl mb-12 flex justify-center items-center gap-32 border border-gray-100 print:bg-white print:border-gray-200">
                <div class="text-center px-8">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-1">INICIO PERIODO</p>
                    <p class="text-sm font-black text-gray-900">{{ formatDate(receipt.period_start) }}</p>
                </div>
                <div class="h-8 w-px bg-gray-200"></div>
                <div class="text-center px-8">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-1">FIN PERIODO</p>
                    <p class="text-sm font-black text-gray-900">{{ formatDate(receipt.period_end) }}</p>
                </div>
            </div>

            <!-- Breakdown -->
            <div class="mb-12">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b-4 border-gray-900">
                            <th class="text-left font-black text-[11px] text-gray-900 uppercase tracking-[0.2em] py-4">CONCEPTO / DESCRIPCIÓN</th>
                            <th class="text-right font-black text-[11px] text-gray-900 uppercase tracking-[0.2em] py-4">PERCEPCIÓN</th>
                            <th class="text-right font-black text-[11px] text-gray-900 uppercase tracking-[0.2em] py-4">DEDUCCIÓN</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y-2 divide-gray-100">
                        <tr>
                            <td class="py-5 text-sm font-bold text-gray-800">Sueldo Base Gravable</td>
                            <td class="py-5 text-right text-sm font-black text-gray-900">{{ formatCurrency(receipt.base_salary) }}</td>
                            <td class="py-5 text-right text-sm font-black text-gray-300">---</td>
                        </tr>
                        <tr v-if="receipt.bonus > 0">
                            <td class="py-5 text-sm font-bold text-gray-800">Bonos, Incentivos y Comisiones</td>
                            <td class="py-5 text-right text-sm font-black text-emerald-600">+ {{ formatCurrency(receipt.bonus) }}</td>
                            <td class="py-5 text-right text-sm font-black text-gray-300">---</td>
                        </tr>
                        <tr v-if="receipt.deductions > 0">
                            <td class="py-5 text-sm font-bold text-gray-800">Deducciones, Retenciones (ISR/IMSS)</td>
                            <td class="py-5 text-right text-sm font-black text-gray-300">---</td>
                            <td class="py-5 text-right text-sm font-black text-red-600">- {{ formatCurrency(receipt.deductions) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-4 border-gray-900">
                        <tr class="bg-gray-50 print:bg-white text-gray-900">
                            <td class="py-8 px-4 text-sm font-black uppercase tracking-[0.2em]">NETO A PAGAR</td>
                            <td colspan="2" class="py-8 px-4 text-right text-4xl font-black">{{ formatCurrency(receipt.net_total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Notes -->
            <div v-if="receipt.notes" class="mb-24 p-6 bg-zinc-50 rounded-2xl border border-zinc-100 print:bg-white">
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">NOTAS ADICIONALES</p>
                <p class="text-sm text-gray-700 italic leading-relaxed font-medium">"{{ receipt.notes }}"</p>
            </div>

            <!-- Signatures -->
            <div class="grid grid-cols-2 gap-32 pt-20">
                <div class="text-center">
                    <div class="border-b-2 border-gray-900 mb-6 h-20"></div>
                    <p class="text-[10px] font-black text-gray-900 uppercase tracking-widest">FIRMA DEL EMPLEADO</p>
                    <p class="text-[9px] text-gray-400 uppercase mt-2 font-bold">{{ receipt.employee.user.name }}</p>
                </div>
                <div class="text-center">
                    <div class="border-b-2 border-gray-900 mb-6 h-20"></div>
                    <p class="text-[10px] font-black text-gray-900 uppercase tracking-widest">AUTORIZADO POR</p>
                    <p class="text-[9px] text-gray-400 uppercase mt-2 font-bold">{{ receipt.creator?.name || 'Administrador Sistema' }}</p>
                </div>
            </div>

            <div class="mt-12 text-center print:hidden">
                <button onclick="window.print()" class="bg-gray-900 text-white px-10 py-3 rounded-2xl text-xs font-black uppercase tracking-widest hover:bg-gray-800 transition shadow-xl">Imprimir Carta</button>
            </div>
        </div>
    </div>

    <!-- TICKET FORMAT (80mm) -->
    <div v-if="format === 'ticket'" class="min-h-screen bg-white p-4 print:p-0 font-mono text-[12px] text-black">
        <div class="w-[80mm] mx-auto print:w-full">
            <!-- Header -->
            <div class="text-center mb-6">
                <img src="/logo.svg" alt="Logo" class="h-12 w-auto mx-auto mb-2" />
                <p class="font-bold text-[14px]">LUNAVALOS</p>
                <p class="text-[10px] uppercase border-y border-black border-dashed py-1 my-2">Recibo de Nómina</p>
            </div>

            <!-- Folio & Date -->
            <div class="mb-4">
                <div class="flex justify-between font-bold">
                    <span>FOLIO:</span>
                    <span>#{{ receipt.id.toString().padStart(5, '0') }}</span>
                </div>
                <div class="flex justify-between text-[11px]">
                    <span>FECHA PAGO:</span>
                    <span>{{ formatShortDate(receipt.payment_date) }}</span>
                </div>
            </div>

            <!-- Employee Info -->
            <div class="mb-4 border-b border-black border-dashed pb-4">
                <p class="font-bold underline mb-1 uppercase">EMPLEADO:</p>
                <p class="font-bold">{{ receipt.employee.user.name }}</p>
                <p class="text-[11px]">{{ receipt.employee.position }}</p>
                <p class="text-[11px]">{{ receipt.employee.employee_number }}</p>
                <p class="text-[10px] mt-1">{{ receipt.employee.rfc }}</p>
            </div>

            <!-- Period -->
            <div class="mb-4 text-center">
                <p class="text-[10px] font-bold">PERIODO:</p>
                <p class="text-[11px]">{{ formatShortDate(receipt.period_start) }} - {{ formatShortDate(receipt.period_end) }}</p>
            </div>

            <!-- Breakdown -->
            <div class="mb-6">
                <div class="flex justify-between border-b border-black mb-1 font-bold text-[11px]">
                    <span>CONCEPTO</span>
                    <span>MONTO</span>
                </div>
                <div class="flex justify-between py-1">
                    <span>SUELDO BASE</span>
                    <span>{{ formatCurrency(receipt.base_salary) }}</span>
                </div>
                <div v-if="receipt.bonus > 0" class="flex justify-between py-1">
                    <span>BONOS/EXTRAS</span>
                    <span>{{ formatCurrency(receipt.bonus) }}</span>
                </div>
                <div v-if="receipt.deductions > 0" class="flex justify-between py-1 italic">
                    <span>DEDUCCIONES</span>
                    <span>-{{ formatCurrency(receipt.deductions) }}</span>
                </div>
                <div class="border-t-2 border-black mt-2 pt-2 flex justify-between font-bold text-[14px]">
                    <span>NETO:</span>
                    <span>{{ formatCurrency(receipt.net_total) }}</span>
                </div>
            </div>

            <!-- Notes -->
            <div v-if="receipt.notes" class="mb-8 text-[10px] italic text-center">
                "* {{ receipt.notes }} *"
            </div>

            <!-- Signatures (Stacked) -->
            <div class="space-y-12">
                <div class="text-center">
                    <div class="border-b border-black w-3/4 mx-auto mb-2"></div>
                    <p class="text-[9px] font-bold uppercase">FIRMA EMPLEADO</p>
                    <p class="text-[8px] uppercase mt-1">{{ receipt.employee.user.name }}</p>
                </div>
                <div class="text-center">
                    <div class="border-b border-black w-3/4 mx-auto mb-2"></div>
                    <p class="text-[9px] font-bold uppercase">AUTORIZADO POR</p>
                    <p class="text-[8px] uppercase mt-1">{{ receipt.creator?.name || 'Administrador' }}</p>
                </div>
            </div>

            <div class="mt-12 pt-8 text-center text-[9px] border-t border-black border-dashed">
                <p>DOCUMENTO DE CONTROL INTERNO</p>
                <p>NO FISCAL</p>
            </div>

            <div class="mt-8 text-center print:hidden">
                <button onclick="window.print()" class="bg-black text-white px-6 py-2 rounded-lg text-xs font-bold uppercase tracking-wider">Imprimir Ticket</button>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    @page {
        margin: 0;
    }
    body {
        background: white !important;
    }
}
</style>
