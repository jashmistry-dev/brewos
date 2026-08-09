<?php

namespace Tests\Feature\Tenant;

use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafeA;
    protected Cafe $cafeB;
    protected Branch $branchA1;
    protected Branch $branchA2;
    protected Branch $branchB;
    protected User $ownerA;
    protected User $managerA;
    protected User $cashierA;
    protected User $waiterA;
    protected User $kitchenA;
    protected User $customerA;
    protected User $ownerB;
    protected Role $ownerRoleA;
    protected Role $managerRoleA;
    protected Role $cashierRoleA;
    protected Role $waiterRoleA;
    protected Role $kitchenRoleA;

    protected Customer $registeredCustomer1;
    protected Customer $registeredCustomer2;
    protected Customer $registeredCustomer3;

    protected Category $categoryCoffee;
    protected MenuItem $itemEspresso;
    protected MenuItem $itemLatte;
    protected MenuItem $itemInactive;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        // Cafes
        $this->cafeA = Cafe::create([
            'name'     => 'Analytics Cafe A',
            'slug'     => 'analytics-cafe-a',
            'timezone' => 'Asia/Kolkata',
            'status'   => 'active',
        ]);

        $this->cafeB = Cafe::create([
            'name'     => 'Analytics Cafe B',
            'slug'     => 'analytics-cafe-b',
            'timezone' => 'Asia/Kolkata',
            'status'   => 'active',
        ]);

        // Branches
        $this->branchA1 = Branch::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Analytics Branch A1',
            'slug'    => 'an-branch-a1',
            'status'  => 'active',
        ]);

        $this->branchA2 = Branch::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Analytics Branch A2',
            'slug'    => 'an-branch-a2',
            'status'  => 'active',
        ]);

        $this->branchB = Branch::create([
            'cafe_id' => $this->cafeB->id,
            'name'    => 'Analytics Branch B',
            'slug'    => 'an-branch-b',
            'status'  => 'active',
        ]);

        // Permissions
        $reportViewPerm = Permission::firstOrCreate(['slug' => 'report.view'], ['name' => 'View Reports']);
        $orderViewPerm  = Permission::firstOrCreate(['slug' => 'order.view'], ['name' => 'View Orders']);
        $kitchenViewPerm = Permission::firstOrCreate(['slug' => 'order.kitchen.view'], ['name' => 'View Kitchen']);

        // Roles Cafe A
        $this->ownerRoleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Owner A',
            'slug'    => 'an-owner-a',
            'scope'   => 'tenant',
        ]);
        $this->ownerRoleA->permissions()->attach([$reportViewPerm->id]);

        $this->managerRoleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Manager A',
            'slug'    => 'an-manager-a',
            'scope'   => 'tenant',
        ]);
        $this->managerRoleA->permissions()->attach([$reportViewPerm->id]);

        $this->cashierRoleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Cashier A',
            'slug'    => 'an-cashier-a',
            'scope'   => 'tenant',
        ]);
        $this->cashierRoleA->permissions()->attach([$orderViewPerm->id]); // No report.view

        $this->waiterRoleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Waiter A',
            'slug'    => 'an-waiter-a',
            'scope'   => 'tenant',
        ]);
        $this->waiterRoleA->permissions()->attach([$orderViewPerm->id]); // No report.view

        $this->kitchenRoleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Kitchen A',
            'slug'    => 'an-kitchen-a',
            'scope'   => 'tenant',
        ]);
        $this->kitchenRoleA->permissions()->attach([$kitchenViewPerm->id]); // No report.view

        // Users Cafe A
        $this->ownerA = User::create([
            'name'     => 'Owner A',
            'email'    => 'ownerA@analytics.test',
            'password' => Hash::make('password'),
            'status'   => 'active',
        ]);
        CafeUser::create([
            'cafe_id'   => $this->cafeA->id,
            'user_id'   => $this->ownerA->id,
            'role_id'   => $this->ownerRoleA->id,
            'branch_id' => $this->branchA1->id,
            'status'    => 'active',
        ]);

        $this->managerA = User::create([
            'name'     => 'Manager A',
            'email'    => 'managerA@analytics.test',
            'password' => Hash::make('password'),
            'status'   => 'active',
        ]);
        CafeUser::create([
            'cafe_id'   => $this->cafeA->id,
            'user_id'   => $this->managerA->id,
            'role_id'   => $this->managerRoleA->id,
            'branch_id' => $this->branchA1->id,
            'status'    => 'active',
        ]);

        $this->cashierA = User::create([
            'name'     => 'Cashier A',
            'email'    => 'cashierA@analytics.test',
            'password' => Hash::make('password'),
            'status'   => 'active',
        ]);
        CafeUser::create([
            'cafe_id'   => $this->cafeA->id,
            'user_id'   => $this->cashierA->id,
            'role_id'   => $this->cashierRoleA->id,
            'branch_id' => $this->branchA1->id,
            'status'    => 'active',
        ]);

        $this->waiterA = User::create([
            'name'     => 'Waiter A',
            'email'    => 'waiterA@analytics.test',
            'password' => Hash::make('password'),
            'status'   => 'active',
        ]);
        CafeUser::create([
            'cafe_id'   => $this->cafeA->id,
            'user_id'   => $this->waiterA->id,
            'role_id'   => $this->waiterRoleA->id,
            'branch_id' => $this->branchA1->id,
            'status'    => 'active',
        ]);

        $this->kitchenA = User::create([
            'name'     => 'Kitchen A',
            'email'    => 'kitchenA@analytics.test',
            'password' => Hash::make('password'),
            'status'   => 'active',
        ]);
        CafeUser::create([
            'cafe_id'   => $this->cafeA->id,
            'user_id'   => $this->kitchenA->id,
            'role_id'   => $this->kitchenRoleA->id,
            'branch_id' => $this->branchA1->id,
            'status'    => 'active',
        ]);

        // Customer User
        $this->customerA = User::create([
            'name'     => 'Customer A',
            'email'    => 'customerA@analytics.test',
            'password' => Hash::make('password'),
            'status'   => 'active',
        ]);

        // Owner Cafe B
        $this->ownerB = User::create([
            'name'     => 'Owner B',
            'email'    => 'ownerB@analytics.test',
            'password' => Hash::make('password'),
            'status'   => 'active',
        ]);
        $ownerRoleB = Role::create([
            'cafe_id' => $this->cafeB->id,
            'name'    => 'Owner B',
            'slug'    => 'an-owner-b',
            'scope'   => 'tenant',
        ]);
        $ownerRoleB->permissions()->attach([$reportViewPerm->id]);
        CafeUser::create([
            'cafe_id'   => $this->cafeB->id,
            'user_id'   => $this->ownerB->id,
            'role_id'   => $ownerRoleB->id,
            'branch_id' => $this->branchB->id,
            'status'    => 'active',
        ]);

        // Customers for Cafe A
        $this->registeredCustomer1 = Customer::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Reg Customer 1',
            'phone'   => '9998887771',
            'status'  => 'active',
        ]);

        $this->registeredCustomer2 = Customer::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Reg Customer 2',
            'phone'   => '9998887772',
            'status'  => 'active',
        ]);

        $this->registeredCustomer3 = Customer::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Reg Customer 3 (No Orders)',
            'phone'   => '9998887773',
            'status'  => 'active',
        ]);

        // Menu Items & Categories
        $this->categoryCoffee = Category::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Coffee',
            'status'  => 'active',
        ]);

        $this->itemEspresso = MenuItem::create([
            'cafe_id'     => $this->cafeA->id,
            'category_id' => $this->categoryCoffee->id,
            'name'        => 'Espresso',
            'price'       => 100.00,
            'status'      => 'active',
        ]);

        $this->itemLatte = MenuItem::create([
            'cafe_id'     => $this->cafeA->id,
            'category_id' => $this->categoryCoffee->id,
            'name'        => 'Latte',
            'price'       => 150.00,
            'status'      => 'active',
        ]);

        $this->itemInactive = MenuItem::create([
            'cafe_id'     => $this->cafeA->id,
            'category_id' => $this->categoryCoffee->id,
            'name'        => 'Old Specialty Brew',
            'price'       => 200.00,
            'status'      => 'inactive',
        ]);

        // Seed Orders & Order Items
        // Customer 1: 2 completed orders (Repeat customer)
        $order1 = Order::create([
            'cafe_id'      => $this->cafeA->id,
            'branch_id'    => $this->branchA1->id,
            'customer_id'  => $this->registeredCustomer1->id,
            'order_number' => 'ORD-AN-001',
            'status'       => 'completed',
            'subtotal'     => 350.00,
            'tax'          => 35.00,
            'discount'     => 0.00,
            'total'        => 385.00,
        ]);
        DB::table('orders')->where('id', $order1->id)->update(['created_at' => now()->toDateString() . ' 14:30:00']);

        OrderItem::create([
            'order_id'     => $order1->id,
            'menu_item_id' => $this->itemEspresso->id,
            'quantity'     => 2,
            'unit_price'   => 100.00,
            'total'        => 200.00,
        ]);
        OrderItem::create([
            'order_id'     => $order1->id,
            'menu_item_id' => $this->itemLatte->id,
            'quantity'     => 1,
            'unit_price'   => 150.00,
            'total'        => 150.00,
        ]);

        $order2 = Order::create([
            'cafe_id'      => $this->cafeA->id,
            'branch_id'    => $this->branchA1->id,
            'customer_id'  => $this->registeredCustomer1->id,
            'order_number' => 'ORD-AN-002',
            'status'       => 'completed',
            'subtotal'     => 200.00,
            'tax'          => 20.00,
            'discount'     => 0.00,
            'total'        => 220.00,
        ]);
        DB::table('orders')->where('id', $order2->id)->update(['created_at' => now()->toDateString() . ' 14:45:00']);

        OrderItem::create([
            'order_id'     => $order2->id,
            'menu_item_id' => $this->itemInactive->id, // Inactive item
            'quantity'     => 1,
            'unit_price'   => 200.00,
            'total'        => 200.00,
        ]);

        // Customer 2: 1 completed order (Single order customer)
        $order3 = Order::create([
            'cafe_id'      => $this->cafeA->id,
            'branch_id'    => $this->branchA1->id,
            'customer_id'  => $this->registeredCustomer2->id,
            'order_number' => 'ORD-AN-003',
            'status'       => 'completed',
            'subtotal'     => 100.00,
            'tax'          => 10.00,
            'discount'     => 0.00,
            'total'        => 110.00,
        ]);
        DB::table('orders')->where('id', $order3->id)->update(['created_at' => now()->toDateString() . ' 10:15:00']);

        OrderItem::create([
            'order_id'     => $order3->id,
            'menu_item_id' => $this->itemEspresso->id,
            'quantity'     => 1,
            'unit_price'   => 100.00,
            'total'        => 100.00,
        ]);

        // Guest Order (customer_id = NULL)
        $guestOrder = Order::create([
            'cafe_id'      => $this->cafeA->id,
            'branch_id'    => $this->branchA1->id,
            'customer_id'  => null,
            'order_number' => 'ORD-AN-GUEST',
            'status'       => 'completed',
            'subtotal'     => 150.00,
            'tax'          => 15.00,
            'discount'     => 0.00,
            'total'        => 165.00,
        ]);
        DB::table('orders')->where('id', $guestOrder->id)->update(['created_at' => now()->toDateString() . ' 14:10:00']);

        OrderItem::create([
            'order_id'     => $guestOrder->id,
            'menu_item_id' => $this->itemLatte->id,
            'quantity'     => 1,
            'unit_price'   => 150.00,
            'total'        => 150.00,
        ]);

        // Cancelled Order (Must be excluded from all metrics)
        $cancelledOrder = Order::create([
            'cafe_id'      => $this->cafeA->id,
            'branch_id'    => $this->branchA1->id,
            'customer_id'  => $this->registeredCustomer2->id,
            'order_number' => 'ORD-AN-CANCELLED',
            'status'       => 'cancelled',
            'subtotal'     => 500.00,
            'tax'          => 50.00,
            'discount'     => 0.00,
            'total'        => 550.00,
        ]);
        DB::table('orders')->where('id', $cancelledOrder->id)->update(['created_at' => now()->toDateString() . ' 14:50:00']);
        OrderItem::create([
            'order_id'     => $cancelledOrder->id,
            'menu_item_id' => $this->itemEspresso->id,
            'quantity'     => 5,
            'unit_price'   => 100.00,
            'total'        => 500.00,
        ]);
    }

    /* -------------------------------------------------------------------------- */
    /* 1. AUTHORIZATION TESTS                                                     */
    /* -------------------------------------------------------------------------- */

    public function test_owner_can_access_all_analytics_endpoints(): void
    {
        $this->actingAs($this->ownerA);

        $this->getJson('/cafes/analytics-cafe-a/analytics/customers')->assertStatus(200);
        $this->getJson('/cafes/analytics-cafe-a/analytics/menu')->assertStatus(200);
        $this->getJson('/cafes/analytics-cafe-a/analytics/peak-hours')->assertStatus(200);
    }

    public function test_manager_can_access_all_analytics_endpoints(): void
    {
        $this->actingAs($this->managerA);

        $this->getJson('/cafes/analytics-cafe-a/analytics/customers')->assertStatus(200);
        $this->getJson('/cafes/analytics-cafe-a/analytics/menu')->assertStatus(200);
        $this->getJson('/cafes/analytics-cafe-a/analytics/peak-hours')->assertStatus(200);
    }

    public function test_cashier_denied_access_to_analytics(): void
    {
        $this->actingAs($this->cashierA);

        $this->getJson('/cafes/analytics-cafe-a/analytics/customers')->assertStatus(403);
        $this->getJson('/cafes/analytics-cafe-a/analytics/menu')->assertStatus(403);
        $this->getJson('/cafes/analytics-cafe-a/analytics/peak-hours')->assertStatus(403);
    }

    public function test_waiter_denied_access_to_analytics(): void
    {
        $this->actingAs($this->waiterA);

        $this->getJson('/cafes/analytics-cafe-a/analytics/customers')->assertStatus(403);
    }

    public function test_kitchen_staff_denied_access_to_analytics(): void
    {
        $this->actingAs($this->kitchenA);

        $this->getJson('/cafes/analytics-cafe-a/analytics/customers')->assertStatus(403);
    }

    public function test_customer_user_denied_access_to_analytics(): void
    {
        $this->actingAs($this->customerA);

        $this->getJson('/cafes/analytics-cafe-a/analytics/customers')->assertStatus(403);
    }

    /* -------------------------------------------------------------------------- */
    /* 2. TENANT ISOLATION & CROSS-TENANT REJECTION                               */
    /* -------------------------------------------------------------------------- */

    public function test_cross_tenant_access_is_forbidden(): void
    {
        $this->actingAs($this->ownerA);

        // Cafe A owner requesting Cafe B analytics
        $this->getJson('/cafes/analytics-cafe-b/analytics/customers')->assertStatus(403);
    }

    public function test_cross_tenant_branch_id_is_rejected(): void
    {
        $this->actingAs($this->ownerA);

        // Passing Branch B (belongs to Cafe B) to Cafe A analytics query
        $response = $this->getJson('/cafes/analytics-cafe-a/analytics/customers?branch_id=' . $this->branchB->id);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['branch_id']);
    }

    /* -------------------------------------------------------------------------- */
    /* 3. CUSTOMER BEHAVIOR ANALYTICS CALCULATIONS                                */
    /* -------------------------------------------------------------------------- */

    public function test_customer_behavior_analytics_calculations(): void
    {
        $this->actingAs($this->ownerA);

        // Registered Customers in Cafe A = 3 (Customer 1, 2, 3)
        // Customers with non-cancelled orders = 2 (Customer 1 has 2 orders, Customer 2 has 1 order)
        // Repeat Customers (>= 2 orders) = 1 (Customer 1)
        // Repeat Customer Rate = 1 / 2 * 100 = 50.00%
        // Total Sales from Reg Customers = Order 1 (385.00) + Order 2 (220.00) + Order 3 (110.00) = 715.00
        // Average Spend Per Customer = 715.00 / 2 = 357.50
        // Guest Orders = 1 (guestOrder = 165.00)

        $response = $this->getJson('/cafes/analytics-cafe-a/analytics/customers?start_date=' . now()->subDays(1)->toDateString() . '&end_date=' . now()->toDateString());

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('summary.total_registered_customers'));
        $this->assertEquals(2, $response->json('summary.total_customers_with_orders'));
        $this->assertEquals(1, $response->json('summary.repeat_customers'));
        $this->assertEquals(50.00, $response->json('summary.repeat_customer_rate'));
        $this->assertEquals(357.50, $response->json('summary.average_spend_per_customer'));
        $this->assertEquals(1, $response->json('summary.guest_orders'));
    }

    /* -------------------------------------------------------------------------- */
    /* 4. MENU PERFORMANCE ANALYTICS & INACTIVE/SOFT-DELETED ITEMS INCLUSION       */
    /* -------------------------------------------------------------------------- */

    public function test_menu_performance_analytics_ranking_and_inactive_item_inclusion(): void
    {
        $this->actingAs($this->ownerA);

        // Espresso: Order 1 (qty 2, total 200.00) + Order 3 (qty 1, total 100.00) = 3 sold, 300.00 revenue
        // Latte: Order 1 (qty 1, total 150.00) + Guest Order (qty 1, total 150.00) = 2 sold, 300.00 revenue
        // Old Specialty Brew (Inactive): Order 2 (qty 1, total 200.00) = 1 sold, 200.00 revenue
        // Cancelled order espresso (qty 5) is excluded!

        $response = $this->getJson('/cafes/analytics-cafe-a/analytics/menu?start_date=' . now()->subDays(1)->toDateString() . '&end_date=' . now()->toDateString());

        $response->assertStatus(200)
            ->assertJsonCount(3, 'menu_performance');

        $items = $response->json('menu_performance');

        // Ranked by quantity_sold descending:
        // Rank 1: Espresso (3 sold)
        $this->assertEquals($this->itemEspresso->id, $items[0]['menu_item_id']);
        $this->assertEquals('Espresso', $items[0]['menu_item_name']);
        $this->assertEquals(3, $items[0]['quantity_sold']);
        $this->assertEquals(300.00, $items[0]['revenue']);

        // Rank 2: Latte (2 sold)
        $this->assertEquals($this->itemLatte->id, $items[1]['menu_item_id']);
        $this->assertEquals(2, $items[1]['quantity_sold']);
        $this->assertEquals(300.00, $items[1]['revenue']);

        // Rank 3: Inactive item included historically!
        $this->assertEquals($this->itemInactive->id, $items[2]['menu_item_id']);
        $this->assertEquals('Old Specialty Brew', $items[2]['menu_item_name']);
        $this->assertEquals(1, $items[2]['quantity_sold']);
        $this->assertEquals(200.00, $items[2]['revenue']);
    }

    public function test_menu_performance_limit_query_parameter(): void
    {
        $this->actingAs($this->ownerA);

        // Limit to Top 1 item
        $response = $this->getJson('/cafes/analytics-cafe-a/analytics/menu?limit=1&start_date=' . now()->subDays(1)->toDateString() . '&end_date=' . now()->toDateString());

        $response->assertStatus(200)
            ->assertJsonCount(1, 'menu_performance');

        $this->assertEquals($this->itemEspresso->id, $response->json('menu_performance.0.menu_item_id'));
    }

    /* -------------------------------------------------------------------------- */
    /* 5. PEAK HOUR ANALYSIS                                                      */
    /* -------------------------------------------------------------------------- */

    public function test_peak_hour_analysis_returns_24_buckets_and_correct_peak(): void
    {
        $this->actingAs($this->ownerA);

        // Non-cancelled orders seeded today:
        // Order 1: 14:30 (Hour 14, total 385.00)
        // Order 2: 14:45 (Hour 14, total 220.00)
        // Guest Order: 14:10 (Hour 14, total 165.00)
        // Order 3: 10:15 (Hour 10, total 110.00)
        // Cancelled Order at 14:50 is EXCLUDED.
        // Peak Hour = "14", Peak Order Count = 3

        $response = $this->getJson('/cafes/analytics-cafe-a/analytics/peak-hours?start_date=' . now()->subDays(1)->toDateString() . '&end_date=' . now()->toDateString());

        $response->assertStatus(200);

        // Must return all 24 hourly buckets (00 through 23)
        $this->assertCount(24, $response->json('hourly_breakdown'));

        $this->assertEquals('14', $response->json('summary.peak_hour'));
        $this->assertEquals(3, $response->json('summary.peak_order_count'));

        // Check hour 14 bucket details: count 3, revenue 385 + 220 + 165 = 770.00
        $hour14 = collect($response->json('hourly_breakdown'))->firstWhere('hour', '14');
        $this->assertNotNull($hour14);
        $this->assertEquals(3, $hour14['order_count']);
        $this->assertEquals(770.00, $hour14['revenue']);

        // Check hour 10 bucket details: count 1, revenue 110.00
        $hour10 = collect($response->json('hourly_breakdown'))->firstWhere('hour', '10');
        $this->assertNotNull($hour10);
        $this->assertEquals(1, $hour10['order_count']);
        $this->assertEquals(110.00, $hour10['revenue']);
    }

    /* -------------------------------------------------------------------------- */
    /* 6. BRANCH FILTERING & EMPTY RESULT HANDLING                                */
    /* -------------------------------------------------------------------------- */

    public function test_analytics_branch_filtering(): void
    {
        $this->actingAs($this->ownerA);

        // Filter Customer Analytics by Branch A2 (No orders exist for Branch A2)
        $response = $this->getJson('/cafes/analytics-cafe-a/analytics/customers?branch_id=' . $this->branchA2->id . '&start_date=' . now()->subDays(1)->toDateString() . '&end_date=' . now()->toDateString());

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('summary.total_customers_with_orders'));
        $this->assertEquals(0, $response->json('summary.repeat_customers'));
        $this->assertEquals(0.00, $response->json('summary.repeat_customer_rate'));
    }

    public function test_empty_date_range_returns_zero_metrics_without_errors(): void
    {
        $this->actingAs($this->ownerA);

        $futureStart = now()->addDays(10)->toDateString();
        $futureEnd   = now()->addDays(15)->toDateString();

        $response = $this->getJson("/cafes/analytics-cafe-a/analytics/customers?start_date={$futureStart}&end_date={$futureEnd}");

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('summary.total_customers_with_orders'));
        $this->assertEquals(0.00, $response->json('summary.average_spend_per_customer'));

        $menuResp = $this->getJson("/cafes/analytics-cafe-a/analytics/menu?start_date={$futureStart}&end_date={$futureEnd}");
        $menuResp->assertStatus(200)
            ->assertJsonCount(0, 'menu_performance');

        $peakResp = $this->getJson("/cafes/analytics-cafe-a/analytics/peak-hours?start_date={$futureStart}&end_date={$futureEnd}");
        $peakResp->assertStatus(200);
        $this->assertCount(24, $peakResp->json('hourly_breakdown'));
        $this->assertEquals('00', $peakResp->json('summary.peak_hour'));
        $this->assertEquals(0, $peakResp->json('summary.peak_order_count'));
    }
}
