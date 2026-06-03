<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RecurringAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Forget Spatie cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles and permissions
        $clientRole = Role::firstOrCreate(['name' => config('roles.client', 'Cliente'), 'guard_name' => 'web']);
        $viewPermission = Permission::firstOrCreate(['name' => 'Ver Recurrentes', 'guard_name' => 'web']);
        $managePermission = Permission::firstOrCreate(['name' => 'Gestionar Recurrentes', 'guard_name' => 'web']);

        $clientRole->syncPermissions([$viewPermission]);
    }

    public function test_non_client_users_with_permission_can_access_recurring_index()
    {
        $admin = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $admin->givePermissionTo('Ver Recurrentes');

        $response = $this->actingAs($admin)->get(route('recurring.index'));

        $response->assertStatus(200);
    }

    public function test_client_users_without_permission_are_forbidden()
    {
        $clientUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        
        $response = $this->actingAs($clientUser)->get(route('recurring.index'));

        $response->assertStatus(403);
    }

    public function test_client_users_with_permission_are_redirected_to_their_own_client_details()
    {
        $client = Client::create([
            'business_name' => 'Client 1',
            'contact_name' => 'Contact 1',
            'email' => 'client1@example.com',
        ]);
        
        $clientUser = User::factory()->create([
            'client_id' => $client->id,
            'email_verified_at' => now(),
        ]);
        $clientUser->assignRole(config('roles.client', 'Cliente'));

        $response = $this->actingAs($clientUser)->get(route('recurring.index'));

        $response->assertRedirect(route('recurring.clients.show', $client->id));
    }

    public function test_client_users_cannot_access_other_clients_recurring_details()
    {
        $client1 = Client::create([
            'business_name' => 'Client 1',
            'contact_name' => 'Contact 1',
            'email' => 'client1@example.com',
        ]);
        $client2 = Client::create([
            'business_name' => 'Client 2',
            'contact_name' => 'Contact 2',
            'email' => 'client2@example.com',
        ]);

        $clientUser = User::factory()->create([
            'client_id' => $client1->id,
            'email_verified_at' => now(),
        ]);
        $clientUser->assignRole(config('roles.client', 'Cliente'));

        // Attempting to see client2 details
        $response = $this->actingAs($clientUser)->get(route('recurring.clients.show', $client2->id));

        $response->assertStatus(403);
    }

    public function test_client_users_can_access_their_own_client_recurring_details()
    {
        $client = Client::create([
            'business_name' => 'Client 1',
            'contact_name' => 'Contact 1',
            'email' => 'client1@example.com',
        ]);
        
        $clientUser = User::factory()->create([
            'client_id' => $client->id,
            'email_verified_at' => now(),
        ]);
        $clientUser->assignRole(config('roles.client', 'Cliente'));

        $quote = \App\Models\Quote::create([
            'client_id' => $client->id,
            'client_name' => 'Client 1',
            'contact_name' => 'Contact 1',
            'email' => 'client1@example.com',
            'status' => 'Aceptada',
            'issue_date' => now(),
        ]);

        $contract = Contract::create([
            'client_id' => $client->id,
            'quote_id' => $quote->id,
            'status' => 'signed',
            'contract_number' => 'CON-123',
            'token' => 'dummy-token',
        ]);

        ContractService::create([
            'contract_id' => $contract->id,
            'name' => 'Design',
            'prefix' => 'DES',
            'quantity_per_cycle' => 5,
            'unit_type' => 'fixed',
        ]);

        $response = $this->actingAs($clientUser)->get(route('recurring.clients.show', $client->id));

        $response->assertStatus(200);
    }
}
