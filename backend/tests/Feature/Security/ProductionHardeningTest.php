<?php

namespace Tests\Feature\Security;

use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafe;
    protected User $owner;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->cafe = Cafe::create([
            'name'   => 'Hardening Test Cafe',
            'slug'   => 'hardening-test-cafe',
            'email'  => 'hardening@cafe.com',
            'status' => 'active',
        ]);

        $this->owner = User::create([
            'name'     => 'Owner User',
            'email'    => 'owner@hardening.com',
            'password' => Hash::make('password123'),
            'status'   => 'active',
        ]);

        $ownerRole = Role::create([
            'cafe_id' => $this->cafe->id,
            'name'    => 'Cafe Owner',
            'slug'    => 'cafe-owner',
            'scope'   => 'tenant',
        ]);

        $menuPerms = ['menu.view', 'menu.create', 'menu.update', 'menu.delete'];
        foreach ($menuPerms as $permSlug) {
            $permission = \App\Models\Permission::firstOrCreate(['slug' => $permSlug], ['name' => $permSlug]);
            $ownerRole->permissions()->attach($permission->id);
        }

        CafeUser::create([
            'cafe_id' => $this->cafe->id,
            'user_id' => $this->owner->id,
            'role_id' => $ownerRole->id,
            'status'  => 'active',
        ]);

        $this->category = Category::create([
            'cafe_id'    => $this->cafe->id,
            'name'       => 'Espresso',
            'slug'       => 'espresso',
            'sort_order' => 1,
        ]);
    }

    public function test_security_headers_are_present_on_web_and_api_responses(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_menu_item_image_upload_uses_configured_storage_disk(): void
    {
        Storage::fake('custom_test_disk');
        config(['filesystems.uploads_disk' => 'custom_test_disk']);

        $this->actingAs($this->owner);

        $file = UploadedFile::fake()->image('coffee.jpg', 300, 300);

        $response = $this->postJson("/cafes/{$this->cafe->slug}/menu-items", [
            'category_id' => $this->category->id,
            'name'        => 'Custom Disk Latte',
            'price'       => 4.50,
            'image'       => $file,
            'status'      => 'active',
        ]);

        $response->assertStatus(201);
        Storage::disk('custom_test_disk')->assertExists('menu-items/'.$file->hashName());
    }

    public function test_billing_webhook_rate_limiting_enforces_limit(): void
    {
        config(['app.env' => 'production']);
        config(['services.stripe.webhook_secret' => 'whsec_test_secret_key_12345']);

        // Send requests up to throttle threshold
        for ($i = 0; $i < 60; $i++) {
            $this->postJson('/api/webhooks/billing/stripe', [
                'event_id' => "evt_rate_limit_{$i}",
            ]);
        }

        // 61st request should be rate limited with HTTP 429
        $response = $this->postJson('/api/webhooks/billing/stripe', [
            'event_id' => 'evt_rate_limit_61',
        ]);

        $response->assertStatus(429);
    }
}
