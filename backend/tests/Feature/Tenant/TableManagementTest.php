<?php

namespace Tests\Feature\Tenant;

use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Permission;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TableManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafeA;
    protected Cafe $cafeB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected User $managerA;
    protected User $waiterA;
    protected Role $managerRoleA;
    protected Role $waiterRoleA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->cafeA = Cafe::create([
            'name' => 'Table Cafe A',
            'slug' => 'table-cafe-a',
            'status' => 'active',
        ]);

        $this->cafeB = Cafe::create([
            'name' => 'Table Cafe B',
            'slug' => 'table-cafe-b',
            'status' => 'active',
        ]);

        $this->branchA = Branch::create([
            'cafe_id' => $this->cafeA->id,
            'name' => 'Main Branch A',
            'slug' => 'main-a',
            'status' => 'active',
        ]);

        $this->branchB = Branch::create([
            'cafe_id' => $this->cafeB->id,
            'name' => 'Main Branch B',
            'slug' => 'main-b',
            'status' => 'active',
        ]);

        $this->managerRoleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name' => 'Manager A',
            'slug' => 'manager',
            'scope' => 'tenant',
        ]);

        $this->waiterRoleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name' => 'Waiter A',
            'slug' => 'waiter',
            'scope' => 'tenant',
        ]);

        $tablePerms = ['table.view', 'table.create', 'table.update', 'table.delete'];
        foreach ($tablePerms as $permSlug) {
            $permission = Permission::firstOrCreate(['slug' => $permSlug], ['name' => $permSlug]);
            $this->managerRoleA->permissions()->attach($permission->id);
        }

        $this->managerA = User::create([
            'name' => 'Manager A',
            'email' => 'managerA@tabletest.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->waiterA = User::create([
            'name' => 'Waiter A',
            'email' => 'waiterA@tabletest.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        CafeUser::create([
            'cafe_id' => $this->cafeA->id,
            'user_id' => $this->managerA->id,
            'role_id' => $this->managerRoleA->id,
            'status' => 'active',
        ]);

        CafeUser::create([
            'cafe_id' => $this->cafeA->id,
            'user_id' => $this->waiterA->id,
            'role_id' => $this->waiterRoleA->id,
            'status' => 'active',
        ]);
    }

    public function test_table_creation_and_automatic_qr_token_generation(): void
    {
        $this->actingAs($this->managerA);

        $response = $this->postJson('/cafes/table-cafe-a/tables', [
            'branch_id' => $this->branchA->id,
            'name' => 'Table 101',
            'capacity' => 4,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Table created successfully.',
                'table' => [
                    'name' => 'Table 101',
                    'capacity' => 4,
                    'status' => 'available',
                ],
            ]);

        $qrToken = $response->json('table.qr_token');
        $this->assertNotEmpty($qrToken);

        $this->assertDatabaseHas('restaurant_tables', [
            'branch_id' => $this->branchA->id,
            'name' => 'Table 101',
            'qr_token' => $qrToken,
        ]);
    }

    public function test_qr_token_regeneration_invalidates_previous_token(): void
    {
        $this->actingAs($this->managerA);

        $table = RestaurantTable::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Table 102',
            'capacity' => 2,
            'qr_token' => 'old-token-12345',
        ]);

        $response = $this->postJson("/cafes/table-cafe-a/tables/{$table->id}/regenerate-qr");
        $response->assertStatus(200);

        $newToken = $response->json('qr_token');
        $this->assertNotEmpty($newToken);
        $this->assertNotEquals('old-token-12345', $newToken);

        $this->assertEquals($newToken, $table->fresh()->qr_token);
    }

    public function test_table_branch_tenant_isolation(): void
    {
        $this->actingAs($this->managerA);

        // Attempting to create table for Cafe B's branch should fail validation
        $response = $this->postJson('/cafes/table-cafe-a/tables', [
            'branch_id' => $this->branchB->id,
            'name' => 'Hacked Table',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_cross_tenant_table_update_and_delete_denied(): void
    {
        $tableB = RestaurantTable::create([
            'branch_id' => $this->branchB->id,
            'name' => 'Cafe B Table',
            'qr_token' => 'token-b-999',
        ]);

        $this->actingAs($this->managerA);

        $updateResponse = $this->putJson("/cafes/table-cafe-a/tables/{$tableB->id}", [
            'name' => 'Hacked Name',
        ]);
        $updateResponse->assertStatus(404);

        $deleteResponse = $this->deleteJson("/cafes/table-cafe-a/tables/{$tableB->id}");
        $deleteResponse->assertStatus(404);
    }

    public function test_table_authorization_denied_for_unprivileged_user(): void
    {
        $this->actingAs($this->waiterA);

        $response = $this->postJson('/cafes/table-cafe-a/tables', [
            'branch_id' => $this->branchA->id,
            'name' => 'Unauthorized Table',
        ]);

        $response->assertStatus(403);
    }
}
