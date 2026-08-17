<?php

namespace Tests\Feature\Tenant;

use App\Models\Cafe;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CafeRegistrationHardeningTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        Plan::create([
            'name'             => 'Starter Plan',
            'slug'             => 'starter',
            'price'            => 29.99,
            'billing_interval' => 'monthly',
            'status'           => 'active',
        ]);
    }

    public function test_cafe_registration_requires_password_confirmation(): void
    {
        $response = $this->postJson('/register-cafe', [
            'name'                  => 'Mismatch Cafe',
            'slug'                  => 'mismatch-cafe',
            'owner_name'            => 'Jane Owner',
            'owner_email'           => 'jane_mismatch@test.com',
            'password'              => 'SecretPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_cafe_registration_normalizes_email_and_assigns_14_day_free_trial(): void
    {
        $response = $this->postJson('/register-cafe', [
            'name'                  => 'Artisan Roastery',
            'slug'                  => 'artisan-roastery',
            'owner_name'            => 'Jane Roaster',
            'owner_email'           => '  JANE_ROASTER@TEST.COM ',
            'password'              => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('owner.email', 'jane_roaster@test.com')
            ->assertJsonPath('subscription.status', 'trialing');

        $user = User::where('email', 'jane_roaster@test.com')->first();
        $this->assertNotNull($user);

        $cafe = Cafe::where('slug', 'artisan-roastery')->first();
        $this->assertNotNull($cafe);

        $sub = Subscription::where('cafe_id', $cafe->id)->first();
        $this->assertNotNull($sub);
        $this->assertEquals('trialing', $sub->status);
        $this->assertEquals(14, round(now()->diffInDays($sub->trial_ends_at)));

        // Verify login succeeds with lowercased email
        $loginResponse = $this->postJson('/login', [
            'email'    => 'jane_roaster@test.com',
            'password' => 'SecurePassword123!',
        ]);

        $loginResponse->assertStatus(200);
    }
}
