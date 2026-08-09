<?php

namespace Tests\Feature\Tenant;

use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Role;
use App\Models\User;
use App\Services\DefaultTenantRolesService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class CafeRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_successful_cafe_registration(): void
    {
        $payload = [
            'name' => 'Roast & Brew',
            'slug' => 'roast-and-brew',
            'email' => 'contact@roastbrew.com',
            'phone' => '9876543210',
            'owner_name' => 'Owner Alice',
            'owner_email' => 'alice@roastbrew.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/register-cafe', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Cafe registered successfully.',
                'cafe' => [
                    'name' => 'Roast & Brew',
                    'slug' => 'roast-and-brew',
                ],
                'owner' => [
                    'email' => 'alice@roastbrew.com',
                ],
            ]);

        $this->assertDatabaseHas('cafes', ['slug' => 'roast-and-brew']);
        $this->assertDatabaseHas('users', ['email' => 'alice@roastbrew.com']);
    }

    public function test_invalid_cafe_registration_validation_fails(): void
    {
        $response = $this->postJson('/register-cafe', [
            'name' => '',
            'slug' => 'invalid slug!',
            'email' => 'not-an-email',
            'password' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'slug', 'email', 'owner_name', 'owner_email', 'password']);
    }

    public function test_owner_membership_and_default_branch_created(): void
    {
        $payload = [
            'name' => 'Espresso Hub',
            'slug' => 'espresso-hub',
            'email' => 'info@espressohub.com',
            'owner_name' => 'Owner Bob',
            'owner_email' => 'bob@espressohub.com',
            'password' => 'password123',
        ];

        $this->postJson('/register-cafe', $payload);

        $cafe = Cafe::where('slug', 'espresso-hub')->firstOrFail();
        $user = User::where('email', 'bob@espressohub.com')->firstOrFail();

        $branch = Branch::where('cafe_id', $cafe->id)->where('slug', 'main')->first();
        $this->assertNotNull($branch);
        $this->assertEquals('Main Branch', $branch->name);

        $membership = CafeUser::where('cafe_id', $cafe->id)->where('user_id', $user->id)->first();
        $this->assertNotNull($membership);
        $this->assertEquals($branch->id, $membership->branch_id);

        $role = Role::find($membership->role_id);
        $this->assertNotNull($role);
        $this->assertEquals('cafe-owner', $role->slug);
    }

    public function test_default_roles_created_according_to_authoritative_docs(): void
    {
        $payload = [
            'name' => 'Bean & Leaf',
            'slug' => 'bean-and-leaf',
            'email' => 'contact@beanleaf.com',
            'owner_name' => 'Owner Charlie',
            'owner_email' => 'charlie@beanleaf.com',
            'password' => 'password123',
        ];

        $this->postJson('/register-cafe', $payload);

        $cafe = Cafe::where('slug', 'bean-and-leaf')->firstOrFail();

        $roles = Role::where('cafe_id', $cafe->id)->pluck('slug')->toArray();

        $expectedRoles = ['cafe-owner', 'manager', 'cashier', 'waiter', 'kitchen-staff'];
        foreach ($expectedRoles as $expectedRole) {
            $this->assertContains($expectedRole, $roles);
        }
    }

    public function test_atomic_registration_failure_rolls_back(): void
    {
        $mockRolesService = $this->mock(DefaultTenantRolesService::class);
        $mockRolesService->shouldReceive('createDefaultRolesForCafe')
            ->once()
            ->andThrow(new \Exception('Simulated Database Error'));

        $payload = [
            'name' => 'Rollback Cafe',
            'slug' => 'rollback-cafe',
            'email' => 'info@rollback.com',
            'owner_name' => 'Owner David',
            'owner_email' => 'david@rollback.com',
            'password' => 'password123',
        ];

        try {
            $this->postJson('/register-cafe', $payload);
        } catch (\Exception $e) {
            $this->assertEquals('Simulated Database Error', $e->getMessage());
        }

        $this->assertDatabaseMissing('cafes', ['slug' => 'rollback-cafe']);
        $this->assertDatabaseMissing('users', ['email' => 'david@rollback.com']);
    }
}
