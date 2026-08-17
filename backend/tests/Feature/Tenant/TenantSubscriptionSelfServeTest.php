<?php

namespace Tests\Feature\Tenant;

use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\DefaultTenantRolesService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantSubscriptionSelfServeTest extends TestCase
{
    use DatabaseTransactions;

    protected User $owner;
    protected Cafe $cafe;
    protected Plan $starterPlan;
    protected Plan $proPlan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->starterPlan = Plan::firstOrCreate(
            ['slug' => 'starter'],
            [
                'name'             => 'Starter Plan',
                'price'            => 29.99,
                'billing_interval' => 'monthly',
                'status'           => 'active',
            ]
        );

        $this->proPlan = Plan::firstOrCreate(
            ['slug' => 'pro'],
            [
                'name'             => 'Pro Plan',
                'price'            => 79.99,
                'billing_interval' => 'monthly',
                'status'           => 'active',
            ]
        );

        $this->cafe = Cafe::create([
            'name'   => 'Self Serve Cafe',
            'slug'   => 'self-serve-cafe',
            'email'  => 'owner@selfserve.com',
            'status' => 'active',
        ]);

        $branch = Branch::create([
            'cafe_id' => $this->cafe->id,
            'name'    => 'Main Branch',
            'slug'    => 'main',
            'status'  => 'active',
        ]);

        $roles = app(DefaultTenantRolesService::class)->createDefaultRolesForCafe($this->cafe);
        $ownerRole = $roles['cafe-owner'];

        $this->owner = User::factory()->create([
            'name'   => 'Self Serve Owner',
            'email'  => 'owner@selfserve.com',
            'status' => 'active',
        ]);

        CafeUser::create([
            'cafe_id'   => $this->cafe->id,
            'user_id'   => $this->owner->id,
            'role_id'   => $ownerRole->id,
            'branch_id' => $branch->id,
            'status'    => 'active',
        ]);

        Subscription::create([
            'cafe_id'                  => $this->cafe->id,
            'plan_id'                  => $this->starterPlan->id,
            'status'                   => 'trialing',
            'starts_at'                => now(),
            'trial_ends_at'            => now()->addDays(14),
            'ends_at'                  => now()->addDays(14),
            'provider'                 => 'system',
            'provider_subscription_id' => 'trial_self_serve',
        ]);
    }

    public function test_owner_can_access_subscription_page(): void
    {
        $response = $this->actingAs($this->owner)
            ->get("/cafes/{$this->cafe->slug}/subscription");

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Tenant/Subscription'));
    }

    public function test_owner_can_self_serve_upgrade_plan(): void
    {
        $response = $this->actingAs($this->owner)
            ->post("/cafes/{$this->cafe->slug}/subscription/subscribe", [
                'plan_id'  => $this->proPlan->id,
                'provider' => 'stripe',
            ]);

        $response->assertStatus(302);

        $sub = Subscription::where('cafe_id', $this->cafe->id)->latest('id')->first();
        $this->assertEquals($this->proPlan->id, $sub->plan_id);
        $this->assertEquals('active', $sub->status);
    }

    public function test_owner_can_self_serve_cancel_subscription(): void
    {
        $response = $this->actingAs($this->owner)
            ->post("/cafes/{$this->cafe->slug}/subscription/cancel");

        $response->assertStatus(302);

        $sub = Subscription::where('cafe_id', $this->cafe->id)->latest('id')->first();
        $this->assertEquals('cancelled', $sub->status);
    }

    public function test_owner_can_access_subscription_page_even_when_subscription_is_expired(): void
    {
        $sub = Subscription::where('cafe_id', $this->cafe->id)->latest('id')->first();
        $sub->update([
            'status'  => 'expired',
            'ends_at' => now()->subDay(),
        ]);

        // Even when expired, tenant MUST be allowed to access /subscription to view plans and upgrade
        $response = $this->actingAs($this->owner)
            ->get("/cafes/{$this->cafe->slug}/subscription");

        $response->assertStatus(200);
    }
}
