<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Category;
use App\Models\CustomerRequest;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\RestaurantTable as Table;
use App\Models\User;
use App\Services\DefaultTenantRolesService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerOrderingAndRoleArchitectureTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafe;
    protected Branch $branch;
    protected Table $table;
    protected MenuItem $menuItem;
    protected User $ownerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerUser = User::factory()->create([
            'email' => 'arch_owner_' . uniqid() . '@test.com',
        ]);

        $this->cafe = Cafe::create([
            'name' => 'Architecture Test Cafe ' . uniqid(),
            'slug' => 'arch-cafe-' . uniqid(),
            'email' => 'cafe_' . uniqid() . '@test.com',
            'status' => 'active',
        ]);

        (new DefaultTenantRolesService())->createDefaultRolesForCafe($this->cafe);

        $ownerRole = Role::where('cafe_id', $this->cafe->id)->where('slug', 'cafe-owner')->firstOrFail();
        CafeUser::create([
            'cafe_id' => $this->cafe->id,
            'user_id' => $this->ownerUser->id,
            'role_id' => $ownerRole->id,
        ]);

        $this->branch = Branch::create([
            'cafe_id' => $this->cafe->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch-' . uniqid(),
            'code' => 'MB-' . rand(100, 999),
        ]);

        $this->table = Table::create([
            'cafe_id' => $this->cafe->id,
            'branch_id' => $this->branch->id,
            'name' => 'Table 01',
            'capacity' => 4,
            'qr_token' => 'ARCH_QR_TOKEN_' . uniqid(),
        ]);

        $category = Category::create([
            'cafe_id' => $this->cafe->id,
            'name' => 'Beverages',
        ]);

        $this->menuItem = MenuItem::create([
            'cafe_id' => $this->cafe->id,
            'category_id' => $category->id,
            'name' => 'Espresso',
            'price' => 150.00,
            'is_available' => true,
        ]);

        $plan = \App\Models\Plan::firstOrCreate([
            'slug' => 'growth',
        ], [
            'name' => 'Growth Plan',
            'price' => 29.00,
            'billing_cycle' => 'monthly',
        ]);

        Subscription::create([
            'cafe_id' => $this->cafe->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    /** @test */
    public function bug1_customer_order_status_includes_qr_url_and_does_not_redirect_to_staff_login()
    {
        $order = Order::create([
            'cafe_id' => $this->cafe->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->table->id,
            'order_number' => 'ORD-ARCH-' . rand(1000, 9999),
            'status' => 'placed',
            'payment_status' => 'payment_pending',
            'subtotal' => 150.00,
            'tax' => 7.50,
            'total' => 157.50,
        ]);

        $response = $this->get(route('public.customer.order_status', ['order_number' => $order->order_number]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Customer/CustomerOrderStatus')
            ->has('qr_url')
            ->where('qr_url', route('public.customer.order_menu', ['cafe_slug' => $this->cafe->slug, 'qr_token' => $this->table->qr_token]))
        );
    }

    /** @test */
    public function bug2_customer_table_service_request_creates_record_and_is_queryable_by_staff()
    {
        $token = 'test_csrf_token_' . uniqid();

        $session = \App\Models\OrderingSession::create([
            'cafe_id' => $this->cafe->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->table->id,
            'qr_token_used' => $this->table->qr_token,
            'session_token' => 'SESS_' . uniqid(),
            'status' => 'active',
            'expires_at' => now()->addHours(3),
        ]);

        $requestResponse = $this->withSession(['_token' => $token])
            ->post(route('public.customer.create_request'), [
                '_token' => $token,
                'session_token' => $session->session_token,
                'request_type' => 'call_staff',
                'notes' => 'Need napkins',
            ], ['X-CSRF-TOKEN' => $token]);

        $requestResponse->assertStatus(200);
        $requestResponse->assertJson(['message' => 'Request sent to cafe staff.']);

        $this->assertDatabaseHas('customer_requests', [
            'cafe_id' => $this->cafe->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->table->id,
            'request_type' => 'call_staff',
            'status' => 'pending',
        ]);

        $ordersViewResponse = $this->actingAs($this->ownerUser)
            ->get(route('tenant.orders.index', ['cafe_slug' => $this->cafe->slug]));

        $ordersViewResponse->assertStatus(200);
        $ordersViewResponse->assertInertia(fn ($page) => $page
            ->component('Tenant/Orders')
            ->has('customer_requests')
        );
    }

    /** @test */
    public function bug3_table_management_auto_generates_and_regenerates_qr_tokens()
    {
        $token = 'test_csrf_token_' . uniqid();
        $originalToken = $this->table->qr_token;

        $response = $this->actingAs($this->ownerUser)
            ->withSession(['_token' => $token])
            ->post(route('tenant.tables.regenerate_qr', [
                'cafe_slug' => $this->cafe->slug,
                'table_id' => $this->table->id,
            ]), ['_token' => $token], ['X-CSRF-TOKEN' => $token]);

        $response->assertRedirect();
        $this->table->refresh();

        $this->assertNotEquals($originalToken, $this->table->qr_token);
        $this->assertNotEmpty($this->table->qr_token);
    }

    /** @test */
    public function bug7_counter_payment_must_be_confirmed_before_order_reaches_kitchen()
    {
        $token = 'test_csrf_token_' . uniqid();

        $order = Order::create([
            'cafe_id' => $this->cafe->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->table->id,
            'order_number' => 'ORD-PAY-' . rand(1000, 9999),
            'status' => 'placed',
            'payment_status' => 'payment_pending',
            'subtotal' => 150.00,
            'tax' => 7.50,
            'total' => 157.50,
        ]);

        $kdsResponseBefore = $this->actingAs($this->ownerUser)
            ->get(route('tenant.kitchen_display.index', ['cafe_slug' => $this->cafe->slug]));

        $kdsResponseBefore->assertStatus(200);
        $kdsResponseBefore->assertInertia(fn ($page) => $page
            ->component('Tenant/KitchenDisplay')
            ->where('orders', fn ($orders) => collect($orders)->contains('id', $order->id) === false)
        );

        $confirmResponse = $this->actingAs($this->ownerUser)
            ->withSession(['_token' => $token])
            ->post(route('tenant.orders.confirm_payment', [
                'cafe_slug' => $this->cafe->slug,
                'order_id' => $order->id,
            ]), [
                '_token' => $token,
                'payment_method' => 'cash',
            ], ['X-CSRF-TOKEN' => $token]);

        $confirmResponse->assertRedirect();
        $order->refresh();

        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('kitchen_pending', $order->status);

        $kdsResponseAfter = $this->actingAs($this->ownerUser)
            ->get(route('tenant.kitchen_display.index', ['cafe_slug' => $this->cafe->slug]));

        $kdsResponseAfter->assertStatus(200);
        $kdsResponseAfter->assertInertia(fn ($page) => $page
            ->component('Tenant/KitchenDisplay')
            ->where('orders', fn ($orders) => collect($orders)->contains('id', $order->id) === true)
        );
    }

    /** @test */
    public function bug9_expired_subscription_renders_customer_friendly_unavailable_page_on_qr()
    {
        Subscription::where('cafe_id', $this->cafe->id)->update([
            'status' => 'expired',
            'ends_at' => now()->subDay(),
        ]);

        $response = $this->get(route('public.customer.order_menu', [
            'cafe_slug' => $this->cafe->slug,
            'qr_token' => $this->table->qr_token,
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Customer/CustomerOrderingUnavailable')
        );
    }
}
