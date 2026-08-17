<?php

namespace Tests\Feature\Tenant;

use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Role;
use App\Models\User;
use App\Services\DefaultTenantRolesService;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CafeOwnerPermissionsAndRoleTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;
    protected User $cafeOwner;
    protected Cafe $cafe;
    protected Plan $testPlan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        // Seed Super Admin
        $this->seed(SuperAdminSeeder::class);
        $this->superAdmin = User::where('email', 'admin@brewos.local')->first();

        // Create Cafe
        $this->cafe = Cafe::create([
            'name'   => 'Role Test Cafe',
            'slug'   => 'role-test-cafe',
            'email'  => 'owner@roletest.com',
            'status' => 'active',
        ]);

        $branch = Branch::create([
            'cafe_id' => $this->cafe->id,
            'name'    => 'Main Branch',
            'slug'    => 'main',
            'status'  => 'active',
        ]);

        $rolesService = app(DefaultTenantRolesService::class);
        $roles = $rolesService->createDefaultRolesForCafe($this->cafe);
        $ownerRole = $roles['cafe-owner'];

        $this->cafeOwner = User::create([
            'name'     => 'Cafe Owner User',
            'email'    => 'owner@roletest.com',
            'password' => Hash::make('password'),
            'status'   => 'active',
        ]);

        CafeUser::create([
            'cafe_id'   => $this->cafe->id,
            'user_id'   => $this->cafeOwner->id,
            'role_id'   => $ownerRole->id,
            'branch_id' => $branch->id,
            'status'    => 'active',
        ]);

        $this->testPlan = Plan::create([
            'name'             => 'Test Starter Plan',
            'slug'             => 'test-starter-plan',
            'price'            => 29.99,
            'billing_interval' => 'monthly',
            'status'           => 'active',
        ]);

        PlanFeature::create([
            'plan_id'     => $this->testPlan->id,
            'feature_key' => 'menu_item_limit',
            'value'       => '5',
        ]);
        PlanFeature::create([
            'plan_id'     => $this->testPlan->id,
            'feature_key' => 'staff_limit',
            'value'       => '3',
        ]);
        PlanFeature::create([
            'plan_id'     => $this->testPlan->id,
            'feature_key' => 'branch_limit',
            'value'       => '2',
        ]);
    }

    public function test_cafe_owner_role_resolution_and_inertia_shared_props(): void
    {
        $response = $this->actingAs($this->cafeOwner)
            ->get("/cafes/{$this->cafe->slug}/dashboard");

        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Dashboard')
            ->where('auth.roles', ['cafe-owner'])
            ->where('auth.user.email', 'owner@roletest.com')
            ->whereContains('auth.permissions', 'menu.create')
            ->whereContains('auth.permissions', 'staff.create')
            ->whereContains('auth.permissions', 'branch.create')
            ->whereContains('auth.permissions', 'table.create')
        );
    }

    public function test_staff_management_lists_cafe_owner_as_member(): void
    {
        $response = $this->actingAs($this->cafeOwner)
            ->get("/cafes/{$this->cafe->slug}/staff");

        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Staff')
            ->has('staff', 1)
            ->where('staff.0.email', 'owner@roletest.com')
            ->where('staff.0.role.name', 'Cafe Owner')
        );
    }

    public function test_super_admin_assigns_plan_and_cafe_owner_can_create_resources_under_limit(): void
    {
        // Super Admin assigns plan
        $assignResponse = $this->actingAs($this->superAdmin)
            ->post("/admin/cafes/{$this->cafe->id}/subscription/change-plan", [
                'plan_id' => $this->testPlan->id,
                'reason'  => 'Assigning test plan',
            ]);
        $assignResponse->assertRedirect();

        // Cafe Owner creates Category and Menu Item
        $category = Category::create([
            'cafe_id'    => $this->cafe->id,
            'name'       => 'Main Menu',
            'slug'       => 'main-menu',
            'sort_order' => 1,
        ]);

        $itemResponse = $this->actingAs($this->cafeOwner)
            ->post("/cafes/{$this->cafe->slug}/menu-items", [
                'category_id' => $category->id,
                'name'        => 'Special Coffee',
                'price'       => '4.99',
                'status'      => 'active',
            ]);

        $itemResponse->assertRedirect();
        $this->assertEquals(1, MenuItem::where('cafe_id', $this->cafe->id)->count());
    }
}
