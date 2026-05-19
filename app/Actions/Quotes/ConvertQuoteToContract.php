<?php

namespace App\Actions\Quotes;

use App\Actions\Clients\SyncClientFromQuote;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Contract;
use App\Models\Quote;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ConvertQuoteToContract
{
    /**
     * Convierte una Cotización en Contrato, crea el Cliente (si no existe),
     * el Usuario para el Area de Clientes y genera los Pagos (Anticipo + Saldo).
     */
    public function __invoke(Quote $quote, array $data, User $actor): Contract
    {
        return DB::transaction(function () use ($quote, $data, $actor) {
            $client = $this->getOrCreateClient($quote);
            $this->getOrCreateClientUser($client, $quote);

            $quote->loadMissing('package', 'items', 'addons');

            // Tipo de cobro dominante del paquete (driver del cronograma).
            $packageBilling = optional($quote->package)->billing_type
                ?? $this->inferBillingType($quote);

            $months   = (int) ($quote->package_payment_plan_months ?? 1);
            $months   = max(1, $months);
            $total    = (float) $quote->total;
            $anticipo = (float) $data['anticipo_amount'];

            // `monthly_amount` representa la cuota recurrente real del contrato:
            //   - mensualidad: monto mensual fijo (total_monthly)
            //   - anualidad: monto anual / 12 (referencia MRR)
            //   - pago único: (total - anticipo) / (months - 1)
            $monthlyAmount = $this->calcMonthlyAmount($quote, $packageBilling, $total, $anticipo, $months);

            $contract = Contract::create([
                'quote_id'             => $quote->id,
                'client_id'            => $client->id,
                'token'                => Str::random(40),
                'contract_number'      => $this->generateContractNumber(),
                'start_date'           => $data['paid_at'] ?? now()->toDateString(),
                'subtotal'             => $quote->subtotal,
                'discount_amount'      => $quote->discount_amount ?? 0,
                'currency'             => $quote->currency ?? config('currencies.default'),
                'exchange_rate'        => $quote->exchange_rate ?? 1,
                'iva_amount'           => $quote->iva_amount,
                'retentions_total'     => (($quote->isr_retention_amount ?? 0) + ($quote->iva_retention_amount ?? 0)),
                'total_amount'         => $total,
                'anticipo_amount'      => $anticipo,
                'monthly_amount'       => $monthlyAmount,
                'payment_plan_months'  => $months,
                'status'               => 'activo',
                'created_by_user_id'   => $actor->id,
            ]);

            // 1. Registramos el pago inicial (Anticipo) como Pagado
            $this->registerAnticipo($contract, $client, $data);

            // 2. Generamos el cronograma respetando el tipo de cobro del paquete
            $this->generateSchedule($contract, $client, $quote, $packageBilling);

            // 2.b Renovaciones anuales futuras: si la cotización tiene items
            //     anuales o un Precio de Renovación, programamos pagos
            //     anuales adicionales durante el horizonte configurado para
            //     que cobranza pueda solicitarlos a futuro (cancelables).
            $this->generateAnnualRenewals($contract, $client, $quote, $packageBilling);

            // 3. Materializamos Servicios, Costos y datos fiscales en el Cliente
            //    (fuente única de verdad: SyncClientFromQuote, idempotente).
            (new SyncClientFromQuote)($client, $quote);

            // 4. Marcamos la cotización como Convertida
            $quote->update(['status' => 'Convertida']);

            return $contract;
        });
    }

    private function getOrCreateClient(Quote $quote): Client
    {
        // En un flujo real, buscaríamos por RFC o Email. 
        // Aquí simplificamos buscando por email de contacto de la cotización.
        return Client::firstOrCreate(
            ['email' => $quote->email],
            [
                'business_name'         => $quote->client_name,
                'contact_name'          => $quote->contact_name,
                'phone'                 => $quote->phone,
                'tax_regime'            => $quote->tax_regime,
                'applies_iva'           => $quote->applies_iva,
                'iva_rate'              => $quote->iva_rate,
                'applies_isr_retention' => $quote->applies_isr_retention,
                'isr_retention_rate'    => $quote->isr_retention_rate,
                'applies_iva_retention' => $quote->applies_iva_retention,
                'iva_retention_rate'    => $quote->iva_retention_rate,
            ]
        );
    }

    private function getOrCreateClientUser(Client $client, Quote $quote): User
    {
        $user = User::where('email', $quote->email)->first();

        if (!$user) {
            $user = User::create([
                'name'      => $quote->contact_name,
                'email'     => $quote->email,
                'password'  => Hash::make(Str::random(12)),
                'client_id' => $client->id,
            ]);
        } elseif (!$user->client_id) {
            $user->forceFill(['client_id' => $client->id])->save();
        }

        // Ensure the configured client role is assigned (idempotent).
        $clientRole = \Spatie\Permission\Models\Role::firstOrCreate([
            'name'       => config('roles.client', 'Cliente'),
            'guard_name' => 'web',
        ]);

        if (!$user->hasRole($clientRole)) {
            $user->assignRole($clientRole);
        }

        return $user;
    }

    private function registerAnticipo(Contract $contract, Client $client, array $data): void
    {
        ClientPayment::create([
            'contract_id'        => $contract->id,
            'client_id'          => $client->id,
            'amount'             => $data['anticipo_amount'],
            'type'               => 'anticipo',
            'status'             => 'conciliado',
            'payment_method'     => $data['payment_method'],
            'payment_reference'  => $data['payment_reference'],
            'paid_at'            => $data['paid_at'],
            'currency'           => $contract->currency ?? config('currencies.default'),
            'exchange_rate'      => $contract->exchange_rate ?? 1,
            'notes'              => $data['payment_notes'] ?? null,
        ]);
    }

    private function generateContractNumber(): string
    {
        $year = now()->format('Y');
        $next = (int) (Contract::whereYear('created_at', $year)->max('id') ?? 0) + 1;

        return sprintf('CT-%s-%05d', $year, $next);
    }

    /**
     * Genera el cronograma de pagos respetando el tipo de cobro del paquete:
     *
     *   - `unique`  → pago único con opción a plazos. Anticipo + (N-1) mensualidades
     *                 iguales hasta cubrir el saldo. (Comportamiento histórico.)
     *
     *   - `monthly` → mensualidad recurrente. Cada mes se cobra el monto mensual
     *                 fijo (`total_monthly`). El anticipo cuenta como mes 1; se
     *                 generan (N-1) cuotas mensuales de ese mismo monto. Si la
     *                 cotización trae un componente único (setup), su remanente
     *                 se distribuye uniformemente sobre esas N-1 cuotas.
     *
     *   - `annual`  → plan anual. Anticipo cuenta como año 1; se generan
     *                 (años-1) cuotas anuales del monto anual. Años se derivan
     *                 de `payment_plan_months / 12` (mínimo 1).
     *
     * Idempotente: si ya hay cuotas registradas para el contrato, no duplica.
     */
    public function generateSchedule(Contract $contract, Client $client, ?Quote $quote = null, ?string $packageBilling = null): void
    {
        $hasSchedule = ClientPayment::where('contract_id', $contract->id)
            ->where('type', 'mensualidad')
            ->exists();
        if ($hasSchedule) {
            return;
        }

        $quote          = $quote ?: $contract->quote;
        $packageBilling = $packageBilling
            ?: (optional($quote?->package)->billing_type ?? $this->inferBillingType($quote));

        $months   = max(1, (int) $contract->payment_plan_months);
        $anticipo = (float) $contract->anticipo_amount;
        $total    = (float) $contract->total_amount;

        $totalUnique  = (float) ($quote?->total_unique ?? 0);
        $totalMonthly = (float) ($quote?->total_monthly ?? 0);
        $totalAnnual  = (float) ($quote?->total_annual ?? 0);

        $start    = \Illuminate\Support\Carbon::parse($contract->start_date);
        $currency = $contract->currency ?? config('currencies.default');
        $rate     = $contract->exchange_rate ?? 1;

        $create = function (float $amount, string $dueDate, int $installment) use ($contract, $client, $currency, $rate) {
            ClientPayment::create([
                'contract_id'        => $contract->id,
                'client_id'          => $client->id,
                'type'               => 'mensualidad',
                'status'             => 'programado',
                'amount'             => round($amount, 2),
                'currency'           => $currency,
                'exchange_rate'      => $rate,
                'due_date'           => $dueDate,
                'installment_number' => $installment,
            ]);
        };

        // ── MENSUALIDAD ────────────────────────────────────────────────────
        if ($packageBilling === 'monthly') {
            $remaining = max(0, $months - 1);
            if ($remaining === 0) {
                return;
            }

            // Cuota recurrente = monto mensual de la cotización. Si por alguna
            // razón no hay items mensuales, caemos al promedio del total.
            $recurring = $totalMonthly > 0 ? $totalMonthly : round($total / $months, 2);

            // El anticipo se considera el "mes 1". Si el cliente abonó más que
            // un mes, el excedente reduce los componentes únicos pendientes.
            $excessAnticipo  = max(0, $anticipo - $recurring);
            $uniqueRemainder = max(0, $totalUnique - $excessAnticipo);

            $perInstallmentUnique = $remaining > 0 ? round($uniqueRemainder / $remaining, 2) : 0;
            $accumulatedUnique    = 0.0;

            for ($i = 1; $i <= $remaining; $i++) {
                $extra = ($i === $remaining)
                    ? round($uniqueRemainder - $accumulatedUnique, 2)
                    : $perInstallmentUnique;
                $accumulatedUnique += $extra;

                $create(
                    $recurring + $extra,
                    $start->copy()->addMonths($i)->toDateString(),
                    $i + 1
                );
            }
            return;
        }

        // ── ANUALIDAD ──────────────────────────────────────────────────────
        if ($packageBilling === 'annual') {
            $years      = max(1, (int) floor($months / 12));
            $remaining  = max(0, $years - 1);

            // Saldo anual: el componente anual + cualquier remanente único no
            // cubierto por el anticipo. Lo dividimos en (años-1) cuotas anuales.
            $annualBalance = max(0, $totalAnnual + $totalUnique + $totalMonthly - $anticipo);

            if ($remaining === 0 || $annualBalance <= 0) {
                return;
            }

            $perYear = round($annualBalance / $remaining, 2);
            for ($i = 1; $i <= $remaining; $i++) {
                $amount = ($i === $remaining)
                    ? round($annualBalance - ($perYear * ($remaining - 1)), 2)
                    : $perYear;
                $create(
                    $amount,
                    $start->copy()->addYears($i)->toDateString(),
                    $i + 1
                );
            }
            return;
        }

        // ── PAGO ÚNICO con plan a meses (comportamiento histórico) ────────
        $balance   = max(0, $total - $anticipo);
        $remaining = max(1, $months - 1);
        if ($balance <= 0) {
            return;
        }

        $perMonth = round($balance / $remaining, 2);
        for ($i = 1; $i <= $remaining; $i++) {
            $amount = ($i === $remaining)
                ? round($balance - ($perMonth * ($remaining - 1)), 2)
                : $perMonth;
            $create(
                $amount,
                $start->copy()->addMonths($i)->toDateString(),
                $i + 1
            );
        }
    }

    /**
     * Inferimos un horizonte plurianual de cuotas de renovación cuando la
     * cotización trae componentes anuales (items billing_type=annual, addons
     * annual/semiannual o items con `unit_renewal_price > 0`).
     *
     * - Para paquetes `annual`, el cronograma principal ya cubre años 2..K
     *   donde K = payment_plan_months/12. Aquí completamos hasta `forecast`.
     * - Para paquetes `monthly`/`unique`, el año 1 está cubierto por el plan
     *   principal; aquí agendamos años 2..forecast.
     *
     * Cada cuota anual se marca como `programado` con un installment_number
     * incremental, para que cobranza pueda enviar la solicitud de pago.
     */
    private function generateAnnualRenewals(Contract $contract, Client $client, Quote $quote, string $packageBilling): void
    {
        $forecast = (int) config('quotes.renewals.forecast_years', 10);
        if ($forecast <= 1) {
            return;
        }

        // 1) Annual items y addons.
        $annualItems = $quote->items
            ->where('billing_type', 'annual')
            ->sum(fn ($i) => (float) $i->unit_price * (float) ($i->quantity ?: 1));

        $annualAddons = collect($quote->addons ?? [])
            ->filter(fn ($a) => in_array($a->billing_cycle, ['annual', 'semiannual'], true))
            ->sum(fn ($a) => (float) $a->unit_price * (float) ($a->quantity ?: 1));

        // 2) Items con Precio de Renovación / Anualidad explícito (pago único
        //    pero con renovación anual). Sólo cuenta como renovación a partir
        //    del año 2.
        $renewalPriced = $quote->items
            ->where('unit_renewal_price', '>', 0)
            ->sum(fn ($i) => (float) $i->unit_renewal_price * (float) ($i->quantity ?: 1));

        $perYear = round($annualItems + $annualAddons + $renewalPriced, 2);
        if ($perYear <= 0) {
            return;
        }

        // Años ya cubiertos por el cronograma principal:
        //   - annual:  payment_plan_months / 12
        //   - resto:   1 (año 1 vía anticipo + plan mensual o pago único)
        $alreadyCoveredYears = $packageBilling === 'annual'
            ? max(1, (int) floor(((int) $contract->payment_plan_months) / 12))
            : 1;

        if ($forecast <= $alreadyCoveredYears) {
            return;
        }

        $start    = \Illuminate\Support\Carbon::parse($contract->start_date);
        $currency = $contract->currency ?? config('currencies.default');
        $rate     = $contract->exchange_rate ?? 1;

        $nextInstallment = (int) (ClientPayment::where('contract_id', $contract->id)->max('installment_number') ?: 0) + 1;

        for ($year = $alreadyCoveredYears + 1; $year <= $forecast; $year++) {
            ClientPayment::create([
                'contract_id'        => $contract->id,
                'client_id'          => $client->id,
                'type'               => 'mensualidad',
                'status'             => 'programado',
                'concept'            => "Renovación anual · Año {$year}",
                'amount'             => $perYear,
                'currency'           => $currency,
                'exchange_rate'      => $rate,
                'due_date'           => $start->copy()->addYears($year - 1)->toDateString(),
                'installment_number' => $nextInstallment++,
            ]);
        }
    }

    /**
     * Calcula el monto recurrente "de referencia" para guardar en el contrato.
     * Es lo que se muestra como MRR / cuota mensual en la UI.
     */
    private function calcMonthlyAmount(Quote $quote, string $packageBilling, float $total, float $anticipo, int $months): float
    {
        return match ($packageBilling) {
            'monthly' => round((float) ($quote->total_monthly ?: $total), 2),
            'annual'  => round(((float) ($quote->total_annual ?: $total)) / 12, 2),
            default   => $months > 1
                ? round(max(0, $total - $anticipo) / max(1, $months - 1), 2)
                : 0.0,
        };
    }

    /**
     * Si el paquete no expone `billing_type`, inferimos del mix de items:
     * - hay items mensuales → monthly
     * - hay items anuales   → annual
     * - sólo únicos         → unique
     */
    private function inferBillingType(?Quote $quote): string
    {
        if (!$quote) {
            return 'unique';
        }
        if ((float) $quote->total_monthly > 0) {
            return 'monthly';
        }
        if ((float) $quote->total_annual > 0) {
            return 'annual';
        }
        return 'unique';
    }
}