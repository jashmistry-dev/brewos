<?php

namespace Tests\Feature\Tenant;

use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FrontendMenuManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafe;
    protected User $owner;
    protected User $unauthorizedUser;
    protected Role $ownerRole;
    protected Role $unauthorizedRole;
    protected Category $category;
    protected MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->cafe = Cafe::create([
            'name' => 'Menu Cafe',
            'slug' => 'menu-cafe',
            'email' => 'menu@cafe.com',
            'status' => 'active',
        ]);

        $this->owner = User::create([
            'name' => 'Menu Owner',
            'email' => 'menu-owner@cafe.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->unauthorizedUser = User::create([
            'name' => 'Unprivileged User',
            'email' => 'unprivileged@cafe.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->ownerRole = Role::create([
            'cafe_id' => $this->cafe->id,
            'name' => 'Cafe Owner',
            'slug' => 'cafe-owner',
            'scope' => 'tenant',
        ]);

        $this->unauthorizedRole = Role::create([
            'cafe_id' => $this->cafe->id,
            'name' => 'Waiter',
            'slug' => 'waiter',
            'scope' => 'tenant',
        ]);

        $permissions = [
            'category.view',
            'category.create',
            'category.update',
            'category.delete',
            'menu.view',
            'menu.create',
            'menu.update',
            'menu.delete',
        ];

        foreach ($permissions as $permSlug) {
            $perm = Permission::firstOrCreate(['slug' => $permSlug], ['name' => ucfirst(str_replace('.', ' ', $permSlug))]);
            $this->ownerRole->permissions()->syncWithoutDetaching([$perm->id]);
        }

        CafeUser::create([
            'cafe_id' => $this->cafe->id,
            'user_id' => $this->owner->id,
            'role_id' => $this->ownerRole->id,
            'status' => 'active',
        ]);

        CafeUser::create([
            'cafe_id' => $this->cafe->id,
            'user_id' => $this->unauthorizedUser->id,
            'role_id' => $this->unauthorizedRole->id,
            'status' => 'active',
        ]);

        $this->category = Category::create([
            'cafe_id' => $this->cafe->id,
            'name' => 'Beverages',
            'sort_order' => 1,
            'status' => 'active',
        ]);

        $this->menuItem = MenuItem::create([
            'cafe_id' => $this->cafe->id,
            'category_id' => $this->category->id,
            'name' => 'Espresso',
            'price' => 150.00,
            'status' => 'active',
            'sort_order' => 1,
        ]);
    }

    public function test_categories_inertia_page_renders_successfully(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get('/cafes/menu-cafe/categories');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Categories')
                ->has('categories', 1)
                ->where('categories.0.name', 'Beverages')
            );
    }

    public function test_menu_items_inertia_page_renders_successfully(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get('/cafes/menu-cafe/menu-items');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/MenuItems')
                ->has('menu_items', 1)
                ->has('categories', 1)
                ->where('menu_items.0.name', 'Espresso')
            );
    }

    public function test_unauthorized_user_denied_on_category_management(): void
    {
        $this->actingAs($this->unauthorizedUser);

        $response = $this->postJson('/cafes/menu-cafe/categories', [
            'name' => 'Deserts',
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_denied_on_menu_management(): void
    {
        $this->actingAs($this->unauthorizedUser);

        $response = $this->postJson('/cafes/menu-cafe/menu-items', [
            'category_id' => $this->category->id,
            'name' => 'Latte',
            'price' => 200,
        ]);

        $response->assertStatus(403);
    }

    public function test_existing_api_json_behavior_remains_functional_for_categories(): void
    {
        $this->actingAs($this->owner);

        $response = $this->getJson('/cafes/menu-cafe/categories');

        $response->assertStatus(200)
            ->assertJson([
                'categories' => [
                    [
                        'id' => $this->category->id,
                        'name' => 'Beverages',
                    ],
                ],
            ]);
    }

    public function test_existing_api_json_behavior_remains_functional_for_menu_items(): void
    {
        $this->actingAs($this->owner);

        $response = $this->getJson('/cafes/menu-cafe/menu-items');

        $response->assertStatus(200)
            ->assertJson([
                'menu_items' => [
                    [
                        'id' => $this->menuItem->id,
                        'name' => 'Espresso',
                    ],
                ],
            ]);
    }

    public function test_availability_toggle_remains_functional(): void
    {
        $this->actingAs($this->owner);

        $response = $this->patchJson("/cafes/menu-cafe/menu-items/{$this->menuItem->id}/toggle-availability");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Menu item availability toggled.',
                'status' => 'unavailable',
                'is_available' => false,
            ]);
    }
}
