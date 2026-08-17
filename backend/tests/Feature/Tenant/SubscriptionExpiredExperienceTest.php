<?php

namespace Tests\Feature\Tenant;

use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\OrderingSession;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SubscriptionExpiredExperienceTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafe;
    protected User $owner;
    protected User $superAdmin;
    protected Plan $plan;
    protected Branch $branch;
    protected RestaurantTable $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->cafe = Cafe::create([
            'name'     => 'Subscription UX Cafe',
            'slug'     => 'sub-ux-cafe',
            'email'    => 'owner@subux.test',
            'status'   => 'active',
            'timezone' => 'UTC',
            'currency' => 'USD',
        ]);

        $this->branch = Branch::create([
            'cafe_id' => $this->cafe->id,
            'name'    => 'Main Branch',
            'slug'    => 'main',
            'status'  => 'active',
        ]);

        $this->table = RestaurantTable::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Table 1',
            'qr_token'  => 'table-qr-token-12345',
        ]);

        $this->owner = User::create([
            'name'     => 'Sub UX Owner',
            'email'    => 'owner@subux.test',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);

        $ownerRole = Role::create([
            'cafe_id' => $this->cafe->id,
            'name'    => 'Cafe Owner',
            'slug'    => 'cafe-owner',
            'scope'   => 'tenant',
        ]);

        $perms = ['branch.view', 'staff.view', 'menu.view', 'subscription.view', 'subscription.update', 'order.view'];
        foreach ($perms as $p) {
            $perm = Permission::firstOrCreate(['slug' => $p], ['name' => $p]);
            $ownerRole->permissions()->attach($perm->id);
        }

        CafeUser::create([
            'cafe_id' => $this->cafe->id,
            'user_id' => $this->owner->id,
            'role_id' => $ownerRole->id,
            'status'  => 'active',
        ]);

        $this->superAdmin = User::create([
            'name'     => 'Super Admin',
            'email'    => 'admin@subux.test',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);

        $superAdminRole = Role::firstOrCreate(
            ['slug' => 'super-admin', 'scope' => 'platform'],
            ['name' => 'Super Admin']
        );

        $platformCafe = Cafe::withoutGlobalScopes()->firstOrCreate(
            ['slug' => Cafe::PLATFORM_SENTINEL_SLUG],
            ['name' => 'BrewOS Platform', 'email' => 'admin@brewos.platform', 'status' => 'active']
        );

        CafeUser::create([
            'cafe_id' => $platformCafe->id,
            'user_id' => $this->superAdmin->id,
            'role_id' => $superAdminRole->id,
            'status'  => 'active',
        ]);

        $this->plan = Plan::create([
            'name'             => 'Pro Plan',
            'slug'             => 'pro-plan',
            'price'            => 29.00,
            'billing_interval' => 'monthly',
            'status'           => 'active',
        ]);

        Subscription::create([
            'cafe_id'   => $this->cafe->id,
            'plan_id'   => $this->plan->id,
            'status'    => 'active',
            'starts_at' => now(),
            'ends_at'   => now()->addMonth(),
        ]);
    }

    public function test_1_expired_tenant_dashboard_renders_subscription_required(): void
    {
        Subscription::where('cafe_id', $this->cafe->id)->update([
            'status'  => 'expired',
            'ends_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->owner)
            ->get("/cafes/{$this->cafe->slug}/dashboard");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Errors/SubscriptionRequired')
            ->where('cafeSlug', $this->cafe->slug)
        );
    }

    public function test_2_cancelled_tenant_dashboard_renders_subscription_required(): void
    {
        Subscription::where('cafe_id', $this->cafe->id)->update([
            'status'  => 'cancelled',
            'ends_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->owner)
            ->get("/cafes/{$this->cafe->slug}/dashboard");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Errors/SubscriptionRequired')
        );
    }

    public function test_3_tenant_can_access_subscription_page_while_expired(): void
    {
        Subscription::where('cafe_id', $this->cafe->id)->update([
            'status'  => 'expired',
            'ends_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->owner)
            ->get("/cafes/{$this->cafe->slug}/subscription");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Tenant/Subscription')
        );
    }

    public function test_3b_inactive_cafe_status_can_access_subscription_page(): void
    {
        $this->cafe->update(['status' => 'inactive']);
        Subscription::where('cafe_id', $this->cafe->id)->update([
            'status'  => 'expired',
            'ends_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->owner)
            ->get("/cafes/{$this->cafe->slug}/subscription");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Tenant/Subscription')
        );
    }

    public function test_4_tenant_can_reactivate_subscription(): void
    {
        Subscription::where('cafe_id', $this->cafe->id)->update([
            'status'  => 'expired',
            'ends_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/cafes/{$this->cafe->slug}/subscription/subscribe", [
                'plan_id' => $this->plan->id,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('subscriptions', [
            'cafe_id' => $this->cafe->id,
            'status'  => 'active',
        ]);
    }

    public function test_5_super_admin_can_access_expired_cafe(): void
    {
        Subscription::where('cafe_id', $this->cafe->id)->update([
            'status'  => 'expired',
            'ends_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/admin/cafes/{$this->cafe->id}");

        $response->assertStatus(200);
    }

    public function test_6_inactive_cafe_qr_cannot_create_ordering_session(): void
    {
        Subscription::where('cafe_id', $this->cafe->id)->update([
            'status'  => 'expired',
            'ends_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/order/c/{$this->cafe->slug}/t/{$this->table->qr_token}");

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'ORDERING_UNAVAILABLE');
    }

    public function test_7_inactive_cafe_qr_returns_customer_friendly_unavailable_page(): void
    {
        Subscription::where('cafe_id', $this->cafe->id)->update([
            'status'  => 'expired',
            'ends_at' => now()->subDay(),
        ]);

        $response = $this->get("/order/c/{$this->cafe->slug}/t/{$this->table->qr_token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Customer/CustomerOrderingUnavailable')
            ->where('cafeName', $this->cafe->name)
        );
    }

    public function test_8_active_cafe_qr_works(): void
    {
        $response = $this->get("/order/c/{$this->cafe->slug}/t/{$this->table->qr_token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Customer/CustomerOrder')
        );
    }

    public function test_9_reactivated_cafe_qr_works_again(): void
    {
        // 1. Expire subscription
        Subscription::where('cafe_id', $this->cafe->id)->update([
            'status'  => 'expired',
            'ends_at' => now()->subDay(),
        ]);

        $expiredResponse = $this->get("/order/c/{$this->cafe->slug}/t/{$this->table->qr_token}");
        $expiredResponse->assertInertia(fn ($page) => $page->component('Customer/CustomerOrderingUnavailable'));

        // 2. Reactivate subscription
        Subscription::where('cafe_id', $this->cafe->id)->update([
            'status'  => 'active',
            'ends_at' => now()->addMonth(),
        ]);

        // 3. QR Ordering immediately works again
        $activeResponse = $this->get("/order/c/{$this->cafe->slug}/t/{$this->table->qr_token}");
        $activeResponse->assertInertia(fn ($page) => $page->component('Customer/CustomerOrder'));
    }

    public function test_10_no_cross_tenant_qr_access(): void
    {
        $otherCafe = Cafe::create([
            'name'  => 'Other Cafe',
            'slug'  => 'other-cafe',
            'email' => 'other@cafe.test',
        ]);

        // Trying to use cafe A's slug with cafe B's QR token returns 422
        $response = $this->getJson("/order/c/{$otherCafe->slug}/t/{$this->table->qr_token}");

        $response->assertStatus(422)
            ->assertJsonPath('errors.qr_token.0', 'Invalid or expired QR code.');
    }

    public function test_11_subscription_status_changes_are_recognized_immediately(): void
    {
        // Super Admin changes active -> expired
        $this->actingAs($this->superAdmin);
        Subscription::where('cafe_id', $this->cafe->id)->update(['status' => 'expired']);

        // Instant check by customer
        $res1 = $this->getJson("/order/c/{$this->cafe->slug}/t/{$this->table->qr_token}");
        $res1->assertStatus(403);

        // Super Admin changes expired -> active
        Subscription::where('cafe_id', $this->cafe->id)->update(['status' => 'active']);

        // Instant check by customer
        $res2 = $this->get("/order/c/{$this->cafe->slug}/t/{$this->table->qr_token}");
        $res2->assertStatus(200);
    }
}
