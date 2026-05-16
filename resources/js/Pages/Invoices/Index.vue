<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    invoices:   { type: Object, required: true },
    filters:    { type: Object, default: () => ({}) },
    configured: { type: Boolean, default: false },
});

const filterForm = ref({
    status: props.filters.status ?? '',
    q:      props.filters.q      ?? '',
});

const fmtMoney = (n) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n || 0));
const fmtDate  = (d) => d ? new Date(d).toLocaleDateString('es-MX') : '—';

const apply = () => router.get(route('invoices.index'), filterForm.value, { preserveScroll: true, preserveState: true });

const cancelInvoice = (inv) => {
    const motive = prompt('Motivo (01=Comprobante con errores con relación, 02=Comprobante con errores sin relación, 03=No se llevó a cabo la operación, 04=Operación nominativa a global):', '02');
    if (! motive) return;
    router.post(route('invoices.cancel', inv.id), { motive }, { preserveScroll: true });
};

const statusColors = {
    issued:   'bg-emerald-100 text-emerald-800',
    canceled: 'bg-gray-200 text-gray-600',
    error:    'bg-rose-100 text-rose-800',
};
</script>

<template>
    <Head title="Facturas (CFDI)" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Facturas — CFDI 4.0</h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <div v-if="!configured" class="bg-amber-50 border border-amber-200 text-amber-800 rounded p-4 text-sm">
                    Facturama no está configurado. Define <code>FACTURAMA_API_USER</code> y <code>FACTURAMA_API_PASSWORD</code> en <code>.env</code> para emitir CFDIs.
                </div>

                <div class="bg-white shadow rounded-lg p-4 flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="text-xs text-gray-600 block">Estatus</label>
                        <select v-model="filterForm.status" class="border rounded px-2 py-1 text-sm">
                            <option value="">Todos</option>
                            <option value="issued">Emitidas</option>
                            <option value="canceled">Canceladas</option>
                            <option value="error">Con error</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <label class="text-xs text-gray-600 block">UUID / Folio</label>
                        <input v-model="filterForm.q" class="w-full border rounded px-2 py-1 text-sm" />
                    </div>
                    <button @click="apply" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-1.5 rounded text-sm">Aplicar</button>
                </div>

                <div class="bg-white shadow rounded-lg overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-left">UUID</th>
                                <th class="px-4 py-2 text-left">Cliente</th>
                                <th class="px-4 py-2 text-left">Concepto</th>
                                <th class="px-4 py-2 text-right">Total</th>
                                <th class="px-4 py-2 text-left">Emitida</th>
                                <th class="px-4 py-2 text-left">Estatus</th>
                                <th class="px-4 py-2 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="inv in invoices.data" :key="inv.id">
                                <td class="px-4 py-2 font-mono text-xs">{{ inv.uuid || inv.folio || `#${inv.id}` }}</td>
                                <td class="px-4 py-2">{{ inv.client?.business_name }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ inv.payment?.concept }}</td>
                                <td class="px-4 py-2 text-right font-mono">{{ fmtMoney(inv.total) }}</td>
                                <td class="px-4 py-2">{{ fmtDate(inv.issued_at) }}</td>
                                <td class="px-4 py-2">
                                    <span :class="['inline-block px-2 py-0.5 rounded text-xs font-semibold', statusColors[inv.status] || 'bg-gray-100']">
                                        {{ inv.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                                    <a v-if="inv.pdf_path" :href="route('invoices.download', { invoice: inv.id, type: 'pdf' })" target="_blank" class="text-indigo-600 hover:underline text-xs">PDF</a>
                                    <a v-if="inv.xml_path" :href="route('invoices.download', { invoice: inv.id, type: 'xml' })" class="text-indigo-600 hover:underline text-xs">XML</a>
                                    <button v-if="inv.status === 'issued'" @click="cancelInvoice(inv)" class="text-rose-600 hover:underline text-xs">Cancelar</button>
                                </td>
                            </tr>
                            <tr v-if="!invoices.data.length">
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400">Sin facturas.</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex justify-between items-center p-3 text-sm text-gray-600">
                        <span>{{ invoices.from || 0 }}–{{ invoices.to || 0 }} de {{ invoices.total }}</span>
                        <div class="space-x-1">
                            <Link v-for="link in invoices.links" :key="link.label"
                                :href="link.url || ''"
                                :class="['px-2 py-1 rounded text-xs', link.active ? 'bg-indigo-600 text-white' : 'bg-gray-100 hover:bg-gray-200', !link.url && 'opacity-30 pointer-events-none']"
                                v-html="link.label"
                                preserve-scroll />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
