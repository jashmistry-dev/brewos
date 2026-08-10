<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminCafeManagementTest extends TestCase
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

        // Create Super Admin
        $this->superAdmin = User::create([
            'name'     => 'Super Admin',
            'email'    => 'superadmin@brewos.test',
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

        // Anchor Super Admin to sentinel cafe
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

        // Create Test Tenant Cafe
        $this->testCafe = Cafe::create([
            'name'     => 'SuperAdmin Test Cafe',
            'slug'     => 'sa-test-cafe',
            'email'    => 'info@satest.com',
            'phone'    => '9876543210',
            'status'   => 'active',
            'currency' => 'INR',
        ]);

        // Tenant Owner
        $this->tenantOwner = User::create([
            'name'     => 'Tenant Owner',
            'email'    => 'owner@satest.com',
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

        // Plan & Features
        $this->testPlan = Plan::create([
            'name'             => 'Standard Plan',
            'slug'             => 'standard-plan',
            'description'      => 'Standard SaaS plan',
            'price'            => 29.99,
            'billing_interval' => 'monthly',
            'status'           => 'active',
        ]);

        PlanFeature::create(['plan_id' => $this->testPlan->id, 'feature_key' => 'branch_limit', 'value' => '2']);
        PlanFeature::create(['plan_id' => $this->testPlan->id, 'feature_key' => 'staff_limit', 'value' => '5']);

        // Subscription
        $this->testSubscription = Subscription::create([
            'cafe_id'                  => $this->testCafe->id,
            'plan_id'                  => $this->testPlan->id,
            'status'                   => 'active',
            'starts_at'                => now()->subDays(10),
            'ends_at'                  => now()->addDays(20),
            'provider'                 => 'stripe',
            'provider_subscription_id' => 'sub_sa_123',
        ]);
    }

    /* -------------------------------------------------------------------------- */
    /* 1. CAFE LISTING                                                            */
    /* -------------------------------------------------------------------------- */

    public function test_super_admin_can_view_cafe_list(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->get('/admin/cafes');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Cafes')
            ->has('cafes')
            ->has('metrics')
        );
    }

    public function test_super_admin_json_cafe_list_compatibility(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->getJson('/admin/cafes');

        $response->assertStatus(200)
            ->assertJsonPath('cafes.0.slug', 'sa-test-cafe');
    }

    /* -------------------------------------------------------------------------- */
    /* 2. CAFE DETAILS                                                            */
    /* -------------------------------------------------------------------------- */

    public function test_super_admin_can_view_cafe_details(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->get("/admin/cafes/{$this->testCafe->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/CafeDetails')
            ->where('cafe.id', $this->testCafe->id)
            ->where('owner.email', 'owner@satest.com')
            ->has('usage')
            ->has('metrics')
        );
    }

    /* -------------------------------------------------------------------------- */
    /* 3. TENANT USER DENIED ADMIN CAFE MANAGEMENT                                */
    /* -------------------------------------------------------------------------- */

    public function test_normal_tenant_user_cannot_access_admin_cafe_management(): void
    {
        $this->actingAs($this->tenantOwner);

        $this->get('/admin/cafes')->assertStatus(403);
        $this->get("/admin/cafes/{$this->testCafe->id}")->assertStatus(403);
        $this->patch("/admin/cafes/{$this->testCafe->id}/status", ['status' => 'suspended'])->assertStatus(403);
    }

    /* -------------------------------------------------------------------------- */
    /* 4-7. LIFECYCLE STATUS TRANSITIONS & AUDIT LOGGING                           */
    /* -------------------------------------------------------------------------- */

    public function test_cafe_status_can_be_deactivated_and_logs_audit(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->patch("/admin/cafes/{$this->testCafe->id}/status", [
            'status' => 'inactive',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cafes', [
            'id'     => $this->testCafe->id,
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => 'cafe.status_updated',
            'entity_type' => 'cafe',
            'entity_id'   => $this->testCafe->id,
            'user_id'     => $this->superAdmin->id,
        ]);
    }

    public function test_cafe_status_can_be_suspended_and_logs_audit(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->patch("/admin/cafes/{$this->testCafe->id}/status", [
            'status' => 'suspended',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cafes', [
            'id'     => $this->testCafe->id,
            'status' => 'suspended',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => 'cafe.status_updated',
            'entity_type' => 'cafe',
            'entity_id'   => $this->testCafe->id,
            'user_id'     => $this->superAdmin->id,
        ]);
    }

    public function test_cafe_status_can_be_reactivated_and_logs_audit(): void
    {
        $this->testCafe->update(['status' => 'suspended']);
        $this->actingAs($this->superAdmin);

        $response = $this->patch("/admin/cafes/{$this->testCafe->id}/status", [
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cafes', [
            'id'     => $this->testCafe->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => 'cafe.status_updated',
            'entity_type' => 'cafe',
            'entity_id'   => $this->testCafe->id,
            'user_id'     => $this->superAdmin->id,
        ]);
    }

    /* -------------------------------------------------------------------------- */
    /* 8. SUBSCRIPTION EXPIRY RECOMMENDATION                                      */
    /* -------------------------------------------------------------------------- */

    public function test_subscription_expiry_recommendation_calculated_correctly(): void
    {
        // Expired subscription
        $this->testSubscription->update([
            'ends_at' => now()->subDay(),
            'status'  => 'expired',
        ]);

        $this->actingAs($this->superAdmin);

        $response = $this->get("/admin/cafes/{$this->testCafe->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('subscription.expiry_recommendation', 'expired')
        );
    }

    /* -------------------------------------------------------------------------- */
    /* 9. USAGE METRICS SCOPING                                                   */
    /* -------------------------------------------------------------------------- */

    public function test_usage_metrics_are_scoped_correctly(): void
    {
        Branch::create(['cafe_id' => $this->testCafe->id, 'name' => 'B1', 'slug' => 'b1', 'status' => 'active']);

        $this->actingAs($this->superAdmin);

        $response = $this->get("/admin/cafes/{$this->testCafe->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('usage.branches.current', 1)
            ->where('usage.branches.limit', 2)
            ->where('usage.staff.current', 1)
            ->where('usage.staff.limit', 5)
        );
    }

    /* -------------------------------------------------------------------------- */
    /* 10. CONFIDENTIAL FIELDS NOT EXPOSED                                        */
    /* -------------------------------------------------------------------------- */

    public function test_confidential_fields_are_not_exposed(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->get("/admin/cafes/{$this->testCafe->id}");

        $response->assertStatus(200);
        $content = json_encode($response->original->getData());

        $this->assertStringNotContainsString('password', strtolower($content));
        $this->assertStringNotContainsString('remember_token', strtolower($content));
    }
}
