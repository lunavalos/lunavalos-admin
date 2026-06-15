<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { ArrowLeftIcon, CurrencyDollarIcon, CalendarDaysIcon } from '@heroicons/vue/24/outline';
import { useMoney } from '@/Composables/useMoney';

const props = defineProps({
    contract:       { type: Object, required: true },
    statusOptions:  { type: Array,  default: () => [] },
    renewalOptions: { type: Array,  default: () => [] },
});

const form = useForm({
    start_date:           props.contract.start_date?.split('T')[0] ?? '',
    end_date:             props.contract.end_date?.split('T')[0]   ?? '',
    total_amount:         props.contract.total_amount              ?? '',
    monthly_amount:       props.contract.monthly_amount            ?? '',
    payment_plan_months:  props.contract.payment_plan_months       ?? 12,
    status:               props.contract.status                    ?? 'signed',
    renewal_status:       props.contract.renewal_status            ?? 'none',
    legal_name:           props.contract.legal_name                ?? '',
    tax_id:               props.contract.tax_id                    ?? '',
    fiscal_address:       props.contract.fiscal_address            ?? '',
    postal_code:          props.contract.postal_code               ?? '',
    legal_representative: props.contract.legal_representative      ?? '',
    notes:                props.contract.notes                     ?? '',
    domain_names:         props.contract.domain_names              ?? '',
});

const clientName = computed(() =>
    props.contract.client?.business_name ?? props.contract.quote?.client_name ?? '—'
);

const isRenewal = computed(() =>
    !props.contract.quote_id || parseFloat(props.contract.anticipo_amount || 0) === 0
);

const statusLabel  = { signed: 'Firmado / Activo', pending: 'Pendiente de firma', voided: 'Cancelado' };
const renewalLabel = { none: 'Sin marca', pending: 'Pendiente', notified: 'Notificado', renewed: 'Renovado', declined: 'No renovó / Declinó', overdue: 'Vencido' };
const renewalColor = { none: 'text-gray-600', pending: 'text-amber-600', notified: 'text-blue-600', renewed: 'text-emerald-600', declined: 'text-rose-600', overdue: 'text-red-700' };
const billingLabel = { annual: 'Anual', monthly: 'Mensual', once: 'Único' };
const billingBadge = { annual: 'bg-blue-100 text-blue-700', monthly: 'bg-emerald-100 text-emerald-700', once: 'bg-gray-100 text-gray-600' };

const { fmt } = useMoney();
const fmtDate = (d) => d ? new Date(d + 'T00:00:00').toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';

const services = computed(() => props.contract.client_services ?? []);

const submit = () => form.put(route('contracts.update', props.contract.id));
</script>

