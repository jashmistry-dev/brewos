<?php

namespace Tests\Feature\Admin;

use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuperAdminInertiaResponseTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected Cafe $cafe;
    protected Plan $plan;
    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $platformCafe = Cafe::withoutGlobalScopes()->create([
            'name'   => 'BrewOS Platform',
            'slug'   => 'brewos-platform',
            'status' => 'active',
        ]);

        $superAdminRole = Role::create([
            'name'    => 'Super Admin',
            'slug'    => 'super-admin',
            'scope'   => 'platform',
            'cafe_id' => null,
        ]);

        $this->superAdmin = User::factory()->create([
            'name'   => 'Super Admin User',
            'email'  => 'superadmin_test@brewos.local',
            'status' => 'active',
        ]);

        CafeUser::create([
            'user_id'   => $this->superAdmin->id,
            'cafe_id'   => $platformCafe->id,
            'role_id'   => $superAdminRole->id,
            'branch_id' => null,
            'status'    => 'active',
        ]);

        $this->cafe = Cafe::create([
            'name'   => 'Inertia Test Cafe',
            'slug'   => 'inertia-test-cafe',
            'email'  => 'inertia@test.com',
            'status' => 'active',
        ]);

        $this->plan = Plan::create([
            'name'             => 'Inertia Test Plan',
            'slug'             => 'inertia-test-plan',
            'price'            => 49.99,
            'billing_interval' => 'monthly',
            'status'           => 'active',
        ]);

        $this->subscription = Subscription::create([
            'cafe_id'                  => $this->cafe->id,
            'plan_id'                  => $this->plan->id,
            'status'                   => 'active',
            'starts_at'                => now()->subDays(10),
            'ends_at'                  => now()->addDays(20),
            'provider'                 => 'stripe',
            'provider_subscription_id' => 'sub_inertia_123',
        ]);
    }

    public function test_super_admin_dashboard_returns_valid_inertia_response(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/admin/dashboard');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Dashboard'));
    }

    public function test_super_admin_cafes_index_returns_valid_inertia_response(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/admin/cafes');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Cafes'));
    }

    public function test_super_admin_cafe_details_returns_valid_inertia_response(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get("/admin/cafes/{$this->cafe->id}");

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/CafeDetails'));
    }

    public function test_super_admin_plans_index_returns_valid_inertia_response(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/admin/plans');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Plans'));
    }

    public function test_super_admin_subscriptions_index_returns_valid_inertia_response(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/admin/subscriptions');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Subscriptions'));
    }

    public function test_super_admin_audit_logs_index_returns_valid_inertia_response(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/admin/audit-logs');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/AuditLogs'));
    }

    public function test_super_admin_action_returns_redirect_on_web_and_json_on_api(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        // 1. Web / Inertia Action Request -> Redirect Back
        $webResponse = $this->actingAs($this->superAdmin)
            ->patch("/admin/cafes/{$this->cafe->id}/status", ['status' => 'suspended']);

        $webResponse->assertStatus(302);

        // 2. Pure API Request -> JSON Response
        $apiResponse = $this->actingAs($this->superAdmin)
            ->patchJson("/admin/cafes/{$this->cafe->id}/status", ['status' => 'active']);

        $apiResponse->assertStatus(200)
            ->assertJsonPath('cafe.status', 'active');
    }
}
