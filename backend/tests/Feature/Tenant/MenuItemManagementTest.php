<?php

namespace Tests\Feature\Tenant;

use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenuItemManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafeA;
    protected Cafe $cafeB;
    protected Category $categoryA;
    protected Category $categoryB;
    protected User $managerA;
    protected User $waiterA;
    protected Role $managerRoleA;
    protected Role $waiterRoleA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Storage::fake('public');

        $this->cafeA = Cafe::create([
            'name' => 'Menu Cafe A',
            'slug' => 'menu-cafe-a',
            'status' => 'active',
        ]);

        $this->cafeB = Cafe::create([
            'name' => 'Menu Cafe B',
            'slug' => 'menu-cafe-b',
            'status' => 'active',
        ]);

        $this->categoryA = Category::create([
            'cafe_id' => $this->cafeA->id,
            'name' => 'Coffee A',
        ]);

        $this->categoryB = Category::create([
            'cafe_id' => $this->cafeB->id,
            'name' => 'Coffee B',
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

        $menuPerms = ['menu.view', 'menu.create', 'menu.update', 'menu.delete'];
        foreach ($menuPerms as $permSlug) {
            $permission = Permission::firstOrCreate(['slug' => $permSlug], ['name' => $permSlug]);
            $this->managerRoleA->permissions()->attach($permission->id);
        }

        $this->managerA = User::create([
            'name' => 'Manager A',
            'email' => 'managerA@menutest.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->waiterA = User::create([
            'name' => 'Waiter A',
            'email' => 'waiterA@menutest.com',
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

    public function test_menu_item_creation_and_listing_with_category_filter(): void
    {
        $this->actingAs($this->managerA);

        $response = $this->postJson('/cafes/menu-cafe-a/menu-items', [
            'category_id' => $this->categoryA->id,
            'name' => 'Espresso Single',
            'description' => 'Rich single shot espresso',
            'price' => 3.50,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Menu item created successfully.',
                'menu_item' => [
                    'name' => 'Espresso Single',
                    'price' => 3.50,
                ],
            ]);

        $listResponse = $this->getJson("/cafes/menu-cafe-a/menu-items?category_id={$this->categoryA->id}");
        $listResponse->assertStatus(200);

        $items = $listResponse->json('menu_items');
        $this->assertCount(1, $items);
        $this->assertEquals('Espresso Single', $items[0]['name']);
    }

    public function test_cross_tenant_category_assignment_denied(): void
    {
        $this->actingAs($this->managerA);

        $response = $this->postJson('/cafes/menu-cafe-a/menu-items', [
            'category_id' => $this->categoryB->id,
            'name' => 'Hacked Item',
            'price' => 5.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_menu_item_availability_toggle(): void
    {
        $this->actingAs($this->managerA);

        $item = MenuItem::create([
            'cafe_id' => $this->cafeA->id,
            'category_id' => $this->categoryA->id,
            'name' => 'Cappuccino',
            'price' => 4.50,
            'status' => 'active',
        ]);

        $toggleResponse = $this->patchJson("/cafes/menu-cafe-a/menu-items/{$item->id}/toggle-availability");
        $toggleResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Menu item availability toggled.',
                'is_available' => false,
            ]);

        $this->assertFalse($item->fresh()->isAvailable());
    }

    public function test_menu_item_soft_deletion(): void
    {
        $this->actingAs($this->managerA);

        $item = MenuItem::create([
            'cafe_id' => $this->cafeA->id,
            'category_id' => $this->categoryA->id,
            'name' => 'Latte',
            'price' => 4.00,
        ]);

        $deleteResponse = $this->deleteJson("/cafes/menu-cafe-a/menu-items/{$item->id}");
        $deleteResponse->assertStatus(200);

        $this->assertSoftDeleted('menu_items', ['id' => $item->id]);
    }

    public function test_menu_item_image_upload_validation_and_secure_storage(): void
    {
        $this->actingAs($this->managerA);

        $invalidFile = UploadedFile::fake()->create('menu.pdf', 500, 'application/pdf');

        $invalidTypeResponse = $this->postJson('/cafes/menu-cafe-a/menu-items', [
            'category_id' => $this->categoryA->id,
            'name' => 'Invalid Image Item',
            'price' => 2.00,
            'image' => $invalidFile,
        ]);

        $invalidTypeResponse->assertStatus(422)
            ->assertJsonValidationErrors(['image']);

        $validImage = UploadedFile::fake()->image('mocha.png', 400, 400);

        $validResponse = $this->postJson('/cafes/menu-cafe-a/menu-items', [
            'category_id' => $this->categoryA->id,
            'name' => 'Caffe Mocha',
            'price' => 5.00,
            'image' => $validImage,
        ]);

        $validResponse->assertStatus(201);
        $imagePath = $validResponse->json('menu_item.image');
        $this->assertNotNull($imagePath);

        $path = str_replace('/storage/', '', $imagePath);
        Storage::disk('public')->assertExists($path);
    }

    public function test_menu_item_authorization_denied_for_unprivileged_user(): void
    {
        $this->actingAs($this->waiterA);

        $response = $this->postJson('/cafes/menu-cafe-a/menu-items', [
            'category_id' => $this->categoryA->id,
            'name' => 'Unauthorized Item',
            'price' => 10.00,
        ]);

        $response->assertStatus(403);
    }

    public function test_cross_tenant_menu_item_update_and_delete_denied(): void
    {
        $itemB = MenuItem::create([
            'cafe_id' => $this->cafeB->id,
            'category_id' => $this->categoryB->id,
            'name' => 'Cafe B Item',
            'price' => 10.00,
        ]);

        $this->actingAs($this->managerA);

        $updateResponse = $this->putJson("/cafes/menu-cafe-a/menu-items/{$itemB->id}", [
            'name' => 'Hacked Item Name',
        ]);
        $updateResponse->assertStatus(404);

        $toggleResponse = $this->patchJson("/cafes/menu-cafe-a/menu-items/{$itemB->id}/toggle-availability");
        $toggleResponse->assertStatus(404);

        $deleteResponse = $this->deleteJson("/cafes/menu-cafe-a/menu-items/{$itemB->id}");
        $deleteResponse->assertStatus(404);
    }

    public function test_cross_tenant_category_reassignment_on_update_denied(): void
    {
        $this->actingAs($this->managerA);

        $itemA = MenuItem::create([
            'cafe_id' => $this->cafeA->id,
            'category_id' => $this->categoryA->id,
            'name' => 'Cafe A Item',
            'price' => 5.00,
        ]);

        $response = $this->putJson("/cafes/menu-cafe-a/menu-items/{$itemA->id}", [
            'category_id' => $this->categoryB->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_menu_item_executable_file_upload_rejected(): void
    {
        $this->actingAs($this->managerA);

        $maliciousFile = UploadedFile::fake()->create('shell.php', 100, 'application/x-php');

        $response = $this->postJson('/cafes/menu-cafe-a/menu-items', [
            'category_id' => $this->categoryA->id,
            'name' => 'Malicious Item',
            'price' => 15.00,
            'image' => $maliciousFile,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);

        Storage::disk('public')->assertMissing('menu-items/shell.php');
    }
}
