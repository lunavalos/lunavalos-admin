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

            $months = $quote->package_payment_plan_months ?? 1;
            $total  = (float) $quote->total;
            $anticipo = (float) $data['anticipo_amount'];
            $balance  = $total - $anticipo;
            $monthly  = $months > 1 ? round($balance / ($months - 1), 2) : 0;

            $contract = Contract::create([
                'quote_id'             => $quote->id,
                'client_id'            => $client->id,
                'token'                => Str::random(40),
                'contract_number'      => $this->generateContractNumber(),
                'start_date'           => $data['paid_at'] ?? now()->toDateString(),
                'subtotal'             => $quote->subtotal,
                'discount_amount'      => $quote->discount_amount ?? 0,
                'iva_amount'           => $quote->iva_amount,
                'retentions_total'     => (($quote->isr_retention_amount ?? 0) + ($quote->iva_retention_amount ?? 0)),
                'total_amount'         => $total,
                'anticipo_amount'      => $anticipo,
                'monthly_amount'       => $monthly,
                'payment_plan_months'  => $months,
                'status'               => 'activo',
                'created_by_user_id'   => $actor->id,
            ]);

            // 1. Registramos el pago inicial (Anticipo) como Pagado
            $this->registerAnticipo($contract, $client, $data);

            // 2. Generamos el cronograma de pagos restantes (Pendientes)
            $this->generateSchedule($contract, $client);

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
            'currency'           => 'MXN',
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
     * Genera cronograma de pagos respetando `payment_plan_months` como TOTAL de cuotas.
     * El anticipo (registrado por separado) cuenta como la cuota n°1.
     * - Si N > 1: se generan N-1 mensualidades adicionales.
     * - Si N == 1 pero queda saldo (anticipo parcial), se agenda 1 cuota única con el saldo.
     * - Si saldo <= 0: no se generan cuotas.
     */
    public function generateSchedule(Contract $contract, Client $client): void
    {
        // Idempotente: si ya hay mensualidades agendadas para este contrato, no duplicamos.
        $hasSchedule = ClientPayment::where('contract_id', $contract->id)
            ->where('type', 'mensualidad')
            ->exists();
        if ($hasSchedule) {
            return;
        }

        $balance = max(0, (float) $contract->total_amount - (float) $contract->anticipo_amount);
        $months  = (int) $contract->payment_plan_months;

        if ($balance <= 0) {
            return;
        }

        // Si plan es a 1 mes pero quedó saldo, lo agendamos como una sola cuota.
        $remaining = max(1, $months - 1);

        $perMonth = round($balance / $remaining, 2);
        $start    = \Illuminate\Support\Carbon::parse($contract->start_date);

        for ($i = 1; $i <= $remaining; $i++) {
            $amount = ($i === $remaining)
                ? round($balance - ($perMonth * ($remaining - 1)), 2)
                : $perMonth;

            ClientPayment::create([
                'contract_id'        => $contract->id,
                'client_id'          => $client->id,
                'type'               => 'mensualidad',
                'status'             => 'programado',
                'amount'             => $amount,
                'currency'           => 'MXN',
                'due_date'           => $start->copy()->addMonths($i)->toDateString(),
                'installment_number' => $i + 1, // anticipo = 1; mensualidades = 2..N
            ]);
        }
    }
}