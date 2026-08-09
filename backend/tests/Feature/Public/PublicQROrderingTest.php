<?php

namespace Tests\Feature\Public;

use App\Models\Branch;
use App\Models\Cafe;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class PublicQROrderingTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafeA;
    protected Cafe $cafeB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected Category $categoryA;
    protected Category $categoryB;
    protected MenuItem $menuItemA1;
    protected MenuItem $menuItemA2;
    protected MenuItem $menuItemB;
    protected RestaurantTable $tableA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->cafeA = Cafe::create([
            'name' => 'QR Cafe A',
            'slug' => 'qr-cafe-a',
            'status' => 'active',
        ]);

        $this->cafeB = Cafe::create([
            'name' => 'QR Cafe B',
            'slug' => 'qr-cafe-b',
            'status' => 'active',
        ]);

        $this->branchA = Branch::create([
            'cafe_id' => $this->cafeA->id,
            'name' => 'Branch A',
            'slug' => 'branch-a',
            'status' => 'active',
        ]);

        $this->branchB = Branch::create([
            'cafe_id' => $this->cafeB->id,
            'name' => 'Branch B',
            'slug' => 'branch-b',
            'status' => 'active',
        ]);

        $this->categoryA = Category::create([
            'cafe_id' => $this->cafeA->id,
            'name' => 'Beverages A',
            'status' => 'active',
        ]);

        $this->categoryB = Category::create([
            'cafe_id' => $this->cafeB->id,
            'name' => 'Beverages B',
            'status' => 'active',
        ]);

        $this->menuItemA1 = MenuItem::create([
            'cafe_id' => $this->cafeA->id,
            'category_id' => $this->categoryA->id,
            'name' => 'Espresso',
            'price' => 4.00,
            'status' => 'active',
        ]);

        $this->menuItemA2 = MenuItem::create([
            'cafe_id' => $this->cafeA->id,
            'category_id' => $this->categoryA->id,
            'name' => 'Americano',
            'price' => 4.50,
            'status' => 'available',
        ]);

        $this->menuItemB = MenuItem::create([
            'cafe_id' => $this->cafeB->id,
            'category_id' => $this->categoryB->id,
            'name' => 'Cafe B Coffee',
            'price' => 10.00,
            'status' => 'active',
        ]);

        $this->tableA = RestaurantTable::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Table A-1',
            'capacity' => 4,
            'qr_token' => 'valid-qr-token-cafe-a',
        ]);
    }

    public function test_valid_public_qr_menu_retrieval(): void
    {
        $response = $this->getJson("/public/qr/{$this->tableA->qr_token}/menu");

        $response->assertStatus(200)
            ->assertJson([
                'cafe' => [
                    'name' => 'QR Cafe A',
                    'slug' => 'qr-cafe-a',
                ],
                'branch' => [
                    'name' => 'Branch A',
                ],
                'table' => [
                    'name' => 'Table A-1',
                ],
            ]);

        $this->assertNotEmpty($response->json('categories'));
    }

    public function test_invalid_qr_token_returns_404(): void
    {
        $response = $this->getJson('/public/qr/non-existent-token/menu');
        $response->assertStatus(404);
    }

    public function test_public_qr_order_creation_and_server_side_calculation(): void
    {
        $response = $this->postJson("/public/qr/{$this->tableA->qr_token}/orders", [
            'items' => [
                [
                    'menu_item_id' => $this->menuItemA1->id,
                    'quantity' => 2,
                ],
                [
                    'menu_item_id' => $this->menuItemA2->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Order created successfully.',
                'order' => [
                    'status' => 'pending',
                    'payment_status' => 'unpaid',
                    'subtotal' => 12.50, // (4.00 * 2) + (4.50 * 1)
                    'tax' => 0.00,
                    'total' => 12.50,
                ],
            ]);

        $orderNumber = $response->json('order.order_number');
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $orderNumber);
    }

    public function test_cross_tenant_menu_item_submission_rejected(): void
    {
        // Attempting to submit Cafe B's menu item for Cafe A's QR token
        $response = $this->postJson("/public/qr/{$this->tableA->qr_token}/orders", [
            'items' => [
                [
                    'menu_item_id' => $this->menuItemB->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_unavailable_menu_item_submission_rejected(): void
    {
        $unavailableItem = MenuItem::create([
            'cafe_id' => $this->cafeA->id,
            'category_id' => $this->categoryA->id,
            'name' => 'Out of Stock Coffee',
            'price' => 5.00,
            'status' => 'unavailable',
        ]);

        $response = $this->postJson("/public/qr/{$this->tableA->qr_token}/orders", [
            'items' => [
                [
                    'menu_item_id' => $unavailableItem->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_server_side_price_snapshotting(): void
    {
        $response = $this->postJson("/public/qr/{$this->tableA->qr_token}/orders", [
            'items' => [
                [
                    'menu_item_id' => $this->menuItemA1->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $unitPrice = $response->json('order.items.0.unit_price');
        $this->assertEquals(4.00, $unitPrice);

        // Mutating the menu item price afterwards should not affect the order item price
        $this->menuItemA1->update(['price' => 99.99]);

        $orderId = $response->json('order.id');
        $order = \App\Models\Order::with('orderItems')->find($orderId);
        $this->assertEquals(4.00, (float) $order->orderItems->first()->unit_price);
    }

    public function test_public_qr_rate_limiting_enforces_60_requests_per_minute(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $response = $this->getJson("/public/qr/{$this->tableA->qr_token}/menu");
            $response->assertStatus(200);
        }

        $rateLimitedResponse = $this->getJson("/public/qr/{$this->tableA->qr_token}/menu");
        $rateLimitedResponse->assertStatus(429);
    }

    public function test_first_order_of_day_and_subsequent_order_numbers(): void
    {
        $datePrefix = date('Ymd');

        $response1 = $this->postJson("/public/qr/{$this->tableA->qr_token}/orders", [
            'items' => [['menu_item_id' => $this->menuItemA1->id, 'quantity' => 1]],
        ]);
        $response1->assertStatus(201);
        $this->assertEquals("ORD-{$datePrefix}-0001", $response1->json('order.order_number'));

        $response2 = $this->postJson("/public/qr/{$this->tableA->qr_token}/orders", [
            'items' => [['menu_item_id' => $this->menuItemA1->id, 'quantity' => 2]],
        ]);
        $response2->assertStatus(201);
        $this->assertEquals("ORD-{$datePrefix}-0002", $response2->json('order.order_number'));
    }
}
