<?php

namespace Tests\Feature\Public;

use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Category;
use App\Models\CustomerRequest;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderingSession;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use App\Services\DefaultTenantRolesService;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicQROrderingTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafe;
    protected Branch $branch;
    protected RestaurantTable $table;
    protected MenuItem $menuItem1;
    protected MenuItem $menuItem2;
    protected User $ownerUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->seed(SuperAdminSeeder::class);

        // Create Cafe with QR ordering enabled
        $this->cafe = Cafe::create([
            'name'                           => 'QR Diner Cafe',
            'slug'                           => 'qr-diner-cafe',
            'email'                          => 'owner@qrdiner.com',
            'status'                         => 'active',
            'qr_ordering_enabled'            => true,
            'require_location'               => false,
            'pay_at_counter_enabled'         => true,
            'require_payment_before_kitchen' => true,
            'call_staff_enabled'             => true,
            'request_bill_enabled'           => true,
            'tax_rate'                       => 5.0,
        ]);

        $this->branch = Branch::create([
            'cafe_id' => $this->cafe->id,
            'name'    => 'Main Outlet',
            'slug'    => 'main',
            'status'  => 'active',
        ]);

        $this->table = RestaurantTable::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Table 07',
            'capacity'  => 4,
            'status'    => 'available',
            'qr_token'  => RestaurantTable::generateQrToken(),
        ]);

        $rolesService = app(DefaultTenantRolesService::class);
        $roles = $rolesService->createDefaultRolesForCafe($this->cafe);
        $ownerRole = $roles['cafe-owner'];

        $this->ownerUser = User::create([
            'name'     => 'QR Cafe Owner',
            'email'    => 'owner@qrdiner.com',
            'password' => Hash::make('password'),
            'status'   => 'active',
        ]);

        CafeUser::create([
            'cafe_id'   => $this->cafe->id,
            'user_id'   => $this->ownerUser->id,
            'role_id'   => $ownerRole->id,
            'branch_id' => $this->branch->id,
            'status'    => 'active',
        ]);

        $category = Category::create([
            'cafe_id'    => $this->cafe->id,
            'name'       => 'Hot Drinks',
            'slug'       => 'hot-drinks',
            'sort_order' => 1,
        ]);

        $this->menuItem1 = MenuItem::create([
            'cafe_id'     => $this->cafe->id,
            'category_id' => $category->id,
            'name'        => 'Espresso Double',
            'price'       => '4.00',
            'status'      => 'active',
        ]);

        $this->menuItem2 = MenuItem::create([
            'cafe_id'     => $this->cafe->id,
            'category_id' => $category->id,
            'name'        => 'Cheesecake',
            'price'       => '6.00',
            'status'      => 'active',
        ]);
    }

    public function test_public_qr_token_resolves_table_and_creates_ordering_session(): void
    {
        $response = $this->get("/order/c/{$this->cafe->slug}/t/{$this->table->qr_token}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('ordering_sessions', [
            'cafe_id'       => $this->cafe->id,
            'table_id'      => $this->table->id,
            'qr_token_used' => $this->table->qr_token,
            'status'        => 'active',
        ]);
    }

    public function test_invalid_qr_token_is_rejected_with_422(): void
    {
        $response = $this->getJson('/order/c/' . $this->cafe->slug . '/t/invalid-fake-qr-token');

        $response->assertStatus(422)
            ->assertJsonPath('errors.qr_token.0', 'Invalid or expired QR code.');
    }

    public function test_customer_can_submit_order_with_pay_at_counter_flow(): void
    {
        $session = OrderingSession::create([
            'cafe_id'       => $this->cafe->id,
            'branch_id'     => $this->branch->id,
            'table_id'      => $this->table->id,
            'session_token' => OrderingSession::generateToken(),
            'qr_token_used' => $this->table->qr_token,
            'status'        => 'active',
            'expires_at'    => now()->addHours(2),
        ]);

        $orderResponse = $this->postJson('/order/submit', [
            'session_token'  => $session->session_token,
            'payment_method' => 'pay_at_counter',
            'customer_notes' => 'Please bring extra napkins',
            'items'          => [
                ['menu_item_id' => $this->menuItem1->id, 'quantity' => 2], // 2 x $4.00 = $8.00
                ['menu_item_id' => $this->menuItem2->id, 'quantity' => 1], // 1 x $6.00 = $6.00
            ],
        ]);

        $orderResponse->assertStatus(200)
            ->assertJsonPath('order.status', 'payment_pending')
            ->assertJsonPath('order.payment_status', 'pending_counter_confirmation');

        $orderId = $orderResponse->json('order.id');

        $this->assertDatabaseHas('orders', [
            'id'             => $orderId,
            'table_id'       => $this->table->id,
            'subtotal'       => 14.00,
            'tax'            => 0.70, // 5% tax
            'total'          => 14.70,
            'payment_status' => 'pending_counter_confirmation',
        ]);

        // Kitchen Display must NOT show unpaid order when require_payment_before_kitchen is true
        $kitchenResBefore = $this->actingAs($this->ownerUser)
            ->getJson("/cafes/{$this->cafe->slug}/kitchen-display");

        $kitchenResBefore->assertStatus(200);
        $this->assertCount(0, $kitchenResBefore->json('orders'));

        // Cashier confirms payment
        $confirmRes = $this->actingAs($this->ownerUser)
            ->postJson("/cafes/{$this->cafe->slug}/orders/{$orderId}/confirm-payment", [
                'payment_method' => 'cash',
            ]);

        $confirmRes->assertStatus(200)
            ->assertJsonPath('order.status', 'kitchen_pending')
            ->assertJsonPath('order.payment_status', 'paid');

        // Kitchen Display NOW receives the paid order!
        $kitchenResAfter = $this->actingAs($this->ownerUser)
            ->getJson("/cafes/{$this->cafe->slug}/kitchen-display");

        $kitchenResAfter->assertStatus(200);
        $this->assertCount(1, $kitchenResAfter->json('orders'));
        $this->assertEquals($orderResponse->json('order.order_number'), $kitchenResAfter->json('orders.0.order_number'));
    }

    public function test_customer_can_send_table_request_like_call_staff_or_request_bill(): void
    {
        $session = OrderingSession::create([
            'cafe_id'       => $this->cafe->id,
            'branch_id'     => $this->branch->id,
            'table_id'      => $this->table->id,
            'session_token' => OrderingSession::generateToken(),
            'qr_token_used' => $this->table->qr_token,
            'status'        => 'active',
            'expires_at'    => now()->addHours(2),
        ]);

        $reqRes = $this->postJson('/order/request', [
            'session_token' => $session->session_token,
            'request_type'  => 'call_staff',
            'notes'         => 'Water refilled please',
        ]);

        $reqRes->assertStatus(200)
            ->assertJsonPath('request.request_type', 'call_staff')
            ->assertJsonPath('request.status', 'pending');

        $requestId = $reqRes->json('request.id');

        // Cashier checks active customer requests
        $staffRequestsRes = $this->actingAs($this->ownerUser)
            ->getJson("/cafes/{$this->cafe->slug}/customer-requests");

        $staffRequestsRes->assertStatus(200);
        $this->assertCount(1, $staffRequestsRes->json('customer_requests'));

        // Cashier acknowledges customer request
        $ackRes = $this->actingAs($this->ownerUser)
            ->patchJson("/cafes/{$this->cafe->slug}/customer-requests/{$requestId}/acknowledge", [
                'status' => 'completed',
            ]);

        $ackRes->assertStatus(200)
            ->assertJsonPath('request.status', 'completed');
    }

    public function test_customer_service_requests_all_three_types(): void
    {
        $session = OrderingSession::create([
            'cafe_id'       => $this->cafe->id,
            'branch_id'     => $this->branch->id,
            'table_id'      => $this->table->id,
            'session_token' => OrderingSession::generateToken(),
            'qr_token_used' => $this->table->qr_token,
            'status'        => 'active',
            'expires_at'    => now()->addHours(2),
        ]);

        $types = ['call_staff', 'water', 'request_bill'];

        foreach ($types as $type) {
            $res = $this->postJson('/order/request', [
                'session_token' => $session->session_token,
                'request_type'  => $type,
            ]);

            $res->assertStatus(200)
                ->assertJsonPath('request.request_type', $type)
                ->assertJsonPath('request.status', 'pending');
        }

        $this->assertEquals(3, \App\Models\CustomerRequest::where('cafe_id', $this->cafe->id)->count());
    }

    public function test_public_qr_order_submission_with_csrf_header_and_body_token(): void
    {
        $session = OrderingSession::create([
            'cafe_id'       => $this->cafe->id,
            'branch_id'     => $this->branch->id,
            'table_id'      => $this->table->id,
            'session_token' => OrderingSession::generateToken(),
            'qr_token_used' => $this->table->qr_token,
            'status'        => 'active',
            'expires_at'    => now()->addHours(2),
        ]);

        $res = $this->withHeaders(['X-CSRF-TOKEN' => 'test-csrf-token'])
            ->postJson('/order/submit', [
                '_token'        => 'test-csrf-token',
                'session_token' => $session->session_token,
                'payment_method' => 'pay_at_counter',
                'items' => [
                    ['menu_item_id' => $this->menuItem1->id, 'quantity' => 2],
                ],
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('order.status', 'payment_pending')
            ->assertJsonPath('order.payment_status', 'pending_counter_confirmation');
    }

    public function test_table_qr_regeneration_invalidates_old_token(): void
    {
        $oldToken = $this->table->qr_token;

        $regenRes = $this->actingAs($this->ownerUser)
            ->postJson("/cafes/{$this->cafe->slug}/tables/{$this->table->id}/regenerate-qr");

        $regenRes->assertStatus(200);
        $newToken = $regenRes->json('qr_token');

        $this->assertNotEquals($oldToken, $newToken);

        // Old QR token is rejected
        $oldScan = $this->getJson("/order/c/{$this->cafe->slug}/t/{$oldToken}");
        $oldScan->assertStatus(422);

        // New QR token succeeds
        $newScan = $this->get("/order/c/{$this->cafe->slug}/t/{$newToken}");
        $newScan->assertStatus(200);
    }
}
