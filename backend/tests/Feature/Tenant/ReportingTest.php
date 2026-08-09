<?php

namespace Tests\Feature\Tenant;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafeA;
    protected Cafe $cafeB;
    protected Branch $branchA1;
    protected Branch $branchA2;
    protected Branch $branchB;
    protected User $ownerA;
    protected User $managerA;
    protected User $waiterA;
    protected User $ownerB;
    protected Role $ownerRoleA;
    protected Role $managerRoleA;
    protected Role $waiterRoleA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        // Cafe A & Cafe B
        $this->cafeA = Cafe::create([
            'name'   => 'Report Cafe A',
            'slug'   => 'report-cafe-a',
            'status' => 'active',
        ]);

        $this->cafeB = Cafe::create([
            'name'   => 'Report Cafe B',
            'slug'   => 'report-cafe-b',
            'status' => 'active',
        ]);

        // Branches
        $this->branchA1 = Branch::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Branch A1',
            'slug'    => 'branch-a1-rep',
            'status'  => 'active',
        ]);

        $this->branchA2 = Branch::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Branch A2',
            'slug'    => 'branch-a2-rep',
            'status'  => 'active',
        ]);

        $this->branchB = Branch::create([
            'cafe_id' => $this->cafeB->id,
            'name'    => 'Branch B',
            'slug'    => 'branch-b-rep',
            'status'  => 'active',
        ]);

        // Permissions
        $reportViewPerm = Permission::firstOrCreate(['slug' => 'report.view'], ['name' => 'View Reports']);
        $orderViewPerm  = Permission::firstOrCreate(['slug' => 'order.view'], ['name' => 'View Orders']);

        // Roles
        $this->ownerRoleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Owner A',
            'slug'    => 'owner-a',
            'scope'   => 'tenant',
        ]);
        $this->ownerRoleA->permissions()->attach([$reportViewPerm->id]);

        $this->managerRoleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Manager A',
            'slug'    => 'manager-a',
            'scope'   => 'tenant',
        ]);
        $this->managerRoleA->permissions()->attach([$reportViewPerm->id]);

        $this->waiterRoleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name'    => 'Waiter A',
            'slug'    => 'waiter-a',
            'scope'   => 'tenant',
        ]);
        $this->waiterRoleA->permissions()->attach([$orderViewPerm->id]); // No report.view

        // Users Cafe A
        $this->ownerA = User::create([
            'name'     => 'Owner A',
            'email'    => 'ownerA@report.test',
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
            'email'    => 'managerA@report.test',
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

        $this->waiterA = User::create([
            'name'     => 'Waiter A',
            'email'    => 'waiterA@report.test',
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

        // Owner Cafe B
        $this->ownerB = User::create([
            'name'     => 'Owner B',
            'email'    => 'ownerB@report.test',
            'password' => Hash::make('password'),
            'status'   => 'active',
        ]);
        $ownerRoleB = Role::create([
            'cafe_id' => $this->cafeB->id,
            'name'    => 'Owner B',
            'slug'    => 'owner-b',
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

        // Seed Orders for Cafe A (Branch A1)
        $order1 = Order::create([
            'cafe_id'      => $this->cafeA->id,
            'branch_id'    => $this->branchA1->id,
            'order_number' => 'ORD-REP-001',
            'status'       => 'completed',
            'subtotal'     => 100.00,
            'tax'          => 18.00,
            'discount'     => 10.00,
            'total'        => 108.00,
            'created_at'   => now(),
        ]);

        Payment::create([
            'cafe_id'  => $this->cafeA->id,
            'order_id' => $order1->id,
            'amount'   => 108.00,
            'method'   => 'upi',
            'status'   => 'completed',
            'created_at' => now(),
        ]);

        $order2 = Order::create([
            'cafe_id'      => $this->cafeA->id,
            'branch_id'    => $this->branchA1->id,
            'order_number' => 'ORD-REP-002',
            'status'       => 'completed',
            'subtotal'     => 200.00,
            'tax'          => 36.00,
            'discount'     => 0.00,
            'total'        => 236.00,
            'created_at'   => now(),
        ]);

        Payment::create([
            'cafe_id'  => $this->cafeA->id,
            'order_id' => $order2->id,
            'amount'   => 236.00,
            'method'   => 'card',
            'status'   => 'completed',
            'created_at' => now(),
        ]);

        // Cancelled order (must be excluded from sales & revenue metrics)
        Order::create([
            'cafe_id'      => $this->cafeA->id,
            'branch_id'    => $this->branchA1->id,
            'order_number' => 'ORD-REP-CANCELLED',
            'status'       => 'cancelled',
            'subtotal'     => 500.00,
            'tax'          => 90.00,
            'discount'     => 0.00,
            'total'        => 590.00,
            'created_at'   => now(),
        ]);

        // Order for Branch A2
        $order3 = Order::create([
            'cafe_id'      => $this->cafeA->id,
            'branch_id'    => $this->branchA2->id,
            'order_number' => 'ORD-REP-003',
            'status'       => 'completed',
            'subtotal'     => 50.00,
            'tax'          => 5.00,
            'discount'     => 0.00,
            'total'        => 55.00,
            'created_at'   => now(),
        ]);

        Payment::create([
            'cafe_id'  => $this->cafeA->id,
            'order_id' => $order3->id,
            'amount'   => 55.00,
            'method'   => 'cash',
            'status'   => 'completed',
            'created_at' => now(),
        ]);

        // Order outside date range (60 days ago)
        $oldOrder = Order::create([
            'cafe_id'      => $this->cafeA->id,
            'branch_id'    => $this->branchA1->id,
            'order_number' => 'ORD-REP-OLD',
            'status'       => 'completed',
            'subtotal'     => 300.00,
            'tax'          => 30.00,
            'discount'     => 0.00,
            'total'        => 330.00,
        ]);
        \Illuminate\Support\Facades\DB::table('orders')
            ->where('id', $oldOrder->id)
            ->update(['created_at' => now()->subDays(60)]);

        // Seed Audit Logs for Staff Activity
        AuditLog::create([
            'cafe_id'     => $this->cafeA->id,
            'user_id'     => $this->managerA->id,
            'action'      => 'order.status_updated',
            'entity_type' => 'order',
            'entity_id'   => $order1->id,
            'created_at'  => now(),
        ]);
    }

    /* -------------------------------------------------------------------------- */
    /* 1. AUTHORIZATION TESTS                                                     */
    /* -------------------------------------------------------------------------- */

    public function test_owner_with_report_view_can_access_sales_report(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->getJson('/cafes/report-cafe-a/reports/sales');

        $response->assertStatus(200);
    }

    public function test_manager_with_report_view_can_access_reports(): void
    {
        $this->actingAs($this->managerA);

        $response = $this->getJson('/cafes/report-cafe-a/reports/sales');

        $response->assertStatus(200);
    }

    public function test_unauthorized_waiter_receives_403_on_reports(): void
    {
        $this->actingAs($this->waiterA);

        $response = $this->getJson('/cafes/report-cafe-a/reports/sales');

        $response->assertStatus(403);
    }

    public function test_tenant_isolation_cross_tenant_report_access_denied(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->getJson('/cafes/report-cafe-b/reports/sales');

        $response->assertStatus(403);
    }

    /* -------------------------------------------------------------------------- */
    /* 2. SALES REPORT CALCULATIONS & CANCELLED ORDERS EXCLUSION                   */
    /* -------------------------------------------------------------------------- */

    public function test_sales_report_totals_and_aov_calculation(): void
    {
        $this->actingAs($this->ownerA);

        // Cafe A has 3 completed orders in date range:
        // Order 1: 108.00
        // Order 2: 236.00
        // Order 3: 55.00
        // Total Sales = 399.00, Total Orders = 3, AOV = 399.00 / 3 = 133.00
        // Cancelled order (590.00) & Old order (330.00) are excluded.
        $response = $this->getJson('/cafes/report-cafe-a/reports/sales?start_date=' . now()->subDays(7)->toDateString() . '&end_date=' . now()->toDateString());

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('summary.total_orders'));
        $this->assertEquals(399.00, $response->json('summary.total_sales'));
        $this->assertEquals(133.00, $response->json('summary.average_order_value'));
    }

    public function test_cancelled_orders_are_excluded_from_sales_report(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->getJson('/cafes/report-cafe-a/reports/sales?start_date=' . now()->subDays(1)->toDateString() . '&end_date=' . now()->toDateString());

        $response->assertStatus(200);
        // The cancelled order total of 590.00 must not be included.
        $this->assertEquals(399.00, $response->json('summary.total_sales'));
    }

    /* -------------------------------------------------------------------------- */
    /* 3. REVENUE BREAKDOWN & PAYMENT METHOD SPLIT                                 */
    /* -------------------------------------------------------------------------- */

    public function test_revenue_report_subtotal_tax_discount_and_net_revenue(): void
    {
        $this->actingAs($this->ownerA);

        // Non-cancelled orders in date range:
        // Order 1: subtotal 100, tax 18, discount 10, total 108
        // Order 2: subtotal 200, tax 36, discount 0, total 236
        // Order 3: subtotal 50, tax 5, discount 0, total 55
        // Gross Subtotal = 350.00, Total Tax = 59.00, Total Discount = 10.00, Net Revenue = 399.00
        $response = $this->getJson('/cafes/report-cafe-a/reports/revenue?start_date=' . now()->subDays(7)->toDateString() . '&end_date=' . now()->toDateString());

        $response->assertStatus(200);
        $this->assertEquals(350.00, $response->json('overview.gross_subtotal'));
        $this->assertEquals(59.00, $response->json('overview.total_tax'));
        $this->assertEquals(10.00, $response->json('overview.total_discount'));
        $this->assertEquals(399.00, $response->json('overview.net_revenue'));
    }

    public function test_revenue_report_payment_methods_breakdown(): void
    {
        $this->actingAs($this->ownerA);

        // Payment 1: upi = 108.00
        // Payment 2: card = 236.00
        // Payment 3: cash = 55.00
        $response = $this->getJson('/cafes/report-cafe-a/reports/revenue?start_date=' . now()->subDays(7)->toDateString() . '&end_date=' . now()->toDateString());

        $response->assertStatus(200);
        $this->assertEquals(55.00, $response->json('payment_methods.cash'));
        $this->assertEquals(108.00, $response->json('payment_methods.upi'));
        $this->assertEquals(236.00, $response->json('payment_methods.card'));
    }

    /* -------------------------------------------------------------------------- */
    /* 4. STAFF PERFORMANCE / OPERATIONAL ACTIVITY                                */
    /* -------------------------------------------------------------------------- */

    public function test_staff_operational_activity_report(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->getJson('/cafes/report-cafe-a/reports/staff?start_date=' . now()->subDays(7)->toDateString() . '&end_date=' . now()->toDateString());

        $response->assertStatus(200)
            ->assertJsonCount(3, 'staff_activity');

        // Manager A has 1 recorded audit log activity
        $managerActivity = collect($response->json('staff_activity'))->firstWhere('name', 'Manager A');
        $this->assertNotNull($managerActivity);
        $this->assertEquals(1, $managerActivity['recorded_activities']);
    }

    /* -------------------------------------------------------------------------- */
    /* 5. BRANCH FILTERING & CROSS-TENANT BRANCH REJECTION                         */
    /* -------------------------------------------------------------------------- */

    public function test_sales_report_branch_filtering(): void
    {
        $this->actingAs($this->ownerA);

        // Filter by Branch A1 only (Orders 1 and 2: total 344.00, count 2)
        $response = $this->getJson('/cafes/report-cafe-a/reports/sales?branch_id=' . $this->branchA1->id . '&start_date=' . now()->subDays(7)->toDateString() . '&end_date=' . now()->toDateString());

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('summary.total_orders'));
        $this->assertEquals(344.00, $response->json('summary.total_sales'));
    }

    public function test_cross_tenant_branch_id_rejection(): void
    {
        $this->actingAs($this->ownerA);

        // Branch B belongs to Cafe B; passing it for Cafe A must fail validation with 422
        $response = $this->getJson('/cafes/report-cafe-a/reports/sales?branch_id=' . $this->branchB->id);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['branch_id']);
    }

    /* -------------------------------------------------------------------------- */
    /* 6. EMPTY DATE RANGE RETURNS ZERO VALUES WITHOUT ERRORS                     */
    /* -------------------------------------------------------------------------- */

    public function test_empty_date_range_returns_zero_metrics_without_errors(): void
    {
        $this->actingAs($this->ownerA);

        // Future date range with 0 orders
        $futureStart = now()->addDays(10)->toDateString();
        $futureEnd   = now()->addDays(15)->toDateString();

        $response = $this->getJson("/cafes/report-cafe-a/reports/sales?start_date={$futureStart}&end_date={$futureEnd}");

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('summary.total_orders'));
        $this->assertEquals(0.00, $response->json('summary.total_sales'));
        $this->assertEquals(0.00, $response->json('summary.average_order_value'));
    }
}
