<?php

namespace Tests\Feature\Tenant;

use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Permission;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafeA;
    protected Cafe $cafeB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected RestaurantTable $tableA;
    protected RestaurantTable $tableB;
    protected Category $categoryA;
    protected MenuItem $menuItemA;
    protected User $managerA;
    protected User $waiterA;
    protected Role $managerRoleA;
    protected Role $waiterRoleA;
    protected Order $orderA;
    protected Order $orderB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->cafeA = Cafe::create([
            'name' => 'Order Cafe A',
            'slug' => 'order-cafe-a',
            'status' => 'active',
        ]);

        $this->cafeB = Cafe::create([
            'name' => 'Order Cafe B',
            'slug' => 'order-cafe-b',
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

        $this->tableA = RestaurantTable::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Table A',
            'qr_token' => 'table-token-a',
        ]);

        $this->tableB = RestaurantTable::create([
            'branch_id' => $this->branchB->id,
            'name' => 'Table B',
            'qr_token' => 'table-token-b',
        ]);

        $this->categoryA = Category::create([
            'cafe_id' => $this->cafeA->id,
            'name' => 'Hot Coffee',
        ]);

        $this->menuItemA = MenuItem::create([
            'cafe_id' => $this->cafeA->id,
            'category_id' => $this->categoryA->id,
            'name' => 'Latte',
            'price' => 5.00,
        ]);

        $this->managerRoleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name' => 'Manager A',
            'slug' => 'manager',
            'scope' => 'tenant',
        ]);

        $this->waiterRoleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name' => 'Waiter A',
            'slug' => 'waiter',
            'scope' => 'tenant',
        ]);

        $orderPerms = ['order.view', 'order.create', 'order.update', 'order.delete'];
        foreach ($orderPerms as $permSlug) {
            $permission = Permission::firstOrCreate(['slug' => $permSlug], ['name' => $permSlug]);
            $this->managerRoleA->permissions()->attach($permission->id);
        }

        $this->managerA = User::create([
            'name' => 'Manager A',
            'email' => 'managerA@ordertest.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->waiterA = User::create([
            'name' => 'Waiter A',
            'email' => 'waiterA@ordertest.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        CafeUser::create([
            'cafe_id' => $this->cafeA->id,
            'user_id' => $this->managerA->id,
            'role_id' => $this->managerRoleA->id,
            'status' => 'active',
        ]);

        CafeUser::create([
            'cafe_id' => $this->cafeA->id,
            'user_id' => $this->waiterA->id,
            'role_id' => $this->waiterRoleA->id,
            'status' => 'active',
        ]);

        $this->orderA = Order::create([
            'cafe_id' => $this->cafeA->id,
            'branch_id' => $this->branchA->id,
            'table_id' => $this->tableA->id,
            'order_number' => 'ORD-20260809-0001',
            'status' => 'pending',
            'subtotal' => 10.00,
            'tax' => 0.00,
            'total' => 10.00,
        ]);

        $this->orderB = Order::create([
            'cafe_id' => $this->cafeB->id,
            'branch_id' => $this->branchB->id,
            'table_id' => $this->tableB->id,
            'order_number' => 'ORD-20260809-0002',
            'status' => 'pending',
            'subtotal' => 20.00,
            'tax' => 0.00,
            'total' => 20.00,
        ]);
    }

    public function test_order_listing_and_filtering_by_status(): void
    {
        $this->actingAs($this->managerA);

        $response = $this->getJson('/cafes/order-cafe-a/orders?status=pending');

        $response->assertStatus(200);
        $orderNumbers = array_column($response->json('orders'), 'order_number');

        $this->assertContains('ORD-20260809-0001', $orderNumbers);
        $this->assertNotContains('ORD-20260809-0002', $orderNumbers);
    }

    public function test_order_status_update(): void
    {
        $this->actingAs($this->managerA);

        $response = $this->patchJson("/cafes/order-cafe-a/orders/{$this->orderA->id}/status", [
            'status' => 'preparing',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Order status updated successfully.',
                'order' => [
                    'status' => 'preparing',
                ],
            ]);

        $this->assertEquals('preparing', $this->orderA->fresh()->status);
    }

    public function test_invalid_order_status_update_rejected(): void
    {
        $this->actingAs($this->managerA);

        $response = $this->patchJson("/cafes/order-cafe-a/orders/{$this->orderA->id}/status", [
            'status' => 'invalid_status_value',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_kitchen_display_lists_active_orders(): void
    {
        $this->actingAs($this->managerA);

        $response = $this->getJson('/cafes/order-cafe-a/kitchen-display');

        $response->assertStatus(200);
        $orderNumbers = array_column($response->json('orders'), 'order_number');

        $this->assertContains('ORD-20260809-0001', $orderNumbers);
    }

    public function test_cross_tenant_order_access_denied(): void
    {
        $this->actingAs($this->managerA);

        $showResponse = $this->getJson("/cafes/order-cafe-a/orders/{$this->orderB->id}");
        $showResponse->assertStatus(404);

        $updateResponse = $this->patchJson("/cafes/order-cafe-a/orders/{$this->orderB->id}/status", [
            'status' => 'served',
        ]);
        $updateResponse->assertStatus(404);
    }

    public function test_order_authorization_denied_for_unprivileged_user(): void
    {
        $this->actingAs($this->waiterA);

        $response = $this->patchJson("/cafes/order-cafe-a/orders/{$this->orderA->id}/status", [
            'status' => 'served',
        ]);

        $response->assertStatus(403);
    }
}
