<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;
    protected User $tenantManager;
    protected Cafe $cafe;
    protected Plan $plan;
    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        // Tenant setup (Regular Cafe + Manager)
        $this->cafe = Cafe::create([
            'name'   => 'Test Cafe Admin',
            'slug'   => 'test-cafe-admin',
            'status' => 'active',
        ]);

        // Super Admin setup
        $this->superAdmin = User::create([
            'name'     => 'Super Admin',
            'email'    => 'superadmin@brewos.test',
            'password' => Hash::make('password'),
            'status'   => 'active',
        ]);

        $superAdminRole = Role::create([
            'cafe_id'     => null,
            'name'        => 'Super Admin',
            'slug'        => 'super-admin',
            'scope'       => 'platform',
            'description' => 'Platform Super Admin',
        ]);

        CafeUser::create([
            'cafe_id'   => $this->cafe->id,
            'user_id'   => $this->superAdmin->id,
            'role_id'   => $superAdminRole->id,
            'branch_id' => null,
            'status'    => 'active',
        ]);

        $this->tenantManager = User::create([
            'name'     => 'Cafe Manager',
            'email'    => 'manager@testcafe.test',
            'password' => Hash::make('password'),
            'status'   => 'active',
        ]);

        $managerRole = Role::create([
            'cafe_id'     => $this->cafe->id,
            'name'        => 'Manager',
            'slug'        => 'manager',
            'scope'       => 'tenant',
            'description' => 'Cafe Manager',
        ]);

        CafeUser::create([
            'cafe_id'   => $this->cafe->id,
            'user_id'   => $this->tenantManager->id,
            'role_id'   => $managerRole->id,
            'branch_id' => null,
            'status'    => 'active',
        ]);

        // Seed a plan & subscription
        $this->plan = Plan::create([
            'name'             => 'Pro Plan',
            'slug'             => 'pro-plan',
            'description'      => 'Professional subscription plan',
            'price'            => 49.99,
            'billing_interval' => 'monthly',
            'status'           => 'active',
        ]);

        $this->subscription = Subscription::create([
            'cafe_id'       => $this->cafe->id,
            'plan_id'       => $this->plan->id,
            'status'        => 'active',
            'starts_at'     => now(),
            'ends_at'       => now()->addMonth(),
            'trial_ends_at' => null,
            'provider'      => 'razorpay',
            'provider_subscription_id' => 'sub_test_123',
        ]);
    }

    /* -------------------------------------------------------------------------- */
    /* 1. AUTHENTICATION AND AUTHORIZATION                                        */
    /* -------------------------------------------------------------------------- */

    public function test_unauthenticated_admin_access_rejected_with_401(): void
    {
        $response = $this->getJson('/admin/dashboard');

        $response->assertStatus(401);
    }

    public function test_tenant_user_admin_access_rejected_with_403(): void
    {
        $this->actingAs($this->tenantManager);

        $response = $this->getJson('/admin/dashboard');

        $response->assertStatus(403);
    }

    public function test_super_admin_dashboard_access_successful(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->getJson('/admin/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('metrics.total_cafes', 1)
            ->assertJsonPath('metrics.total_plans', 1)
            ->assertJsonPath('metrics.total_subscriptions', 1);
    }

    public function test_admin_routes_do_not_activate_tenant_context(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->getJson('/admin/dashboard');

        $response->assertStatus(200);
        $this->assertFalse(app(TenantContext::class)->hasTenant());
    }

    /* -------------------------------------------------------------------------- */
    /* 2. CAFE LISTING AND MANAGEMENT                                             */
    /* -------------------------------------------------------------------------- */

    public function test_super_admin_can_list_cafes(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->getJson('/admin/cafes');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'cafes')
            ->assertJsonPath('cafes.0.slug', 'test-cafe-admin');
    }

    public function test_super_admin_can_view_cafe_details(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->getJson("/admin/cafes/{$this->cafe->id}");

        $response->assertStatus(200)
            ->assertJsonPath('cafe.name', 'Test Cafe Admin')
            ->assertJsonPath('cafe.slug', 'test-cafe-admin');
    }

    public function test_super_admin_can_update_cafe_status_and_logs_audit(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->patchJson("/admin/cafes/{$this->cafe->id}/status", [
            'status' => 'suspended',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('cafe.status', 'suspended');

        $this->assertDatabaseHas('cafes', [
            'id'     => $this->cafe->id,
            'status' => 'suspended',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => 'cafe.status_updated',
            'entity_type' => 'cafe',
            'entity_id'   => $this->cafe->id,
            'user_id'     => $this->superAdmin->id,
            'cafe_id'     => $this->cafe->id,
        ]);
    }

    public function test_super_admin_can_delete_cafe_and_logs_audit(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->deleteJson("/admin/cafes/{$this->cafe->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('cafes', [
            'id' => $this->cafe->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => 'cafe.deleted',
            'entity_type' => 'cafe',
            'entity_id'   => $this->cafe->id,
            'user_id'     => $this->superAdmin->id,
        ]);
    }

    /* -------------------------------------------------------------------------- */
    /* 3. PLAN CRUD AND DISABLING MANAGEMENT                                      */
    /* -------------------------------------------------------------------------- */

    public function test_super_admin_can_list_plans(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->getJson('/admin/plans');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'plans')
            ->assertJsonPath('plans.0.slug', 'pro-plan');
    }

    public function test_super_admin_can_create_plan_and_logs_audit(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->postJson('/admin/plans', [
            'name'             => 'Enterprise Plan',
            'slug'             => 'enterprise-plan',
            'description'      => 'Large scale cafe plan',
            'price'            => 199.99,
            'billing_interval' => 'yearly',
            'status'           => 'active',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('plan.slug', 'enterprise-plan');

        $this->assertDatabaseHas('plans', [
            'slug' => 'enterprise-plan',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => 'plan.created',
            'entity_type' => 'plan',
            'user_id'     => $this->superAdmin->id,
        ]);
    }

    public function test_duplicate_plan_slug_rejected_with_422(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->postJson('/admin/plans', [
            'name'             => 'Duplicate Plan',
            'slug'             => 'pro-plan', // Duplicate of existing
            'price'            => 29.99,
            'billing_interval' => 'monthly',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_super_admin_can_update_plan(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->putJson("/admin/plans/{$this->plan->id}", [
            'price'  => 59.99,
            'status' => 'disabled',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('plan.status', 'disabled');

        $this->assertDatabaseHas('plans', [
            'id'     => $this->plan->id,
            'status' => 'disabled',
        ]);
    }

    public function test_plan_with_subscriptions_cannot_be_deleted(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->deleteJson("/admin/plans/{$this->plan->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete plan with active or past subscriptions. Disable the plan instead.');
    }

    public function test_plan_without_subscriptions_can_be_deleted(): void
    {
        $this->actingAs($this->superAdmin);

        $emptyPlan = Plan::create([
            'name'             => 'Unused Plan',
            'slug'             => 'unused-plan',
            'price'            => 10.00,
            'billing_interval' => 'monthly',
        ]);

        $response = $this->deleteJson("/admin/plans/{$emptyPlan->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('plans', [
            'id' => $emptyPlan->id,
        ]);
    }

    /* -------------------------------------------------------------------------- */
    /* 4. PLAN FEATURE MANAGEMENT                                                 */
    /* -------------------------------------------------------------------------- */

    public function test_super_admin_can_add_plan_feature(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->postJson("/admin/plans/{$this->plan->id}/features", [
            'feature_key' => 'staff_limit',
            'value'       => '15',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('feature.feature_key', 'staff_limit')
            ->assertJsonPath('feature.value', '15');

        $this->assertDatabaseHas('plan_features', [
            'plan_id'     => $this->plan->id,
            'feature_key' => 'staff_limit',
            'value'       => '15',
        ]);
    }

    public function test_duplicate_plan_feature_key_rejected_with_422(): void
    {
        $this->actingAs($this->superAdmin);

        PlanFeature::create([
            'plan_id'     => $this->plan->id,
            'feature_key' => 'table_limit',
            'value'       => '20',
        ]);

        $response = $this->postJson("/admin/plans/{$this->plan->id}/features", [
            'feature_key' => 'table_limit',
            'value'       => '30',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['feature_key']);
    }

    public function test_super_admin_can_delete_plan_feature(): void
    {
        $this->actingAs($this->superAdmin);

        $feature = PlanFeature::create([
            'plan_id'     => $this->plan->id,
            'feature_key' => 'qr_ordering',
            'value'       => 'true',
        ]);

        $response = $this->deleteJson("/admin/plans/{$this->plan->id}/features/{$feature->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('plan_features', [
            'id' => $feature->id,
        ]);
    }

    /* -------------------------------------------------------------------------- */
    /* 5. SUBSCRIPTION MANAGEMENT AND CANCELLATION                                */
    /* -------------------------------------------------------------------------- */

    public function test_super_admin_can_list_subscriptions(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->getJson('/admin/subscriptions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'subscriptions')
            ->assertJsonPath('subscriptions.0.cafe_slug', 'test-cafe-admin');
    }

    public function test_super_admin_can_view_subscription(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->getJson("/admin/subscriptions/{$this->subscription->id}");

        $response->assertStatus(200)
            ->assertJsonPath('subscription.status', 'active')
            ->assertJsonPath('subscription.plan.name', 'Pro Plan');
    }

    public function test_super_admin_can_cancel_subscription_and_logs_audit(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->postJson("/admin/subscriptions/{$this->subscription->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('subscription.status', 'cancelled');

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $this->subscription->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => 'subscription.cancelled',
            'entity_type' => 'subscription',
            'entity_id'   => $this->subscription->id,
            'user_id'     => $this->superAdmin->id,
            'cafe_id'     => $this->cafe->id,
        ]);
    }

    /* -------------------------------------------------------------------------- */
    /* 6. AUDIT LOG VIEWER AND INTEGRITY                                          */
    /* -------------------------------------------------------------------------- */

    public function test_super_admin_can_view_audit_logs(): void
    {
        $this->actingAs($this->superAdmin);

        AuditLog::create([
            'user_id'     => $this->superAdmin->id,
            'cafe_id'     => $this->cafe->id,
            'action'      => 'test.action',
            'entity_type' => 'test_entity',
            'entity_id'   => 1,
            'old_values'  => ['status' => 'old'],
            'new_values'  => ['status' => 'new'],
        ]);

        $response = $this->getJson('/admin/audit-logs');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('audit_logs.0.action', 'test.action');
    }

    public function test_audit_logs_have_no_update_or_delete_routes(): void
    {
        $this->actingAs($this->superAdmin);

        $auditLog = AuditLog::create([
            'user_id'     => $this->superAdmin->id,
            'action'      => 'test.action',
            'entity_type' => 'test_entity',
        ]);

        $putResponse = $this->putJson("/admin/audit-logs/{$auditLog->id}", ['action' => 'modified']);
        $putResponse->assertStatus(405); // Method Not Allowed

        $deleteResponse = $this->deleteJson("/admin/audit-logs/{$auditLog->id}");
        $deleteResponse->assertStatus(405);
    }

    public function test_sensitive_passwords_never_logged_in_audit_logs(): void
    {
        $this->actingAs($this->superAdmin);

        // Update cafe status
        $this->patchJson("/admin/cafes/{$this->cafe->id}/status", [
            'status' => 'active',
        ]);

        $log = AuditLog::where('action', 'cafe.status_updated')->first();
        $this->assertNotNull($log);

        $logJson = json_encode($log->toArray());
        $this->assertStringNotContainsString('password', strtolower($logJson));
        $this->assertStringNotContainsString('remember_token', strtolower($logJson));
    }
}