<template>
    <Head :title="'Editar contrato ' + (contract.contract_number ?? contract.id)" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('contracts.admin.show', contract.id)"
                      class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
                    <ArrowLeftIcon class="w-5 h-5" />
                </Link>
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                        Editar contrato
                        <span class="text-primary font-mono ml-1">{{ contract.contract_number ?? ('#' + contract.id) }}</span>
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-zinc-400 mt-0.5">{{ clientName }}</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Flash -->
                <div v-if="$page.props.flash?.success"
                     class="mb-6 p-4 bg-emerald-50 border border-emerald-300 text-emerald-700 rounded-lg text-sm">
                    {{ $page.props.flash.success }}
                </div>

                <form @submit.prevent="submit" class="space-y-6">

                    <!-- Estado del contrato -->
                    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-6 space-y-5">
                        <h3 class="font-semibold text-gray-700 dark:text-gray-200 border-b border-gray-100 dark:border-zinc-800 pb-3">Estado</h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <InputLabel value="Estado del contrato" />
                                <select v-model="form.status"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 shadow-sm text-sm focus:border-primary focus:ring-primary">
                                    <option v-for="s in statusOptions" :key="s" :value="s">{{ statusLabel[s] ?? s }}</option>
                                </select>
                                <p v-if="form.status === 'voided'" class="mt-1 text-xs text-rose-600">
                                    ⚠ Marcar como "Cancelado" suspende el contrato. El cliente verá el servicio como inactivo.
                                </p>
                                <InputError class="mt-1" :message="form.errors.status" />
                            </div>

                            <div>
                                <InputLabel value="Estado de renovación" />
                                <select v-model="form.renewal_status"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 shadow-sm text-sm focus:border-primary focus:ring-primary">
                                    <option v-for="s in renewalOptions" :key="s" :value="s"
                                            :class="renewalColor[s]">{{ renewalLabel[s] ?? s }}</option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.renewal_status" />
                            </div>
                        </div>
                    </div>

                    <!-- Vigencia -->
                    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-6 space-y-5">
                        <h3 class="font-semibold text-gray-700 dark:text-gray-200 border-b border-gray-100 dark:border-zinc-800 pb-3">Vigencia</h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <InputLabel for="start_date" value="Inicio del contrato" />
                                <TextInput id="start_date" type="date" v-model="form.start_date"
                                           class="mt-1 block w-full" />
                                <InputError class="mt-1" :message="form.errors.start_date" />
                            </div>
                            <div>
                                <InputLabel for="end_date" value="Fecha de vencimiento / renovación" />
                                <TextInput id="end_date" type="date" v-model="form.end_date"
                                           class="mt-1 block w-full" />
                                <p class="mt-1 text-xs text-gray-400">
                                    {{ isRenewal ? 'Cuándo le tocará pagar la anualidad.' : 'Fecha en que vence el contrato.' }}
                                    Déjalo vacío si es mensual recurrente sin fin.
                                </p>
                                <InputError class="mt-1" :message="form.errors.end_date" />
                            </div>
                        </div>
                    </div>

                    <!-- Desglose de Servicios -->
                    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-6 space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-zinc-800 pb-3">
                            <h3 class="font-semibold text-gray-700 dark:text-gray-200">Desglose de Servicios</h3>
                            <span class="text-xs text-gray-400">{{ services.length }} servicio(s) enlazado(s)</span>
                        </div>

                        <div v-if="services.length" class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <div v-for="s in services" :key="s.id"
                                 class="flex items-center justify-between py-3 gap-4">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span :class="['shrink-0 px-2 py-0.5 rounded text-[11px] font-semibold', billingBadge[s.billing_type] ?? 'bg-gray-100 text-gray-600']">
                                        {{ billingLabel[s.billing_type] ?? s.billing_type }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate">{{ s.service_name }}</p>
                                        <p v-if="s.renewal_date" class="text-[11px] text-gray-400 flex items-center gap-1 mt-0.5">
                                            <CalendarDaysIcon class="w-3 h-3" />
                                            Vence: {{ fmtDate(s.renewal_date) }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-1">
                                        <CurrencyDollarIcon class="w-3.5 h-3.5 text-gray-400" />
                                        {{ fmt(s.renewal_amount, s.currency) }}
                                        <span class="text-[11px] text-gray-400 font-normal">
                                            {{ s.billing_type === 'monthly' ? '/mes' : s.billing_type === 'annual' ? '/año' : '' }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <p v-else class="text-sm text-gray-400 italic py-2">
                            Este contrato no tiene servicios enlazados aún.
                            Los servicios se pueden asociar desde la sección del cliente.
                        </p>
                    </div>

                    <!-- Montos -->
                    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-6 space-y-5">
                        <h3 class="font-semibold text-gray-700 dark:text-gray-200 border-b border-gray-100 dark:border-zinc-800 pb-3">Montos</h3>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                            <div>
                                <InputLabel for="total_amount" :value="isRenewal ? 'Monto de renovación anual' : 'Total del contrato'" />
                                <TextInput id="total_amount" type="number" step="0.01" v-model="form.total_amount"
                                           class="mt-1 block w-full" />
                                <InputError class="mt-1" :message="form.errors.total_amount" />
                            </div>
                            <div>
                                <InputLabel for="monthly_amount" :value="isRenewal ? 'Ref. mensual (total ÷ 12)' : 'Mensualidad'" />
                                <TextInput id="monthly_amount" type="number" step="0.01" v-model="form.monthly_amount"
                                           class="mt-1 block w-full" />
                                <InputError class="mt-1" :message="form.errors.monthly_amount" />
                            </div>
                            <div>
                                <InputLabel for="payment_plan_months" value="Duración (meses)" />
                                <TextInput id="payment_plan_months" type="number" min="1" v-model="form.payment_plan_months"
                                           class="mt-1 block w-full" />
                                <InputError class="mt-1" :message="form.errors.payment_plan_months" />
                            </div>
                        </div>
                    </div>

                    <!-- Datos fiscales -->
                    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-6 space-y-5">
                        <h3 class="font-semibold text-gray-700 dark:text-gray-200 border-b border-gray-100 dark:border-zinc-800 pb-3">Datos fiscales del cliente</h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <InputLabel for="legal_name" value="Razón social" />
                                <TextInput id="legal_name" type="text" v-model="form.legal_name" class="mt-1 block w-full" />
                                <InputError class="mt-1" :message="form.errors.legal_name" />
                            </div>
                            <div>
                                <InputLabel for="tax_id" value="RFC" />
                                <TextInput id="tax_id" type="text" v-model="form.tax_id" class="mt-1 block w-full font-mono" />
                                <InputError class="mt-1" :message="form.errors.tax_id" />
                            </div>
                            <div class="sm:col-span-2">
                                <InputLabel for="fiscal_address" value="Dirección fiscal" />
                                <TextInput id="fiscal_address" type="text" v-model="form.fiscal_address" class="mt-1 block w-full" />
                                <InputError class="mt-1" :message="form.errors.fiscal_address" />
                            </div>
                            <div>
                                <InputLabel for="postal_code" value="Código postal" />
                                <TextInput id="postal_code" type="text" v-model="form.postal_code" class="mt-1 block w-full" />
                                <InputError class="mt-1" :message="form.errors.postal_code" />
                            </div>
                            <div>
                                <InputLabel for="legal_representative" value="Representante legal" />
                                <TextInput id="legal_representative" type="text" v-model="form.legal_representative" class="mt-1 block w-full" />
                                <InputError class="mt-1" :message="form.errors.legal_representative" />
                            </div>
                        </div>
                    </div>

                    <!-- Dominio / Hosting incluidos -->
                    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-6 space-y-4">
                        <h3 class="font-semibold text-gray-700 dark:text-gray-200 border-b border-gray-100 dark:border-zinc-800 pb-3">Dominio / Hosting incluido</h3>
                        <p class="text-xs text-gray-500 dark:text-zinc-400">Si el contrato incluye dominio y/o hosting, indícalo aquí. Aparecerá en el recibo de renovación y en el correo al cliente. Si se deja vacío, se usará el dominio registrado en el perfil del cliente.</p>
                        <div>
                            <InputLabel for="domain_names" value="Dominio(s) del contrato" />
                            <TextInput id="domain_names" type="text" v-model="form.domain_names"
                                       class="mt-1 block w-full font-mono text-sm"
                                       placeholder="ejemplo.com, ejemplo.mx" />
                            <InputError class="mt-1" :message="form.errors.domain_names" />
                        </div>
                    </div>

                    <!-- Notas internas -->
                    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-6 space-y-4">
                        <h3 class="font-semibold text-gray-700 dark:text-gray-200 border-b border-gray-100 dark:border-zinc-800 pb-3">Notas internas</h3>
                        <textarea v-model="form.notes" rows="5"
                                  class="mt-1 block w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 shadow-sm text-sm focus:border-primary focus:ring-primary"
                                  placeholder="Servicios incluidos, observaciones, historial de cambios…" />
                        <InputError :message="form.errors.notes" />
                    </div>

                    <!-- Acciones -->
                    <div class="flex items-center justify-between pt-2">
                        <Link :href="route('contracts.admin.show', contract.id)"
                              class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition">
                            ← Cancelar y volver
                        </Link>
                        <button type="submit"
                                :disabled="form.processing"
                                :class="{ 'opacity-50': form.processing }"
                                class="bg-primary hover:bg-secondary text-white px-6 py-2.5 rounded-lg font-semibold shadow text-sm transition">
                            Guardar cambios
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
