<?php

namespace Tests\Feature\Tenant;

use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafeA;
    protected Cafe $cafeB;
    protected User $managerA;
    protected User $waiterA;
    protected Role $managerRoleA;
    protected Role $waiterRoleA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->cafeA = Cafe::create([
            'name' => 'Category Cafe A',
            'slug' => 'cat-cafe-a',
            'status' => 'active',
        ]);

        $this->cafeB = Cafe::create([
            'name' => 'Category Cafe B',
            'slug' => 'cat-cafe-b',
            'status' => 'active',
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

        $categoryPerms = ['category.view', 'category.create', 'category.update', 'category.delete'];
        foreach ($categoryPerms as $permSlug) {
            $permission = Permission::firstOrCreate(['slug' => $permSlug], ['name' => $permSlug]);
            $this->managerRoleA->permissions()->attach($permission->id);
        }

        $this->managerA = User::create([
            'name' => 'Manager A',
            'email' => 'managerA@cattest.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->waiterA = User::create([
            'name' => 'Waiter A',
            'email' => 'waiterA@cattest.com',
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
    }

    public function test_category_creation_and_sorting(): void
    {
        $this->actingAs($this->managerA);

        $response = $this->postJson('/cafes/cat-cafe-a/categories', [
            'name' => 'Hot Beverages',
            'description' => 'Freshly brewed espresso and coffee drinks',
            'sort_order' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Category created successfully.',
                'category' => [
                    'name' => 'Hot Beverages',
                    'description' => 'Freshly brewed espresso and coffee drinks',
                    'sort_order' => 1,
                ],
            ]);

        $this->assertDatabaseHas('categories', [
            'cafe_id' => $this->cafeA->id,
            'name' => 'Hot Beverages',
        ]);
    }

    public function test_category_update_and_deletion(): void
    {
        $this->actingAs($this->managerA);

        $category = Category::create([
            'cafe_id' => $this->cafeA->id,
            'name' => 'Cold Brews',
            'description' => 'Chilled coffee',
            'sort_order' => 2,
            'status' => 'active',
        ]);

        $updateResponse = $this->putJson("/cafes/cat-cafe-a/categories/{$category->id}", [
            'name' => 'Cold Drinks',
            'sort_order' => 5,
        ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Category updated successfully.',
                'category' => [
                    'name' => 'Cold Drinks',
                    'sort_order' => 5,
                ],
            ]);

        $deleteResponse = $this->deleteJson("/cafes/cat-cafe-a/categories/{$category->id}");
        $deleteResponse->assertStatus(200);

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_category_tenant_isolation(): void
    {
        Category::create([
            'cafe_id' => $this->cafeA->id,
            'name' => 'Cafe A Category',
        ]);

        Category::create([
            'cafe_id' => $this->cafeB->id,
            'name' => 'Cafe B Category',
        ]);

        $this->actingAs($this->managerA);

        $response = $this->getJson('/cafes/cat-cafe-a/categories');
        $response->assertStatus(200);

        $names = array_column($response->json('categories'), 'name');
        $this->assertContains('Cafe A Category', $names);
        $this->assertNotContains('Cafe B Category', $names);
    }

    public function test_category_authorization_denied_for_unprivileged_user(): void
    {
        $this->actingAs($this->waiterA);

        $response = $this->postJson('/cafes/cat-cafe-a/categories', [
            'name' => 'Unauthorized Category',
        ]);

        $response->assertStatus(403);
    }

    public function test_cross_tenant_category_update_and_delete_denied(): void
    {
        $categoryB = Category::create([
            'cafe_id' => $this->cafeB->id,
            'name' => 'Cafe B Category',
        ]);

        $this->actingAs($this->managerA);

        $updateResponse = $this->putJson("/cafes/cat-cafe-a/categories/{$categoryB->id}", [
            'name' => 'Hacked Category Name',
        ]);
        $updateResponse->assertStatus(404);

        $deleteResponse = $this->deleteJson("/cafes/cat-cafe-a/categories/{$categoryB->id}");
        $deleteResponse->assertStatus(404);
    }
}
