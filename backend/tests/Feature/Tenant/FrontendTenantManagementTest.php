<?php

namespace Tests\Feature\Tenant;

use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FrontendTenantManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafe;
    protected User $owner;
    protected User $unauthorizedUser;
    protected Role $ownerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cafe = Cafe::create([
            'name' => 'BrewOS Flagship',
            'slug' => 'brewos-flagship',
            'email' => 'contact@brewos.com',
            'phone' => '1234567890',
            'status' => 'active',
        ]);

        $this->owner = User::create([
            'name' => 'Cafe Owner',
            'email' => 'owner@brewos.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->unauthorizedUser = User::create([
            'name' => 'Regular Staff',
            'email' => 'staff@brewos.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->ownerRole = Role::create([
            'cafe_id' => $this->cafe->id,
            'name' => 'Cafe Owner',
            'slug' => 'cafe-owner',
            'scope' => 'tenant',
        ]);

        $permissions = [
            'cafe.view',
            'cafe.settings.update',
            'branch.view',
            'branch.create',
            'branch.update',
            'staff.view',
            'staff.create',
            'staff.update',
            'staff.delete',
        ];

        foreach ($permissions as $permSlug) {
            $perm = Permission::firstOrCreate(['slug' => $permSlug], ['name' => ucfirst(str_replace('.', ' ', $permSlug))]);
            $this->ownerRole->permissions()->syncWithoutDetaching([$perm->id]);
        }

        CafeUser::create([
            'cafe_id' => $this->cafe->id,
            'user_id' => $this->owner->id,
            'role_id' => $this->ownerRole->id,
            'status' => 'active',
        ]);

        Branch::create([
            'cafe_id' => $this->cafe->id,
            'name' => 'HQ Branch',
            'slug' => 'hq-branch',
            'status' => 'active',
        ]);
    }

    public function test_owner_can_render_inertia_dashboard_page(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get('/cafes/brewos-flagship/dashboard');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Dashboard')
                ->where('cafe.slug', 'brewos-flagship')
                ->has('branchCount')
                ->has('staffCount')
            );
    }

    public function test_owner_can_render_inertia_settings_page(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get('/cafes/brewos-flagship/settings');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Settings')
                ->where('cafe.slug', 'brewos-flagship')
                ->where('cafe.name', 'BrewOS Flagship')
            );
    }

    public function test_owner_can_render_inertia_branches_page(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get('/cafes/brewos-flagship/branches');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Branches')
                ->has('branches', 1)
                ->where('branches.0.slug', 'hq-branch')
            );
    }

    public function test_owner_can_render_inertia_staff_page(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get('/cafes/brewos-flagship/staff');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Staff')
                ->has('staff')
                ->has('roles')
                ->has('branches')
            );
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/cafes/brewos-flagship/dashboard');

        $response->assertRedirect('/login');
    }
}
