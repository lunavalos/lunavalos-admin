<?php

namespace Tests\Feature;

use App\Actions\Quotes\ConvertQuoteToContract;
use App\Models\ClientPayment;
use App\Models\Quote;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Semántica de `billing_type = annual`:
 *
 *   - `price` (→ QuoteItem.unit_price) es el PAGO INICIAL ÚNICO por el
 *     desarrollo del proyecto, financiable en `payment_plan_months` cuotas.
 *   - `renewal_price` (→ QuoteItem.unit_renewal_price) es la ANUALIDAD que el
 *     cliente paga cada año por los servicios (dominio, hosting, soporte),
 *     a partir del año 2.
 */
class AnnualBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['Crear Cotizaciones', 'Ver Cotizaciones', 'Editar Cotizaciones'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    private function admin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo(['Crear Cotizaciones', 'Ver Cotizaciones', 'Editar Cotizaciones']);

        return $user;
    }

    private function annualPackage(int $planMonths = 6): Service
    {
        return Service::create([
            'name'                => 'Sitio Web Corporativo',
            'description'         => 'Desarrollo del sitio + servicios anuales',
            'price'               => 30000,   // pago inicial único (desarrollo)
            'renewal_price'       => 6000,    // anualidad (año 2 en adelante)
            'billing_type'        => 'annual',
            'is_package'          => true,
            'payment_plan_months' => $planMonths,
        ]);
    }

    /** Iguala mensual que además trae una anualidad (dominio/hosting). */
    private function monthlyPackageWithRenewal(): Service
    {
        return Service::create([
            'name'                => 'Iguala de Marketing',
            'description'         => 'Gestión mensual de campañas',
            'price'               => 4500,    // se cobra CADA MES
            'renewal_price'       => 3200,    // anualidad, aparte de la iguala
            'billing_type'        => 'monthly',
            'is_package'          => true,
            'payment_plan_months' => 6,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'package_service_ids'         => [],
            'package_payment_plan_months' => 6,
            'client_name'                 => 'ACME SA de CV',
            'contact_name'                => 'Contacto',
            'email'                       => 'contacto@acme.test',
            'phone'                       => '5555555555',
            'issue_date'                  => '2026-08-12',
            'valid_until'                 => '2026-08-27',
            'currency'                    => 'MXN',
            'discount_amount'             => 0,
            'applies_iva'                 => false,
            'status'                      => 'Borrador',
            'addons'                      => [],
        ], $overrides);
    }

    private function quoteFor(Service $service, int $planMonths, User $admin): Quote
    {
        $this->actingAs($admin)
            ->post(route('quotes.wizard.store'), $this->payload([
                'package_service_ids'         => [$service->id],
                'package_payment_plan_months' => $planMonths,
            ]))
            ->assertSessionHasNoErrors();

        return Quote::with(['items', 'addons', 'package'])->latest('id')->first();
    }

    /** El precio base NO se suma con la anualidad: el total de hoy es sólo el desarrollo. */
    public function test_annual_quote_totals_only_include_the_upfront_price(): void
    {
        $svc   = $this->annualPackage();
        $quote = $this->quoteFor($svc, 6, $this->admin());

        $this->assertEquals(30000.00, (float) $quote->subtotal);
        $this->assertEquals(30000.00, (float) $quote->total);

        $item = $quote->items->first();
        $this->assertSame('annual', $item->billing_type);
        $this->assertEquals(30000.00, (float) $item->unit_price);
        $this->assertEquals(6000.00, (float) $item->unit_renewal_price);
    }

    /** El precio base se difiere en cuotas MENSUALES según el plan de pago. */
    public function test_upfront_price_is_split_into_monthly_installments(): void
    {
        $admin = $this->admin();
        $quote = $this->quoteFor($this->annualPackage(), 6, $admin);

        $contract = (new ConvertQuoteToContract)($quote, [
            'anticipo_amount'   => 5000,
            'payment_method'    => 'transferencia',
            'payment_reference' => 'TEST-ANN-1',
            'paid_at'           => '2026-08-12',
        ], $admin);

        $installments = ClientPayment::where('contract_id', $contract->id)
            ->where('type', 'mensualidad')
            ->whereNull('concept')
            ->orderBy('installment_number')
            ->get();

        // Anticipo = mes 1; saldo 25,000 repartido en 5 mensualidades.
        $this->assertCount(5, $installments);
        $this->assertEquals(5000.00, (float) $installments->first()->amount);
        $this->assertEquals(25000.00, round($installments->sum('amount'), 2));

        // Las cuotas son MENSUALES (mes 2..6), no anuales.
        $this->assertSame('2026-09-12', $installments->first()->due_date->toDateString());
    }

    /** La anualidad se agenda con el renewal_price, empezando el año 2. */
    public function test_renewals_use_renewal_price_starting_year_two(): void
    {
        $admin = $this->admin();
        $quote = $this->quoteFor($this->annualPackage(), 6, $admin);

        $contract = (new ConvertQuoteToContract)($quote, [
            'anticipo_amount'   => 5000,
            'payment_method'    => 'transferencia',
            'payment_reference' => 'TEST-ANN-2',
            'paid_at'           => '2026-08-12',
        ], $admin);

        $renewals = ClientPayment::where('contract_id', $contract->id)
            ->whereNotNull('concept')
            ->orderBy('due_date')
            ->get();

        $this->assertTrue($renewals->isNotEmpty(), 'Debe agendar renovaciones anuales.');
        $this->assertEquals(6000.00, (float) $renewals->first()->amount);
        $this->assertSame('2027-08-12', $renewals->first()->due_date->toDateString());
    }

    /** El PDF separa el pago inicial de la anualidad y lo explica. */
    public function test_pdf_explains_upfront_price_and_annual_renewal(): void
    {
        $quote = $this->quoteFor($this->annualPackage(), 6, $this->admin());
        $quote->load(['items.service.features', 'addons.serviceAddon']);

        $html = view('pdf.quote', compact('quote'))->render();

        // La partida se presenta como pago inicial + anualidad, no como "ANUAL".
        $this->assertStringContainsString('PAGO INICIAL ÚNICO', $html);
        $this->assertStringContainsString('+ ANUALIDAD', $html);

        // Totales separados: el desarrollo es la inversión inicial…
        $this->assertStringContainsString('INVERSI&Oacute;N INICIAL', $html);
        $this->assertStringContainsString('desarrollo del proyecto', $html);

        // …y la anualidad va aparte, fuera del total de hoy.
        $this->assertStringContainsString('RENOVACI&Oacute;N ANUAL', $html);
        $this->assertStringContainsString('a partir del a&ntilde;o 2', $html);
        $this->assertStringContainsString('C&oacute;mo funciona tu inversi&oacute;n', $html);

        // El total a pagar es sólo el desarrollo (30,000), sin la anualidad.
        $this->assertStringContainsString('30,000.00', $html);
        $this->assertStringContainsString('6,000.00', $html);
        $this->assertStringNotContainsString('36,000.00', $html);
    }

    /**
     * Mensualidad/iguala: el Precio Base se cobra cada mes y la anualidad debe
     * aparecer igual en el PDF, como cobro aparte una vez al año.
     */
    public function test_monthly_quote_shows_its_annual_renewal_in_the_pdf(): void
    {
        $quote = $this->quoteFor($this->monthlyPackageWithRenewal(), 6, $this->admin());
        $quote->load(['items.service.features', 'addons.serviceAddon']);

        // El total de hoy es sólo la iguala: la anualidad no se suma.
        $this->assertEquals(4500.00, (float) $quote->total);

        $html = view('pdf.quote', compact('quote'))->render();

        $this->assertStringContainsString('MENSUAL', $html);
        $this->assertStringContainsString('+ ANUALIDAD', $html);
        $this->assertStringContainsString('al mes', $html);

        // Iguala mensual y renovación anual, cada una en su renglón.
        $this->assertStringContainsString('IGUALA MENSUAL', $html);
        $this->assertStringContainsString('RENOVACI&Oacute;N ANUAL', $html);
        $this->assertStringContainsString('3,200.00', $html);
        $this->assertStringContainsString('Iguala mensual de', $html);
        $this->assertStringContainsString('aparte de la iguala mensual', $html);

        // Una iguala pura no tiene "saldo restante" que diferir.
        $this->assertStringContainsString('Condiciones de Pago (Iguala mensual)', $html);
        $this->assertStringNotContainsString('saldo restante', $html);
    }

    /** La anualidad de una iguala mensual también se agenda desde el año 2. */
    public function test_monthly_package_renewal_is_scheduled_yearly(): void
    {
        $admin = $this->admin();
        $quote = $this->quoteFor($this->monthlyPackageWithRenewal(), 6, $admin);

        $contract = (new ConvertQuoteToContract)($quote, [
            'anticipo_amount'   => 4500,
            'payment_method'    => 'transferencia',
            'payment_reference' => 'TEST-MON-1',
            'paid_at'           => '2026-08-12',
        ], $admin);

        $renewals = ClientPayment::where('contract_id', $contract->id)
            ->whereNotNull('concept')
            ->orderBy('due_date')
            ->get();

        $this->assertTrue($renewals->isNotEmpty());
        $this->assertEquals(3200.00, (float) $renewals->first()->amount);
        $this->assertSame('2027-08-12', $renewals->first()->due_date->toDateString());
    }

    /** El contrato imprime la cuota real del plan y explica la anualidad. */
    public function test_contract_pdf_states_installment_and_annual_renewal(): void
    {
        $admin = $this->admin();
        $quote = $this->quoteFor($this->annualPackage(), 6, $admin);

        $contract = (new ConvertQuoteToContract)($quote, [
            'anticipo_amount'   => 5000,
            'payment_method'    => 'transferencia',
            'payment_reference' => 'TEST-ANN-4',
            'paid_at'           => '2026-08-12',
        ], $admin);

        $contract->load('quote.items', 'client');

        $html = view('contracts.pdf', ['contract' => $contract, 'settings' => []])->render();

        // Cuota del plan = saldo / 5, no monthly_amount (que es la referencia MRR).
        $this->assertStringContainsString('5 mensualidades', $html);
        $this->assertStringContainsString('5,000.00', $html);

        // La anualidad se explica como pago aparte desde el año 2.
        $this->assertStringContainsString('Renovación anual:', $html);
        $this->assertStringContainsString('6,000.00', $html);
        $this->assertStringContainsString('a partir del segundo año', $html);

        // El MRR de referencia del contrato es la anualidad / 12, no el desarrollo.
        $this->assertEquals(500.00, (float) $contract->monthly_amount);
    }

    /**
     * Un plan de pago largo (24 meses) financia el PAGO INICIAL, no compra
     * años de servicio: la anualidad del año 2 se debe cobrar igual.
     */
    public function test_long_payment_plan_does_not_skip_annual_renewals(): void
    {
        $admin = $this->admin();
        $quote = $this->quoteFor($this->annualPackage(24), 24, $admin);

        $contract = (new ConvertQuoteToContract)($quote, [
            'anticipo_amount'   => 1250,
            'payment_method'    => 'transferencia',
            'payment_reference' => 'TEST-ANN-3',
            'paid_at'           => '2026-08-12',
        ], $admin);

        $renewals = ClientPayment::where('contract_id', $contract->id)
            ->whereNotNull('concept')
            ->orderBy('due_date')
            ->get();

        $this->assertSame('2027-08-12', $renewals->first()?->due_date?->toDateString());
        $this->assertEquals(6000.00, (float) $renewals->first()->amount);
    }
}
