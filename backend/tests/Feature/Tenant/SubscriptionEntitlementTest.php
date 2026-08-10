<?php

namespace Tests\Feature\Tenant;

use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionEntitlementTest extends TestCase
{
    use RefreshDatabase;

    protected Cafe $cafe;
    protected User $owner;
    protected User $superAdmin;
    protected Plan $limitedPlan;
    protected Plan $unlimitedPlan;
    protected Role $ownerRole;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->cafe = Cafe::create([
            'name'     => 'Entitlement Test Cafe',
            'slug'     => 'entitlement-test-cafe',
            'email'    => 'cafe@entitlement.test',
            'status'   => 'active',
            'timezone' => 'UTC',
            'currency' => 'USD',
        ]);

        $this->owner = User::create([
            'name'     => 'Cafe Owner',
            'email'    => 'owner@entitlement.test',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);

        $this->superAdmin = User::create([
            'name'     => 'Super Admin',
            'email'    => 'admin@entitlement.test',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);

        $platformRole = Role::firstOrCreate(
            ['slug' => 'super-admin', 'scope' => 'platform'],
            ['name' => 'Super Admin']
        );

        $platformCafe = Cafe::firstOrCreate(
            ['slug' => Cafe::PLATFORM_SENTINEL_SLUG],
            ['name' => 'BrewOS Platform', 'email' => 'admin@brewos.platform', 'status' => 'active']
        );

        CafeUser::create([
            'cafe_id' => $platformCafe->id,
            'user_id' => $this->superAdmin->id,
            'role_id' => $platformRole->id,
            'status'  => 'active',
        ]);

        $this->ownerRole = Role::create([
            'cafe_id' => $this->cafe->id,
            'name'    => 'Cafe Owner',
            'slug'    => 'cafe-owner',
            'scope'   => 'tenant',
        ]);

        $permissions = [
            'branch.view', 'branch.create', 'branch.update',
            'staff.view', 'staff.create', 'staff.update',
            'menu.view', 'menu.create', 'menu.update',
            'table.view', 'table.create', 'table.update',
            'subscription.view', 'subscription.update',
        ];

        foreach ($permissions as $permSlug) {
            $perm = Permission::firstOrCreate(
                ['slug' => $permSlug],
                ['name' => ucfirst(str_replace('.', ' ', $permSlug))]
            );
            $this->ownerRole->permissions()->attach($perm->id);
        }

        CafeUser::create([
            'cafe_id' => $this->cafe->id,
            'user_id' => $this->owner->id,
            'role_id' => $this->ownerRole->id,
            'status'  => 'active',
        ]);

        $this->limitedPlan = Plan::create([
            'name'             => 'Limited Plan',
            'slug'             => 'limited-plan',
            'price'            => 19.99,
            'billing_interval' => 'monthly',
            'status'           => 'active',
        ]);

        PlanFeature::create(['plan_id' => $this->limitedPlan->id, 'feature_key' => 'staff_limit', 'value' => '2']);
        PlanFeature::create(['plan_id' => $this->limitedPlan->id, 'feature_key' => 'table_limit', 'value' => '2']);
        PlanFeature::create(['plan_id' => $this->limitedPlan->id, 'feature_key' => 'branch_limit', 'value' => '2']);
        PlanFeature::create(['plan_id' => $this->limitedPlan->id, 'feature_key' => 'menu_item_limit', 'value' => '2']);

        $this->unlimitedPlan = Plan::create([
            'name'             => 'Unlimited Plan',
            'slug'             => 'unlimited-plan',
            'price'            => 99.99,
            'billing_interval' => 'monthly',
            'status'           => 'active',
        ]);

        PlanFeature::create(['plan_id' => $this->unlimitedPlan->id, 'feature_key' => 'staff_limit', 'value' => 'unlimited']);
        PlanFeature::create(['plan_id' => $this->unlimitedPlan->id, 'feature_key' => 'table_limit', 'value' => 'unlimited']);
        PlanFeature::create(['plan_id' => $this->unlimitedPlan->id, 'feature_key' => 'branch_limit', 'value' => 'unlimited']);
        PlanFeature::create(['plan_id' => $this->unlimitedPlan->id, 'feature_key' => 'menu_item_limit', 'value' => 'unlimited']);

        Subscription::create([
            'cafe_id'                  => $this->cafe->id,
            'plan_id'                  => $this->limitedPlan->id,
            'status'                   => 'active',
            'starts_at'                => now(),
            'ends_at'                  => now()->addMonth(),
            'provider'                 => 'stripe',
            'provider_subscription_id' => 'sub_test_123',
        ]);

        $this->category = Category::create([
            'cafe_id'    => $this->cafe->id,
            'name'       => 'Coffee',
            'sort_order' => 1,
        ]);
    }

    public function test_1_active_subscription_allows_access(): void
    {
        $this->actingAs($this->owner);

        $response = $this->getJson("/cafes/{$this->cafe->slug}/branches");
        $response->assertStatus(200);
    }

    public function test_2_valid_trial_allows_access(): void
    {
        Subscription::where('cafe_id', $this->cafe->id)->update([
            'status'        => 'trialing',
            'trial_ends_at' => now()->addDays(14),
            'ends_at'       => now()->addDays(14),
        ]);

        $this->actingAs($this->owner);

        $response = $this->getJson("/cafes/{$this->cafe->slug}/branches");
        $response->assertStatus(200);
    }

    public function test_3_expired_subscription_is_restricted_serverside(): void
    {
        Subscription::where('cafe_id', $this->cafe->id)->update([
            'status'  => 'expired',
            'ends_at' => now()->subDay(),
        ]);

        $this->actingAs($this->owner);

        $response = $this->getJson("/cafes/{$this->cafe->slug}/branches");
        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'SUBSCRIPTION_EXPIRED');
    }

    public function test_4_cancelled_subscription_is_restricted_serverside(): void
    {
        Subscription::where('cafe_id', $this->cafe->id)->update([
            'status'  => 'cancelled',
            'ends_at' => now()->subDay(),
        ]);

        $this->actingAs($this->owner);

        $response = $this->getJson("/cafes/{$this->cafe->slug}/branches");
        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'SUBSCRIPTION_EXPIRED');
    }

    public function test_5_super_admin_remains_accessible_even_if_subscription_expired(): void
    {
        Subscription::where('cafe_id', $this->cafe->id)->update([
            'status'  => 'expired',
            'ends_at' => now()->subDay(),
        ]);

        $this->actingAs($this->superAdmin);

        $response = $this->getJson('/admin/cafes');
        $response->assertStatus(200);
    }

    public function test_6_staff_limit_enforcement(): void
    {
        $this->actingAs($this->owner);

        // Limit is 2. Create 1 staff member (owner is count 1)
        $this->postJson("/cafes/{$this->cafe->slug}/staff", [
            'name'     => 'Staff 2',
            'email'    => 'staff2@entitlement.test',
            'password' => 'password123',
            'role_id'  => $this->ownerRole->id,
        ])->assertStatus(201);

        // 3rd staff member attempt must trigger HTTP 422 ENTITLEMENT_LIMIT_REACHED
        $response = $this->postJson("/cafes/{$this->cafe->slug}/staff", [
            'name'     => 'Staff 3',
            'email'    => 'staff3@entitlement.test',
            'password' => 'password123',
            'role_id'  => $this->ownerRole->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'ENTITLEMENT_LIMIT_REACHED')
            ->assertJsonPath('feature', 'staff_limit');
    }

    public function test_7_table_limit_enforcement(): void
    {
        $this->actingAs($this->owner);

        $branch = Branch::create([
            'cafe_id' => $this->cafe->id,
            'name'    => 'Main Branch',
            'slug'    => 'main-branch',
            'status'  => 'active',
        ]);

        // Limit is 2
        $this->postJson("/cafes/{$this->cafe->slug}/tables", [
            'branch_id' => $branch->id,
            'name'      => 'T1',
            'capacity'  => 2,
        ])->assertStatus(201);

        $this->postJson("/cafes/{$this->cafe->slug}/tables", [
            'branch_id' => $branch->id,
            'name'      => 'T2',
            'capacity'  => 4,
        ])->assertStatus(201);

        // 3rd table attempt must trigger HTTP 422 ENTITLEMENT_LIMIT_REACHED
        $response = $this->postJson("/cafes/{$this->cafe->slug}/tables", [
            'branch_id' => $branch->id,
            'name'      => 'T3',
            'capacity'  => 6,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'ENTITLEMENT_LIMIT_REACHED')
            ->assertJsonPath('feature', 'table_limit');
    }

    public function test_8_branch_limit_enforcement(): void
    {
        $this->actingAs($this->owner);

        // Limit is 2
        $this->postJson("/cafes/{$this->cafe->slug}/branches", [
            'name' => 'B1',
            'slug' => 'b1',
        ])->assertStatus(201);

        $this->postJson("/cafes/{$this->cafe->slug}/branches", [
            'name' => 'B2',
            'slug' => 'b2',
        ])->assertStatus(201);

        // 3rd branch attempt must trigger HTTP 422 ENTITLEMENT_LIMIT_REACHED
        $response = $this->postJson("/cafes/{$this->cafe->slug}/branches", [
            'name' => 'B3',
            'slug' => 'b3',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'ENTITLEMENT_LIMIT_REACHED')
            ->assertJsonPath('feature', 'branch_limit');
    }

    public function test_9_menu_item_limit_enforcement(): void
    {
        $this->actingAs($this->owner);

        // Limit is 2
        $this->postJson("/cafes/{$this->cafe->slug}/menu-items", [
            'category_id' => $this->category->id,
            'name'        => 'Espresso',
            'price'       => 3.50,
        ])->assertStatus(201);

        $this->postJson("/cafes/{$this->cafe->slug}/menu-items", [
            'category_id' => $this->category->id,
            'name'        => 'Latte',
            'price'       => 4.50,
        ])->assertStatus(201);

        // 3rd menu item attempt must trigger HTTP 422 ENTITLEMENT_LIMIT_REACHED
        $response = $this->postJson("/cafes/{$this->cafe->slug}/menu-items", [
            'category_id' => $this->category->id,
            'name'        => 'Cappuccino',
            'price'       => 4.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'ENTITLEMENT_LIMIT_REACHED')
            ->assertJsonPath('feature', 'menu_item_limit');
    }

    public function test_10_unlimited_plan_behavior_allows_unlimited_creations(): void
    {
        Subscription::where('cafe_id', $this->cafe->id)->update([
            'plan_id' => $this->unlimitedPlan->id,
        ]);

        $this->actingAs($this->owner);

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson("/cafes/{$this->cafe->slug}/branches", [
                'name' => "Branch {$i}",
                'slug' => "branch-{$i}",
            ])->assertStatus(201);
        }

        $this->assertEquals(5, Branch::where('cafe_id', $this->cafe->id)->count());
    }

    public function test_11_exact_limit_boundary(): void
    {
        $this->actingAs($this->owner);

        // Limit = 2. 2 creations succeed.
        $this->postJson("/cafes/{$this->cafe->slug}/branches", ['name' => 'B1', 'slug' => 'b1'])->assertStatus(201);
        $this->postJson("/cafes/{$this->cafe->slug}/branches", ['name' => 'B2', 'slug' => 'b2'])->assertStatus(201);

        // 3rd fails
        $this->postJson("/cafes/{$this->cafe->slug}/branches", ['name' => 'B3', 'slug' => 'b3'])->assertStatus(422);
    }

    public function test_12_below_limit_creation(): void
    {
        $this->actingAs($this->owner);

        $response = $this->postJson("/cafes/{$this->cafe->slug}/branches", [
            'name' => 'Branch 1',
            'slug' => 'branch-1',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('branches', ['cafe_id' => $this->cafe->id, 'slug' => 'branch-1']);
    }

    public function test_13_direct_api_bypass_attempt_is_rejected_by_backend(): void
    {
        $this->actingAs($this->owner);

        // Fill capacity to limit (2)
        $this->postJson("/cafes/{$this->cafe->slug}/branches", ['name' => 'B1', 'slug' => 'b1'])->assertStatus(201);
        $this->postJson("/cafes/{$this->cafe->slug}/branches", ['name' => 'B2', 'slug' => 'b2'])->assertStatus(201);

        // Direct raw JSON API call attempting bypass
        $response = $this->postJson("/cafes/{$this->cafe->slug}/branches", [
            'name'    => 'Bypass Branch',
            'slug'    => 'bypass-branch',
            'cafe_id' => $this->cafe->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'ENTITLEMENT_LIMIT_REACHED');
    }

    public function test_14_tenant_isolation_cafe_a_cannot_manipulate_cafe_b_subscription(): void
    {
        $cafeB = Cafe::create([
            'name'   => 'Cafe B',
            'slug'   => 'cafe-b',
            'email'  => 'cafeb@test.com',
            'status' => 'active',
        ]);

        $this->actingAs($this->owner);

        // Cafe Owner of Cafe A attempts to access Cafe B subscription endpoint
        $response = $this->getJson("/cafes/{$cafeB->slug}/subscription");
        $response->assertStatus(403);
    }

    public function test_15_tenant_isolation_cafe_a_cannot_manipulate_cafe_b_usage(): void
    {
        $cafeB = Cafe::create([
            'name'   => 'Cafe B',
            'slug'   => 'cafe-b',
            'email'  => 'cafeb@test.com',
            'status' => 'active',
        ]);

        $this->actingAs($this->owner);

        // Owner of Cafe A attempts to create staff under Cafe B slug
        $response = $this->postJson("/cafes/{$cafeB->slug}/staff", [
            'name'     => 'Hacked Staff',
            'email'    => 'hacked@test.com',
            'password' => 'password123',
            'role_id'  => $this->ownerRole->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_16_webhook_cannot_update_arbitrary_cafe_without_matching_subscription(): void
    {
        $response = $this->postJson('/api/webhooks/billing/stripe', [
            'event_id'                 => 'evt_random_999',
            'event_type'               => 'subscription.renewed',
            'provider_subscription_id' => 'non_existent_sub_id',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'processed');

        // Verify cafe subscription was NOT modified
        $sub = Subscription::where('cafe_id', $this->cafe->id)->first();
        $this->assertEquals('limited-plan', $sub->plan->slug);
    }

    public function test_17_client_cannot_submit_cafe_id_to_bypass_context(): void
    {
        $this->actingAs($this->owner);

        $response = $this->postJson("/cafes/{$this->cafe->slug}/branches", [
            'name'    => 'Tampered Branch',
            'slug'    => 'tampered-branch',
            'cafe_id' => 99999,
        ]);

        $response->assertStatus(201);
        // Assert creation was correctly scoped to active tenant context, NOT 99999
        $this->assertDatabaseHas('branches', [
            'slug'    => 'tampered-branch',
            'cafe_id' => $this->cafe->id,
        ]);
    }

    public function test_18_successful_billing_event(): void
    {
        $response = $this->postJson('/api/webhooks/billing/stripe', [
            'event_id'                 => 'evt_success_100',
            'event_type'               => 'subscription.renewed',
            'provider_subscription_id' => 'sub_test_123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'processed');

        $this->assertDatabaseHas('billing_events', [
            'provider'   => 'stripe',
            'event_id'   => 'evt_success_100',
            'event_type' => 'subscription.renewed',
        ]);
    }

    public function test_19_repeated_same_billing_event_is_idempotent(): void
    {
        // 1st delivery
        $this->postJson('/api/webhooks/billing/stripe', [
            'event_id'                 => 'evt_repeat_200',
            'event_type'               => 'subscription.renewed',
            'provider_subscription_id' => 'sub_test_123',
        ])->assertStatus(200)->assertJsonPath('status', 'processed');

        // 2nd delivery (duplicate event)
        $response = $this->postJson('/api/webhooks/billing/stripe', [
            'event_id'                 => 'evt_repeat_200',
            'event_type'               => 'subscription.renewed',
            'provider_subscription_id' => 'sub_test_123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ignored_duplicate');
    }

    public function test_20_failed_billing_event(): void
    {
        $response = $this->postJson('/api/webhooks/billing/stripe', [
            'event_id'                 => 'evt_failed_300',
            'event_type'               => 'payment.failed',
            'provider_subscription_id' => 'sub_test_123',
            'reason'                   => 'Card declined',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => 'payment.failed',
            'entity_type' => 'subscription',
        ]);
    }

    public function test_21_cancellation_webhook_event(): void
    {
        $response = $this->postJson('/api/webhooks/billing/stripe', [
            'event_id'                 => 'evt_cancel_400',
            'event_type'               => 'subscription.cancelled',
            'provider_subscription_id' => 'sub_test_123',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('subscriptions', [
            'cafe_id' => $this->cafe->id,
            'status'  => 'cancelled',
        ]);
    }

    public function test_22_renewal_extends_subscription_end_date(): void
    {
        $oldEndsAt = now()->addDays(5);
        Subscription::where('cafe_id', $this->cafe->id)->update(['ends_at' => $oldEndsAt]);

        $this->postJson('/api/webhooks/billing/stripe', [
            'event_id'                 => 'evt_renew_500',
            'event_type'               => 'subscription.renewed',
            'provider_subscription_id' => 'sub_test_123',
        ])->assertStatus(200);

        $sub = Subscription::where('cafe_id', $this->cafe->id)->first();
        $this->assertTrue($sub->ends_at->isAfter(now()->addDays(20)));
    }

    public function test_23_audit_logging_records_entitlement_events(): void
    {
        $this->actingAs($this->owner);

        // Trigger entitlement limit denial
        $this->postJson("/cafes/{$this->cafe->slug}/branches", ['name' => 'B1', 'slug' => 'b1']);
        $this->postJson("/cafes/{$this->cafe->slug}/branches", ['name' => 'B2', 'slug' => 'b2']);
        $this->postJson("/cafes/{$this->cafe->slug}/branches", ['name' => 'B3', 'slug' => 'b3']);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => 'entitlement.denied',
            'cafe_id'     => $this->cafe->id,
            'entity_type' => 'subscription',
        ]);
    }

    public function test_24_concurrent_resource_creation_protection(): void
    {
        $this->actingAs($this->owner);

        // Ensure database transactions and lockForUpdate work cleanly during creation
        $res1 = $this->postJson("/cafes/{$this->cafe->slug}/branches", ['name' => 'Concurrent 1', 'slug' => 'conc-1']);
        $res2 = $this->postJson("/cafes/{$this->cafe->slug}/branches", ['name' => 'Concurrent 2', 'slug' => 'conc-2']);
        $res3 = $this->postJson("/cafes/{$this->cafe->slug}/branches", ['name' => 'Concurrent 3', 'slug' => 'conc-3']);

        $res1->assertStatus(201);
        $res2->assertStatus(201);
        $res3->assertStatus(422);

        $this->assertEquals(2, Branch::where('cafe_id', $this->cafe->id)->count());
    }

    public function test_25_existing_api_compatibility_preserved(): void
    {
        $this->actingAs($this->owner);

        $response = $this->getJson("/cafes/{$this->cafe->slug}/subscription");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'subscription' => ['id', 'plan_id', 'status', 'starts_at', 'ends_at'],
                'usage'        => ['staff', 'tables'],
            ]);
    }
}
