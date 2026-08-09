<?php

namespace Tests\Feature\Tenant;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SubscriptionBillingTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafeA;
    protected Cafe $cafeB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected User $ownerA;
    protected User $managerA;
    protected User $waiterA;
    protected User $ownerB;
    protected Role $ownerRoleA;
    protected Role $waiterRoleA;
    protected Plan $starterPlan;
    protected Plan $proPlan;
    protected Subscription $subscriptionA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        // Cafe A & Cafe B
        $this->cafeA = Cafe::create([
            'name'   => 'Subscription Cafe A',
            'slug'   => 'sub-cafe-a',
            'status' => 'active',
        ]);

        $this->cafeB = Cafe::create([
            'name'   => 'Subscription Cafe B',
            'slug'   => 'sub-cafe-b',
            'status' => 'active',
        ]);

        $this->branchA = Branch::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Branch A',
            'slug'    => 'branch-a-sub',
            'status'  => 'active',
        ]);

        $this->branchB = Branch::create([
            'cafe_id' => $this->cafeB->id,
            'name'    => 'Branch B',
            'slug'    => 'branch-b-sub',
            'status'  => 'active',
        ]);

        // Permissions
        $subViewPerm = Permission::firstOrCreate(['slug' => 'subscription.view'], ['name' => 'View Subscription']);
        $subUpdatePerm = Permission::firstOrCreate(['slug' => 'subscription.update'], ['name' => 'Update Subscription']);
        $staffViewPerm = Permission::firstOrCreate(['slug' => 'staff.view'], ['name' => 'View Staff']);
        $staffCreatePerm = Permission::firstOrCreate(['slug' => 'staff.create'], ['name' => 'Create Staff']);
        $tableViewPerm = Permission::firstOrCreate(['slug' => 'table.view'], ['name' => 'View Table']);
        $tableCreatePerm = Permission::firstOrCreate(['slug' => 'table.create'], ['name' => 'Create Table']);

        // Owner Role Cafe A
        $this->ownerRoleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Owner A',
            'slug'    => 'owner-a',
            'scope'   => 'tenant',
        ]);
        $this->ownerRoleA->permissions()->attach([
            $subViewPerm->id,
            $subUpdatePerm->id,
            $staffViewPerm->id,
            $staffCreatePerm->id,
            $tableViewPerm->id,
            $tableCreatePerm->id,
        ]);

        // Waiter Role Cafe A (No subscription permissions)
        $this->waiterRoleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Waiter A',
            'slug'    => 'waiter-a',
            'scope'   => 'tenant',
        ]);
        $this->waiterRoleA->permissions()->attach([$tableViewPerm->id]);

        // Owner User Cafe A
        $this->ownerA = User::create([
            'name'     => 'Owner A',
            'email'    => 'ownerA@subcafe.test',
            'password' => Hash::make('password'),
            'status'   => 'active',
        ]);
        CafeUser::create([
            'cafe_id' => $this->cafeA->id,
            'user_id' => $this->ownerA->id,
            'role_id' => $this->ownerRoleA->id,
            'status'  => 'active',
        ]);

        // Waiter User Cafe A
        $this->waiterA = User::create([
            'name'     => 'Waiter A',
            'email'    => 'waiterA@subcafe.test',
            'password' => Hash::make('password'),
            'status'   => 'active',
        ]);
        CafeUser::create([
            'cafe_id' => $this->cafeA->id,
            'user_id' => $this->waiterA->id,
            'role_id' => $this->waiterRoleA->id,
            'status'  => 'active',
        ]);

        // Owner User Cafe B
        $this->ownerB = User::create([
            'name'     => 'Owner B',
            'email'    => 'ownerB@subcafe.test',
            'password' => Hash::make('password'),
            'status'   => 'active',
        ]);
        $ownerRoleB = Role::create([
            'cafe_id' => $this->cafeB->id,
            'name'    => 'Owner B',
            'slug'    => 'owner-b',
            'scope'   => 'tenant',
        ]);
        $ownerRoleB->permissions()->attach([$subViewPerm->id, $subUpdatePerm->id]);
        CafeUser::create([
            'cafe_id' => $this->cafeB->id,
            'user_id' => $this->ownerB->id,
            'role_id' => $ownerRoleB->id,
            'status'  => 'active',
        ]);

        // Plans
        $this->starterPlan = Plan::create([
            'name'             => 'Starter Plan',
            'slug'             => 'starter-plan',
            'description'      => 'Starter plan for small cafes',
            'price'            => 19.99,
            'billing_interval' => 'monthly',
            'status'           => 'active',
        ]);

        PlanFeature::create([
            'plan_id'     => $this->starterPlan->id,
            'feature_key' => 'staff_limit',
            'value'       => '2', // Max 2 staff members
        ]);

        PlanFeature::create([
            'plan_id'     => $this->starterPlan->id,
            'feature_key' => 'table_limit',
            'value'       => '3', // Max 3 tables
        ]);

        $this->proPlan = Plan::create([
            'name'             => 'Pro Plan Unlimited',
            'slug'             => 'pro-plan-unlimited',
            'description'      => 'Unlimited plan',
            'price'            => 49.99,
            'billing_interval' => 'monthly',
            'status'           => 'active',
        ]);

        PlanFeature::create([
            'plan_id'     => $this->proPlan->id,
            'feature_key' => 'staff_limit',
            'value'       => 'unlimited',
        ]);

        PlanFeature::create([
            'plan_id'     => $this->proPlan->id,
            'feature_key' => 'table_limit',
            'value'       => 'unlimited',
        ]);

        // Subscription for Cafe A (Starter Plan)
        $this->subscriptionA = Subscription::create([
            'cafe_id'                  => $this->cafeA->id,
            'plan_id'                  => $this->starterPlan->id,
            'status'                   => 'active',
            'starts_at'                => now(),
            'ends_at'                  => now()->addMonth(),
            'trial_ends_at'            => null,
            'provider'                 => 'razorpay',
            'provider_subscription_id' => 'sub_cafea_123',
        ]);
    }

    /* -------------------------------------------------------------------------- */
    /* 1. SUBSCRIPTION VIEW & UPDATE AUTHORIZATION                                */
    /* -------------------------------------------------------------------------- */

    public function test_subscription_view_authorization(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->getJson('/cafes/sub-cafe-a/subscription');

        $response->assertStatus(200)
            ->assertJsonPath('subscription.plan.name', 'Starter Plan')
            ->assertJsonPath('usage.staff.limit', 2)
            ->assertJsonPath('usage.tables.limit', 3);
    }

    public function test_unauthorized_role_receives_403_on_subscription_view(): void
    {
        $this->actingAs($this->waiterA);

        $response = $this->getJson('/cafes/sub-cafe-a/subscription');

        $response->assertStatus(403);
    }

    public function test_unauthorized_role_receives_403_on_subscription_update(): void
    {
        $this->actingAs($this->waiterA);

        $response = $this->postJson('/cafes/sub-cafe-a/subscription/subscribe', [
            'plan_id' => $this->proPlan->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_tenant_isolation_cross_tenant_subscription_access_denied(): void
    {
        $this->actingAs($this->ownerA);

        // Owner A trying to view Cafe B's subscription
        $response = $this->getJson('/cafes/sub-cafe-b/subscription');

        $response->assertStatus(403);
    }

    /* -------------------------------------------------------------------------- */
    /* 2. SUBSCRIPTION OVERVIEW RESPONSE SHAPE                                    */
    /* -------------------------------------------------------------------------- */

    public function test_subscription_overview_returns_correct_plan_status_dates(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->getJson('/cafes/sub-cafe-a/subscription');

        $response->assertStatus(200)
            ->assertJsonPath('subscription.status', 'active')
            ->assertJsonPath('subscription.provider', 'razorpay')
            ->assertJsonPath('subscription.provider_subscription_id', 'sub_cafea_123')
            ->assertJsonPath('usage.staff.current', 2) // ownerA + waiterA
            ->assertJsonPath('usage.staff.limit', 2)
            ->assertJsonPath('usage.tables.current', 0)
            ->assertJsonPath('usage.tables.limit', 3);
    }

    /* -------------------------------------------------------------------------- */
    /* 3. PLAN LIMIT ENFORCEMENT (STAFF & TABLE LIMITS)                           */
    /* -------------------------------------------------------------------------- */

    public function test_staff_limit_enforcement_rejects_creation_when_reached(): void
    {
        $this->actingAs($this->ownerA);

        // Cafe A has 2 active staff members (ownerA + waiterA), and staff_limit is 2.
        // Creating a 3rd staff member must be rejected with HTTP 422.
        $response = $this->postJson('/cafes/sub-cafe-a/staff', [
            'name'     => 'Extra Staff',
            'email'    => 'extra@subcafe.test',
            'password' => 'password123',
            'role_id'  => $this->waiterRoleA->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['staff']);
    }

    public function test_table_limit_enforcement_rejects_creation_when_reached(): void
    {
        $this->actingAs($this->ownerA);

        // Create 3 tables to hit the limit of 3
        RestaurantTable::create(['branch_id' => $this->branchA->id, 'name' => 'T1', 'qr_token' => 'tok-t1']);
        RestaurantTable::create(['branch_id' => $this->branchA->id, 'name' => 'T2', 'qr_token' => 'tok-t2']);
        RestaurantTable::create(['branch_id' => $this->branchA->id, 'name' => 'T3', 'qr_token' => 'tok-t3']);

        // Creating a 4th table must be rejected with HTTP 422.
        $response = $this->postJson('/cafes/sub-cafe-a/tables', [
            'branch_id' => $this->branchA->id,
            'name'      => 'T4',
            'capacity'  => 4,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['table']);
    }

    public function test_higher_or_unlimited_limits_allow_resource_creation(): void
    {
        $this->actingAs($this->ownerA);

        // Upgrade Cafe A to Pro Plan (unlimited limits)
        $this->subscriptionA->update(['plan_id' => $this->proPlan->id]);

        // Creating a 3rd staff member should now succeed
        $response = $this->postJson('/cafes/sub-cafe-a/staff', [
            'name'     => 'New Staff Pro',
            'email'    => 'newpro@subcafe.test',
            'password' => 'password123',
            'role_id'  => $this->waiterRoleA->id,
        ]);

        $response->assertStatus(201);
    }

    /* -------------------------------------------------------------------------- */
    /* 4. SUBSCRIPTION UPDATE, CANCELLATION & AUDIT LOGGING                        */
    /* -------------------------------------------------------------------------- */

    public function test_subscription_plan_update_and_audit_log(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->postJson('/cafes/sub-cafe-a/subscription/subscribe', [
            'plan_id'                  => $this->proPlan->id,
            'provider'                 => 'stripe',
            'provider_subscription_id' => 'sub_stripe_999',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('subscription.plan_id', $this->proPlan->id)
            ->assertJsonPath('subscription.provider', 'stripe')
            ->assertJsonPath('subscription.provider_subscription_id', 'sub_stripe_999');

        $this->assertDatabaseHas('subscriptions', [
            'cafe_id'                  => $this->cafeA->id,
            'plan_id'                  => $this->proPlan->id,
            'provider'                 => 'stripe',
            'provider_subscription_id' => 'sub_stripe_999',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => 'subscription.updated',
            'entity_type' => 'subscription',
            'cafe_id'     => $this->cafeA->id,
            'user_id'     => $this->ownerA->id,
        ]);
    }

    public function test_subscription_cancellation_and_audit_log(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->postJson('/cafes/sub-cafe-a/subscription/cancel');

        $response->assertStatus(200)
            ->assertJsonPath('subscription.status', 'cancelled');

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $this->subscriptionA->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => 'subscription.cancelled',
            'entity_type' => 'subscription',
            'entity_id'   => $this->subscriptionA->id,
            'cafe_id'     => $this->cafeA->id,
            'user_id'     => $this->ownerA->id,
        ]);
    }

    public function test_invalid_plan_validation_returns_422(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->postJson('/cafes/sub-cafe-a/subscription/subscribe', [
            'plan_id' => 99999, // Invalid plan ID
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan_id']);
    }
}
