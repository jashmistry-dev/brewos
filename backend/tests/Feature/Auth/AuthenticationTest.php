<?php

namespace Tests\Feature\Auth;

use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_superadmin_can_authenticate_and_is_redirected_to_admin_dashboard(): void
    {
        $this->seed(SuperAdminSeeder::class);

        $response = $this->post('/login', [
            'email' => 'admin@brewos.local',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->isSuperAdmin());
    }

    public function test_cafe_owner_can_authenticate_and_is_redirected_to_tenant_dashboard(): void
    {
        $cafe = Cafe::create([
            'name' => 'Test Cafe',
            'slug' => 'test-cafe',
            'email' => 'contact@testcafe.com',
            'status' => 'active',
        ]);

        $branch = Branch::create([
            'cafe_id' => $cafe->id,
            'name' => 'Main Branch',
            'slug' => 'main',
            'status' => 'active',
        ]);

        $ownerRole = Role::create([
            'cafe_id' => $cafe->id,
            'name' => 'Cafe Owner',
            'slug' => 'cafe-owner',
            'scope' => 'tenant',
        ]);

        $owner = User::create([
            'name' => 'Test Owner',
            'email' => 'owner@testcafe.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        CafeUser::create([
            'cafe_id' => $cafe->id,
            'user_id' => $owner->id,
            'role_id' => $ownerRole->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);

        $response = $this->post('/login', [
            'email' => 'owner@testcafe.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('tenant.dashboard', ['cafe_slug' => 'test-cafe']));
        $this->assertAuthenticatedAs($owner);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::create([
            'name' => 'Test User',
            'email' => 'invalid@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/login', [
            'email' => 'invalid@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertGuest();
    }

    public function test_login_accepts_valid_password_strings_without_an_artificial_minimum_length_restriction(): void
    {
        $user = User::create([
            'name' => 'Short Pass User',
            'email' => 'shortpass@example.com',
            'password' => Hash::make('secret'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/login', [
            'email' => 'shortpass@example.com',
            'password' => 'secret',
        ]);

        $response->assertStatus(200);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rate_limiting_triggers_after_5_attempts(): void
    {
        User::create([
            'name' => 'Test User',
            'email' => 'ratelimit@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', [
                'email' => 'ratelimit@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        $response = $this->postJson('/login', [
            'email' => 'ratelimit@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(429);
    }

    public function test_user_can_logout(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'logout@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully.']);

        $this->assertGuest();
    }

    public function test_role_switching_superadmin_logout_cafeowner_logout_superadmin(): void
    {
        $this->seed(SuperAdminSeeder::class);

        $cafe = Cafe::create([
            'name' => 'Switch Test Cafe',
            'slug' => 'switch-test-cafe',
            'email' => 'switch@testcafe.com',
            'status' => 'active',
        ]);

        $branch = Branch::create([
            'cafe_id' => $cafe->id,
            'name' => 'Main Branch',
            'slug' => 'main',
            'status' => 'active',
        ]);

        $ownerRole = Role::create([
            'cafe_id' => $cafe->id,
            'name' => 'Cafe Owner',
            'slug' => 'cafe-owner',
            'scope' => 'tenant',
        ]);

        $owner = User::create([
            'name' => 'Switch Owner',
            'email' => 'switchowner@testcafe.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        CafeUser::create([
            'cafe_id' => $cafe->id,
            'user_id' => $owner->id,
            'role_id' => $ownerRole->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);

        // Step 1: Login Super Admin
        $res1 = $this->post('/login', [
            'email' => 'admin@brewos.local',
            'password' => 'password',
        ]);
        $res1->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->isSuperAdmin());

        // Step 2: Logout Super Admin
        $res2 = $this->post('/logout');
        $res2->assertRedirect('/login');
        $this->assertGuest();

        // Step 3: Login Cafe Owner
        $res3 = $this->post('/login', [
            'email' => 'switchowner@testcafe.com',
            'password' => 'password',
        ]);
        $res3->assertRedirect(route('tenant.dashboard', ['cafe_slug' => 'switch-test-cafe']));
        $this->assertAuthenticatedAs($owner);

        // Step 4: Logout Cafe Owner
        $res4 = $this->post('/logout');
        $res4->assertRedirect('/login');
        $this->assertGuest();

        // Step 5: Second Super Admin Login
        $res5 = $this->post('/login', [
            'email' => 'admin@brewos.local',
            'password' => 'password',
        ]);
        $res5->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->isSuperAdmin());
    }

    public function test_role_switching_cafeowner_logout_superadmin_logout_cafeowner(): void
    {
        $this->seed(SuperAdminSeeder::class);

        $cafe = Cafe::create([
            'name' => 'Reverse Switch Cafe',
            'slug' => 'reverse-switch-cafe',
            'email' => 'reverse@testcafe.com',
            'status' => 'active',
        ]);

        $branch = Branch::create([
            'cafe_id' => $cafe->id,
            'name' => 'Main Branch',
            'slug' => 'main',
            'status' => 'active',
        ]);

        $ownerRole = Role::create([
            'cafe_id' => $cafe->id,
            'name' => 'Cafe Owner',
            'slug' => 'cafe-owner',
            'scope' => 'tenant',
        ]);

        $owner = User::create([
            'name' => 'Reverse Owner',
            'email' => 'reverseowner@testcafe.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        CafeUser::create([
            'cafe_id' => $cafe->id,
            'user_id' => $owner->id,
            'role_id' => $ownerRole->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);

        // Step 1: Login Cafe Owner
        $res1 = $this->post('/login', [
            'email' => 'reverseowner@testcafe.com',
            'password' => 'password',
        ]);
        $res1->assertRedirect(route('tenant.dashboard', ['cafe_slug' => 'reverse-switch-cafe']));
        $this->assertAuthenticatedAs($owner);

        // Step 2: Logout Cafe Owner
        $res2 = $this->post('/logout');
        $res2->assertRedirect('/login');
        $this->assertGuest();

        // Step 3: Login Super Admin
        $res3 = $this->post('/login', [
            'email' => 'admin@brewos.local',
            'password' => 'password',
        ]);
        $res3->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->isSuperAdmin());

        // Step 4: Logout Super Admin
        $res4 = $this->post('/logout');
        $res4->assertRedirect('/login');
        $this->assertGuest();

        // Step 5: Second Cafe Owner Login
        $res5 = $this->post('/login', [
            'email' => 'reverseowner@testcafe.com',
            'password' => 'password',
        ]);
        $res5->assertRedirect(route('tenant.dashboard', ['cafe_slug' => 'reverse-switch-cafe']));
        $this->assertAuthenticatedAs($owner);
    }
}
