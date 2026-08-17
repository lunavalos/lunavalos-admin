<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Los roles de producción (Diseño / Web Developer) trabajan sobre tickets y
 * sobre el tablero de recurrentes, pero no deben alcanzar nada comercial: el
 * catálogo de servicios lleva costos y precios de renovación, y la ficha del
 * cliente lleva precios, costos internos y credenciales.
 *
 * Estas pruebas fijan esa frontera a nivel de ruta, que es donde importa:
 * ocultar el menú no sirve de nada si la URL sigue respondiendo 200.
 */
class ProductionRolesAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(DatabaseSeeder::class);
    }

    private function usuarioConRol(string $rol): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($rol);

        return $user;
    }

    public static function rolesDeProduccion(): array
    {
        return [
            'Designer'      => ['Designer'],
            'Web Developer' => ['Web Developer'],
        ];
    }

    #[DataProvider('rolesDeProduccion')]
    public function test_no_alcanzan_los_modulos_con_costos(string $rol): void
    {
        $user = $this->usuarioConRol($rol);

        $rutas = [
            'services.index',
            'service-addons.index',
            'clients.index',
            'quotes.index',
            'contracts.index',
            'contracts.renewals.index',
            'finances.index',
            'payments.index',
            'invoices.index',
            'agencies.index',
        ];

        foreach ($rutas as $ruta) {
            $this->actingAs($user)
                ->get(route($ruta))
                ->assertForbidden();
        }
    }

    #[DataProvider('rolesDeProduccion')]
    public function test_si_alcanzan_tickets_y_recurrentes(string $rol): void
    {
        $user = $this->usuarioConRol($rol);

        $this->actingAs($user)->get(route('tickets.index'))->assertOk();
        $this->actingAs($user)->get(route('recurring.index'))->assertOk();
    }

    #[DataProvider('rolesDeProduccion')]
    public function test_el_menu_no_les_ofrece_clientes_ni_servicios(string $rol): void
    {
        $user = $this->usuarioConRol($rol);

        $response = $this->actingAs($user)->get(route('tickets.index'));

        $can = $response->viewData('page')['props']['auth']['user']['can'];

        $this->assertFalse($can['view_clients']);
        $this->assertFalse($can['view_services']);
        $this->assertFalse($can['view_quotes']);
        $this->assertFalse($can['view_reports']);
    }

    /**
     * El tablero de tickets manda la lista de clientes para el selector: debe
     * traer solo el nombre, nunca los importes de la ficha comercial.
     */
    public function test_el_tablero_de_tickets_no_expone_importes_del_cliente(): void
    {
        $user = $this->usuarioConRol('Designer');

        $cliente = \App\Models\Client::create([
            'business_name' => 'Cliente Demo',
            'contact_name'  => 'Contacto',
            'email'         => 'demo@example.com',
            'initial_price' => 12345.67,
        ]);

        \App\Models\ClientService::create([
            'client_id'      => $cliente->id,
            'service_name'   => 'Diseño mensual',
            'status'         => 'active',
            'billing_type'   => 'monthly',
            'renewal_amount' => 8900.00,
        ]);

        $response = $this->actingAs($user)->get(route('tickets.index'));

        $clientes = $response->viewData('page')['props']['clients'];

        $this->assertArrayNotHasKey('initial_price', $clientes[0]);
        $this->assertArrayNotHasKey('login_credentials', $clientes[0]);
        $this->assertArrayNotHasKey('vault_credentials', $clientes[0]);
        $this->assertArrayNotHasKey('renewal_amount', $clientes[0]['services'][0]);
        $this->assertSame('Diseño mensual', $clientes[0]['services'][0]['service_name']);
    }
}
