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
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminPlanAssignmentAndEntitlementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;
    protected User $cafeOwner;
    protected Cafe $cafe;
    protected Plan $starterPlan;
    protected Plan $proPlan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        // Seed Super Admin
        $this->seed(SuperAdminSeeder::class);
        $this->superAdmin = User::where('email', 'admin@brewos.local')->first();

        // Create regular Cafe + Branch + Owner
        $this->cafe = Cafe::create([
            'name'   => 'Entitlement Test Cafe',
            'slug'   => 'entitlement-test-cafe',
            'email'  => 'owner@entitlement.test',
            'status' => 'active',
        ]);

        $branch = Branch::create([
            'cafe_id' => $this->cafe->id,
            'name'    => 'Main Branch',
            'slug'    => 'main',
            'status'  => 'active',
        ]);

        $rolesService = app(\App\Services\DefaultTenantRolesService::class);
        $roles = $rolesService->createDefaultRolesForCafe($this->cafe);
        $ownerRole = $roles['cafe-owner'];

        $this->cafeOwner = User::create([
            'name'     => 'Owner User',
            'email'    => 'owner@entitlement.test',
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

        // Create Starter Plan (limit: 2 menu items)
        $this->starterPlan = Plan::create([
            'name'             => 'Starter Test Plan',
            'slug'             => 'starter-test-plan',
            'price'            => 19.99,
            'billing_interval' => 'monthly',
            'status'           => 'active',
        ]);

        PlanFeature::create([
            'plan_id'     => $this->starterPlan->id,
            'feature_key' => 'menu_item_limit',
            'value'       => '2',
        ]);

        // Create Pro Plan (unlimited menu items)
        $this->proPlan = Plan::create([
            'name'             => 'Pro Test Plan',
            'slug'             => 'pro-test-plan',
            'price'            => 49.99,
            'billing_interval' => 'monthly',
            'status'           => 'active',
        ]);

        PlanFeature::create([
            'plan_id'     => $this->proPlan->id,
            'feature_key' => 'menu_item_limit',
            'value'       => 'unlimited',
        ]);
    }

    public function test_super_admin_plan_assignment_immediately_updates_owner_entitlements_and_menu_item_limits(): void
    {
        // Step 1: Super Admin assigns Starter Plan to Cafe
        $assignResponse = $this->actingAs($this->superAdmin)
            ->post("/admin/cafes/{$this->cafe->id}/subscription/change-plan", [
                'plan_id' => $this->starterPlan->id,
                'reason'  => 'Assigned starter plan for testing',
            ]);

        $assignResponse->assertRedirect();

        // Step 2: Cafe Owner logs in and checks subscription overview
        $subResponse = $this->actingAs($this->cafeOwner)
            ->get("/cafes/{$this->cafe->slug}/subscription");

        $subResponse->assertStatus(200);

        // Create a Category for Menu Items
        $category = Category::create([
            'cafe_id'    => $this->cafe->id,
            'name'       => 'Coffee',
            'slug'       => 'coffee',
            'sort_order' => 1,
        ]);

        // Step 3: Cafe Owner creates menu items up to Starter limit (2)
        $item1Res = $this->actingAs($this->cafeOwner)
            ->post("/cafes/{$this->cafe->slug}/menu-items", [
                'category_id' => $category->id,
                'name'        => 'Espresso',
                'price'       => '3.50',
                'status'      => 'active',
            ]);
        $item1Res->assertRedirect();

        $item2Res = $this->actingAs($this->cafeOwner)
            ->post("/cafes/{$this->cafe->slug}/menu-items", [
                'category_id' => $category->id,
                'name'        => 'Cappuccino',
                'price'       => '4.50',
                'status'      => 'active',
            ]);
        $item2Res->assertRedirect();

        $this->assertEquals(2, MenuItem::where('cafe_id', $this->cafe->id)->count());

        // Step 4: 3rd menu item attempt MUST be rejected by entitlement limit (422)
        $item3Res = $this->actingAs($this->cafeOwner)
            ->postJson("/cafes/{$this->cafe->slug}/menu-items", [
                'category_id' => $category->id,
                'name'        => 'Latte',
                'price'       => '5.00',
                'status'      => 'active',
            ]);

        $item3Res->assertStatus(422)
            ->assertJsonPath('error_code', 'ENTITLEMENT_LIMIT_REACHED');

        // Step 5: Super Admin upgrades Cafe plan to Pro (unlimited)
        $upgradeResponse = $this->actingAs($this->superAdmin)
            ->post("/admin/cafes/{$this->cafe->id}/subscription/change-plan", [
                'plan_id' => $this->proPlan->id,
                'reason'  => 'Upgraded to pro plan for unlimited items',
            ]);

        $upgradeResponse->assertRedirect();

        // Step 6: Cafe Owner immediately can create 3rd menu item without restart
        $item3Success = $this->actingAs($this->cafeOwner)
            ->post("/cafes/{$this->cafe->slug}/menu-items", [
                'category_id' => $category->id,
                'name'        => 'Latte',
                'price'       => '5.00',
                'status'      => 'active',
            ]);
        $item3Success->assertRedirect();

        $this->assertEquals(3, MenuItem::where('cafe_id', $this->cafe->id)->count());
    }
}
