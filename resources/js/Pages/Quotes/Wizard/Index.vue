<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import WizardStepper from '@/Components/WizardStepper.vue';
import StepPackage from './StepPackage.vue';
import StepAddons from './StepAddons.vue';
import StepClient from './StepClient.vue';
import StepSummary from './StepSummary.vue';
import { useMoney } from '@/Composables/useMoney';

const { defaultCurrency } = useMoney();

const props = defineProps({
    services: Array,
    addons: Array,
    addonsByCategory: Object,
    clients: Array,
    categoryLabels: Object,
    cycleLabels: Object,
    taxRegimes: Object,
    cfdiUses: Object,
    taxDefaults: Object,
    maxPaymentMonths: Number,
    defaultValidity: Number,
    quote: { type: Object, default: null },
});

const isEdit = !! props.quote;

const today = new Date().toISOString().slice(0, 10);
const validUntil = new Date(Date.now() + (props.defaultValidity || 15) * 86400000).toISOString().slice(0, 10);

const stepLabels = ['Paquete', 'Adicionales', 'Cliente', 'Resumen'];
const current = ref(0);

const initial = isEdit
    ? {
        package_service_id: props.quote.package_service_id,
        package_payment_plan_months: props.quote.package_payment_plan_months || 1,
        client_id: props.quote.client_id,
        client_name: props.quote.client_name || '',
        contact_name: props.quote.contact_name || '',
        email: props.quote.email || '',
        phone: props.quote.phone || '',
        issue_date: props.quote.issue_date ? String(props.quote.issue_date).slice(0, 10) : today,
        valid_until: props.quote.valid_until ? String(props.quote.valid_until).slice(0, 10) : validUntil,
        duration: props.quote.duration || '',
        currency: props.quote.currency || defaultCurrency.value,
        discount_amount: Number(props.quote.discount_amount || 0),
        notes: props.quote.notes || '',
        observations: props.quote.observations || '',
        tax_regime: props.quote.tax_regime || '',
        applies_iva: !! props.quote.applies_iva,
        iva_rate: Number(props.quote.iva_rate || 0),
        applies_isr_retention: !! props.quote.applies_isr_retention,
        isr_retention_rate: Number(props.quote.isr_retention_rate || 0),
        applies_iva_retention: !! props.quote.applies_iva_retention,
        iva_retention_rate: Number(props.quote.iva_retention_rate || 0),
        include_payment_terms: props.quote.include_payment_terms !== false,
        status: props.quote.status || 'Borrador',
        addons: (props.quote.addons || []).map(a => ({
            service_addon_id: a.service_addon_id,
            quantity: a.quantity,
            is_required: !! a.is_required,
        })),
    }
    : {
        package_service_id: null,
        package_payment_plan_months: 1,
        client_id: null,
        client_name: '',
        contact_name: '',
        email: '',
        phone: '',
        issue_date: today,
        valid_until: validUntil,
        duration: '',
        currency: defaultCurrency.value,
        discount_amount: 0,
        notes: '',
        observations: '',
        tax_regime: '',
        applies_iva: true,
        iva_rate: 16,
        applies_isr_retention: false,
        isr_retention_rate: 0,
        applies_iva_retention: false,
        iva_retention_rate: 0,
        include_payment_terms: true,
        status: 'Borrador',
        addons: [],
    };

const form = useForm(initial);

const selectedPackage = computed(() => props.services.find(s => s.id === form.package_service_id) || null);
const requiredCategories = computed(() => {
    const svc = selectedPackage.value;
    if (!svc) return [];
    if (Array.isArray(svc.required_addon_categories_list) && svc.required_addon_categories_list.length) {
        return svc.required_addon_categories_list;
    }
    if (Array.isArray(svc.required_addon_categories) && svc.required_addon_categories.length) {
        return svc.required_addon_categories;
    }
    return svc.required_addon_category ? [svc.required_addon_category] : [];
});

const selectedAddons = computed(() =>
    form.addons.map(row => {
        const a = props.addons.find(x => x.id === row.service_addon_id);
        return a ? {
            ...row,
            name: a.name,
            unit_price: a.price,
            billing_cycle: a.billing_cycle,
            billing_cycle_months: a.billing_cycle_months,
        } : row;
    })
);

const goto = (i) => { current.value = i; };
const next = () => { current.value = Math.min(current.value + 1, stepLabels.length - 1); };
const back = () => { current.value = Math.max(current.value - 1, 0); };

// Aplica un patch parcial al form de Inertia. Necesario porque los steps
// emiten un objeto nuevo con `{...modelValue, change}` y v-model="form" no
// puede reasignar la const del useForm.
const patchForm = (patch) => {
    if (! patch || typeof patch !== 'object') return;
    for (const k of Object.keys(patch)) {
        form[k] = patch[k];
    }
};

const submit = () => {
    const transformed = form.transform(data => ({
        ...data,
        addons: data.addons.map(a => ({
            service_addon_id: a.service_addon_id,
            quantity: a.quantity,
            is_required: !!a.is_required,
        })),
    }));

    if (isEdit) {
        transformed.put(route('quotes.wizard.update', props.quote.id), { preserveScroll: true });
    } else {
        transformed.post(route('quotes.wizard.store'), { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="isEdit ? `Editar Cotización #${quote.id}` : 'Nueva Cotización'" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                {{ isEdit ? `Editar Cotización #${quote.id}` : 'Nueva Cotización' }}
            </h2>
        </template>

        <div class="py-8">
            <div class="container mx-auto bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-gray-100 dark:border-zinc-800 p-6">
                <WizardStepper :steps="stepLabels" :current="current" @goto="goto" />

                <StepPackage
                    v-if="current === 0"
                    :model-value="form"
                    @update:model-value="patchForm"
                    :services="services"
                    :max-payment-months="maxPaymentMonths"
                    :category-labels="categoryLabels"
                    @next="next"
                />
                <StepAddons
                    v-else-if="current === 1"
                    :model-value="form"
                    @update:model-value="patchForm"
                    :addons-by-category="addonsByCategory"
                    :tax-regimes="taxRegimes"
                    :cfdi-uses="cfdiUses"
                    :tax-defaults="taxDefaults"
                    :category-labels="categoryLabels"
                    :cycle-labels="cycleLabels"
                    :required-categories="requiredCategories"
                    @next="next" @back="back"
                />
                <StepClient
                    v-else-if="current === 2"
                    :model-value="form"
                    @update:model-value="patchForm"
                    :clients="clients"
                    :tax-regimes="taxRegimes"
                    :cfdi-uses="cfdiUses"
                    :tax-defaults="taxDefaults"
                    :errors="form.errors"
                    @next="next" @back="back"
                />
                <StepSummary
                    v-else-if="current === 3"
                    :model-value="form"
                    @update:model-value="patchForm"
                    :selected-package="selectedPackage"
                    :selected-addons="selectedAddons"
                    :cycle-labels="cycleLabels"
                    :submitting="form.processing"
                    @submit="submit" @back="back"
                />

                <div v-if="Object.keys(form.errors).length" class="mt-6 p-3 rounded bg-red-50 text-red-800 text-sm">
                    <ul class="list-disc ml-5">
                        <li v-for="(msg, key) in form.errors" :key="key">{{ msg }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
