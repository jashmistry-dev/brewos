<?php

namespace Tests\Feature\Authorization;

use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafe;
    protected User $managerUser;
    protected User $waiterUser;
    protected User $superAdminUser;
    protected Role $managerRole;
    protected Role $waiterRole;
    protected Role $superAdminRole;
    protected Permission $staffViewPermission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cafe = Cafe::create([
            'name' => 'Cafe Gamma',
            'slug' => 'cafe-gamma',
            'status' => 'active',
        ]);

        $this->staffViewPermission = Permission::create([
            'name' => 'View Staff',
            'slug' => 'staff.view',
        ]);

        $this->managerRole = Role::create([
            'cafe_id' => $this->cafe->id,
            'name' => 'Manager',
            'slug' => 'manager',
            'scope' => 'tenant',
        ]);

        $this->managerRole->permissions()->attach($this->staffViewPermission->id);

        $this->waiterRole = Role::create([
            'cafe_id' => $this->cafe->id,
            'name' => 'Waiter',
            'slug' => 'waiter',
            'scope' => 'tenant',
        ]);

        $this->superAdminRole = Role::create([
            'cafe_id' => null,
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'scope' => 'platform',
        ]);

        $this->managerUser = User::create([
            'name' => 'Manager User',
            'email' => 'manager@gamma.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->waiterUser = User::create([
            'name' => 'Waiter User',
            'email' => 'waiter@gamma.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->superAdminUser = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@brewos.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        CafeUser::create([
            'cafe_id' => $this->cafe->id,
            'user_id' => $this->managerUser->id,
            'role_id' => $this->managerRole->id,
            'status' => 'active',
        ]);

        CafeUser::create([
            'cafe_id' => $this->cafe->id,
            'user_id' => $this->waiterUser->id,
            'role_id' => $this->waiterRole->id,
            'status' => 'active',
        ]);

        CafeUser::create([
            'cafe_id' => $this->cafe->id,
            'user_id' => $this->superAdminUser->id,
            'role_id' => $this->superAdminRole->id,
            'status' => 'active',
        ]);
    }

    public function test_user_with_permission_can_access_authorized_action(): void
    {
        $this->actingAs($this->managerUser);

        $response = $this->getJson('/cafes/cafe-gamma/staff');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Staff list loaded.',
                'cafe_id' => $this->cafe->id,
            ]);
    }

    public function test_user_without_permission_is_denied(): void
    {
        $this->actingAs($this->waiterUser);

        $response = $this->getJson('/cafes/cafe-gamma/staff');

        $response->assertStatus(403);
    }

    public function test_super_admin_cannot_access_tenant_api_without_membership(): void
    {
        $otherCafe = Cafe::create([
            'name' => 'Cafe Delta',
            'slug' => 'cafe-delta',
            'status' => 'active',
        ]);

        $this->actingAs($this->superAdminUser);

        $response = $this->getJson('/cafes/cafe-delta/dashboard');

        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_platform_admin_routes(): void
    {
        $this->actingAs($this->superAdminUser);

        $response = $this->getJson('/admin/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Super Admin platform dashboard loaded.',
            ]);
    }
}
