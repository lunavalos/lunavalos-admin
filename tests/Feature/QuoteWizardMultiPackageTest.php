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

class QuoteWizardMultiPackageTest extends TestCase
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

    /** Sitio web de pago único con renovación anual. */
    private function websitePackage(): Service
    {
        return Service::create([
            'name'                => 'Sitio Web Corporativo',
            'description'         => 'Sitio institucional',
            'price'               => 30000,
            'renewal_price'       => 6000,
            'billing_type'        => 'unique',
            'is_package'          => true,
            'payment_plan_months' => 3,
        ]);
    }

    /** Sistema a medida con mensualidad recurrente. */
    private function systemPackage(): Service
    {
        return Service::create([
            'name'                => 'Sistema Administrativo',
            'description'         => 'Sistema a medida',
            'price'               => 4500,
            'renewal_price'       => 0,
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

    public function test_wizard_stores_one_item_per_selected_package(): void
    {
        $web    = $this->websitePackage();
        $system = $this->systemPackage();

        $this->actingAs($this->admin())
            ->post(route('quotes.wizard.store'), $this->payload([
                'package_service_ids' => [$web->id, $system->id],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $quote = Quote::with('items')->latest('id')->first();

        // El primero de la lista queda como paquete principal.
        $this->assertSame($web->id, $quote->package_service_id);

        $this->assertCount(2, $quote->items);
        $this->assertEqualsCanonicalizing(
            [$web->id, $system->id],
            $quote->items->pluck('service_id')->all()
        );

        // Subtotal suma ambos paquetes, no sólo el principal.
        $this->assertEquals(34500.00, (float) $quote->subtotal);

        // Snapshot de renovación por línea.
        $webItem = $quote->items->firstWhere('service_id', $web->id);
        $this->assertEquals(6000.00, (float) $webItem->unit_renewal_price);
        $this->assertSame('unique', $webItem->billing_type);

        $systemItem = $quote->items->firstWhere('service_id', $system->id);
        $this->assertSame('monthly', $systemItem->billing_type);
    }

    public function test_wizard_accepts_legacy_single_package_payload(): void
    {
        $web = $this->websitePackage();

        $payload = $this->payload();
        unset($payload['package_service_ids']);
        $payload['package_service_id'] = $web->id;

        $this->actingAs($this->admin())
            ->post(route('quotes.wizard.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $quote = Quote::with('items')->latest('id')->first();

        $this->assertSame($web->id, $quote->package_service_id);
        $this->assertCount(1, $quote->items);
        $this->assertEquals(30000.00, (float) $quote->subtotal);
    }

    public function test_wizard_rejects_empty_package_selection(): void
    {
        $this->actingAs($this->admin())
            ->post(route('quotes.wizard.store'), $this->payload())
            ->assertSessionHasErrors('package_service_ids');

        $this->assertSame(0, Quote::count());
    }

    public function test_update_replaces_the_full_package_selection(): void
    {
        $web    = $this->websitePackage();
        $system = $this->systemPackage();
        $admin  = $this->admin();

        $this->actingAs($admin)->post(route('quotes.wizard.store'), $this->payload([
            'package_service_ids' => [$web->id, $system->id],
        ]));

        $quote = Quote::latest('id')->first();

        $this->actingAs($admin)
            ->put(route('quotes.wizard.update', $quote), $this->payload([
                'package_service_ids' => [$system->id],
            ]))
            ->assertSessionHasNoErrors();

        $quote->refresh()->load('items');

        $this->assertSame($system->id, $quote->package_service_id);
        $this->assertCount(1, $quote->items);
        $this->assertEquals(4500.00, (float) $quote->subtotal);
    }

    public function test_mixed_quote_schedules_recurring_plus_split_upfront(): void
    {
        $web    = $this->websitePackage();
        $system = $this->systemPackage();
        $admin  = $this->admin();

        $this->actingAs($admin)->post(route('quotes.wizard.store'), $this->payload([
            'package_service_ids'         => [$web->id, $system->id],
            'package_payment_plan_months' => 6,
        ]));

        $quote = Quote::with('items')->latest('id')->first();

        $contract = (new ConvertQuoteToContract)($quote, [
            'anticipo_amount'   => 10000,
            'payment_method'    => 'transferencia',
            'payment_reference' => 'TEST-001',
            'paid_at'           => '2026-08-12',
        ], $admin);

        // El componente mensual manda aunque el paquete principal sea de pago
        // único: se generan 5 cuotas (meses 2..6), no un solo saldo.
        $installments = ClientPayment::where('contract_id', $contract->id)
            ->where('type', 'mensualidad')
            ->whereNull('concept')
            ->orderBy('installment_number')
            ->get();

        $this->assertCount(5, $installments);

        // Cada cuota = mensualidad del sistema (4,500) + reparto del remanente
        // único del sitio (30,000 - excedente del anticipo) entre las 5 cuotas.
        $expected = 4500 + round((30000 - (10000 - 4500)) / 5, 2);
        $this->assertEquals($expected, (float) $installments->first()->amount);

        // La suma de las cuotas cubre exactamente el remanente único + mensualidades.
        $this->assertEquals(
            round((30000 - (10000 - 4500)) + (4500 * 5), 2),
            round($installments->sum('amount'), 2)
        );

        // La renovación anual del sitio se agenda a futuro.
        $renewals = ClientPayment::where('contract_id', $contract->id)
            ->whereNotNull('concept')
            ->get();

        $this->assertTrue($renewals->isNotEmpty());
        $this->assertEquals(6000.00, (float) $renewals->first()->amount);
    }
}
