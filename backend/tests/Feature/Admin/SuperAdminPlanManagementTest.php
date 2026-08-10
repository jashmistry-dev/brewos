<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminPlanManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;
    protected User $tenantOwner;
    protected Cafe $testCafe;
    protected Plan $testPlan;
    protected Subscription $testSubscription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        // Super Admin
        $this->superAdmin = User::create([
            'name'     => 'Super Admin',
            'email'    => 'plan_admin@brewos.test',
            'password' => Hash::make('password123'),
            'status'   => 'active',
        ]);

        $superAdminRole = Role::create([
            'cafe_id'     => null,
            'name'        => 'Super Admin',
            'slug'        => 'super-admin',
            'scope'       => 'platform',
            'description' => 'Platform Super Admin',
        ]);

        $sentinelCafe = Cafe::withoutGlobalScopes()->firstOrCreate(
            ['slug' => Cafe::PLATFORM_SENTINEL_SLUG],
            ['name' => 'BrewOS Platform', 'status' => 'active']
        );

        CafeUser::create([
            'cafe_id'   => $sentinelCafe->id,
            'user_id'   => $this->superAdmin->id,
            'role_id'   => $superAdminRole->id,
            'branch_id' => null,
            'status'    => 'active',
        ]);

        // Tenant Cafe
        $this->testCafe = Cafe::create([
            'name'     => 'Plan Test Cafe',
            'slug'     => 'plan-test-cafe',
            'email'    => 'plan@cafe.com',
            'status'   => 'active',
            'notes'    => 'Initial private admin note.',
        ]);

        $this->tenantOwner = User::create([
            'name'     => 'Tenant Owner',
            'email'    => 'owner@plan.com',
            'password' => Hash::make('password123'),
            'status'   => 'active',
        ]);

        $ownerRole = Role::create([
            'cafe_id' => $this->testCafe->id,
            'name'    => 'Cafe Owner',
            'slug'    => 'cafe-owner',
            'scope'   => 'tenant',
        ]);

        CafeUser::create([
            'cafe_id' => $this->testCafe->id,
            'user_id' => $this->tenantOwner->id,
            'role_id' => $ownerRole->id,
            'status'  => 'active',
        ]);

        // Plan & Subscription
        $this->testPlan = Plan::create([
            'name'             => 'Initial Plan',
            'slug'             => 'initial-plan',
            'price'            => 19.99,
            'billing_interval' => 'monthly',
            'status'           => 'active',
        ]);

        $this->testSubscription = Subscription::create([
            'cafe_id'                  => $this->testCafe->id,
            'plan_id'                  => $this->testPlan->id,
            'status'                   => 'active',
            'starts_at'                => now()->subDays(10),
            'ends_at'                  => now()->addDays(20),
            'provider'                 => 'stripe',
            'provider_subscription_id' => 'sub_plan_test_123',
        ]);
    }

    public function test_super_admin_can_render_plans_page(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->get('/admin/plans');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Plans')
            ->has('plans')
        );
    }

    public function test_super_admin_can_create_and_update_plan(): void
    {
        $this->actingAs($this->superAdmin);

        // 1. Create Plan
        $response = $this->post('/admin/plans', [
            'name'             => 'Enterprise Tier',
            'slug'             => 'enterprise-tier',
            'description'      => 'High scale plan',
            'price'            => 199.99,
            'billing_interval' => 'monthly',
            'status'           => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('plans', ['slug' => 'enterprise-tier']);

        $newPlan = Plan::where('slug', 'enterprise-tier')->first();

        // 2. Update Plan
        $updateRes = $this->put("/admin/plans/{$newPlan->id}", [
            'name'             => 'Enterprise Plus',
            'slug'             => 'enterprise-tier',
            'price'            => 249.99,
            'billing_interval' => 'monthly',
            'status'           => 'active',
        ]);

        $updateRes->assertRedirect();
        $this->assertDatabaseHas('plans', ['id' => $newPlan->id, 'name' => 'Enterprise Plus']);
    }

    public function test_super_admin_can_add_and_remove_plan_features(): void
    {
        $this->actingAs($this->superAdmin);

        // Add Feature Limit
        $response = $this->post("/admin/plans/{$this->testPlan->id}/features", [
            'feature_key' => 'staff_limit',
            'value'       => '10',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('plan_features', [
            'plan_id'     => $this->testPlan->id,
            'feature_key' => 'staff_limit',
            'value'       => '10',
        ]);

        $feature = PlanFeature::where('plan_id', $this->testPlan->id)->where('feature_key', 'staff_limit')->first();

        // Remove Feature Limit
        $delRes = $this->delete("/admin/plans/{$this->testPlan->id}/features/{$feature->id}");
        $delRes->assertRedirect();
        $this->assertDatabaseMissing('plan_features', ['id' => $feature->id]);
    }

    public function test_super_admin_can_update_cafe_notes(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->patch("/admin/cafes/{$this->testCafe->id}/notes", [
            'notes' => 'Updated private internal notes for VIP cafe.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cafes', [
            'id'    => $this->testCafe->id,
            'notes' => 'Updated private internal notes for VIP cafe.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => 'cafe.notes_updated',
            'entity_type' => 'cafe',
            'entity_id'   => $this->testCafe->id,
        ]);
    }

    public function test_super_admin_can_extend_subscription_expiry(): void
    {
        $this->actingAs($this->superAdmin);
        $newExpiry = now()->addDays(60)->format('Y-m-d');

        $response = $this->post("/admin/cafes/{$this->testCafe->id}/subscription/extend", [
            'new_ends_at' => $newExpiry,
            'reason'      => 'Comped extra month for loyalty',
        ]);

        $response->assertRedirect();
        $sub = Subscription::where('cafe_id', $this->testCafe->id)->first();
        $this->assertEquals($newExpiry, $sub->ends_at->format('Y-m-d'));

        $this->assertDatabaseHas('audit_logs', [
            'action'      => 'subscription.extended',
            'entity_type' => 'subscription',
        ]);
    }

    public function test_super_admin_can_change_cafe_plan(): void
    {
        $this->actingAs($this->superAdmin);

        $newPlan = Plan::create([
            'name'             => 'Pro Plan',
            'slug'             => 'pro-plan',
            'price'            => 49.99,
            'billing_interval' => 'monthly',
            'status'           => 'active',
        ]);

        $response = $this->post("/admin/cafes/{$this->testCafe->id}/subscription/change-plan", [
            'plan_id' => $newPlan->id,
            'reason'  => 'Admin plan upgrade override',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('subscriptions', [
            'id'      => $this->testSubscription->id,
            'plan_id' => $newPlan->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => 'subscription.plan_changed',
            'entity_type' => 'subscription',
        ]);
    }

    public function test_super_admin_can_reactivate_subscription(): void
    {
        $this->testSubscription->update(['status' => 'cancelled', 'ends_at' => now()->subDay()]);
        $this->actingAs($this->superAdmin);
        $newExpiry = now()->addDays(30)->format('Y-m-d');

        $response = $this->post("/admin/cafes/{$this->testCafe->id}/subscription/reactivate", [
            'new_ends_at' => $newExpiry,
            'reason'      => 'Reactivated after customer payment clearing',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('subscriptions', [
            'id'     => $this->testSubscription->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => 'subscription.reactivated',
            'entity_type' => 'subscription',
        ]);
    }

    public function test_cafe_notes_are_never_exposed_to_tenant_users(): void
    {
        $array = $this->testCafe->toArray();
        $this->assertArrayNotHasKey('notes', $array);

        $json = json_encode($this->testCafe);
        $this->assertStringNotContainsString('Initial private admin note.', $json);
    }

    public function test_tenant_user_cannot_access_plan_management(): void
    {
        $this->actingAs($this->tenantOwner);

        $this->get('/admin/plans')->assertStatus(403);
        $this->post('/admin/plans', ['name' => 'Hacked'])->assertStatus(403);
        $this->patch("/admin/cafes/{$this->testCafe->id}/notes", ['notes' => 'Hacked'])->assertStatus(403);
    }
}
