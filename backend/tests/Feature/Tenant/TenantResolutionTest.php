<?php

namespace Tests\Feature\Tenant;

use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Role;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantResolutionTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafeA;
    protected Cafe $cafeB;
    protected User $userA;
    protected User $userB;
    protected Role $roleA;
    protected Role $roleB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cafeA = Cafe::create([
            'name' => 'Cafe Alpha',
            'slug' => 'cafe-alpha',
            'status' => 'active',
        ]);

        $this->cafeB = Cafe::create([
            'name' => 'Cafe Beta',
            'slug' => 'cafe-beta',
            'status' => 'active',
        ]);

        $this->userA = User::create([
            'name' => 'Alice',
            'email' => 'alice@alpha.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->userB = User::create([
            'name' => 'Bob',
            'email' => 'bob@beta.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->roleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name' => 'Cafe Owner',
            'slug' => 'cafe-owner',
            'scope' => 'tenant',
        ]);

        $this->roleB = Role::create([
            'cafe_id' => $this->cafeB->id,
            'name' => 'Cafe Owner',
            'slug' => 'cafe-owner',
            'scope' => 'tenant',
        ]);

        CafeUser::create([
            'cafe_id' => $this->cafeA->id,
            'user_id' => $this->userA->id,
            'role_id' => $this->roleA->id,
            'status' => 'active',
        ]);

        CafeUser::create([
            'cafe_id' => $this->cafeB->id,
            'user_id' => $this->userB->id,
            'role_id' => $this->roleB->id,
            'status' => 'active',
        ]);
    }

    public function test_tenant_is_resolved_by_valid_url_slug(): void
    {
        $this->actingAs($this->userA);

        $response = $this->getJson('/cafes/cafe-alpha/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Tenant dashboard loaded.',
                'cafe' => [
                    'id' => $this->cafeA->id,
                    'name' => 'Cafe Alpha',
                    'slug' => 'cafe-alpha',
                ],
            ]);
    }

    public function test_user_can_access_own_cafe(): void
    {
        $this->actingAs($this->userA);

        $response = $this->getJson('/cafes/cafe-alpha/dashboard');

        $response->assertStatus(200);
    }

    public function test_user_cannot_access_other_cafe_denied_with_403(): void
    {
        $this->actingAs($this->userA);

        $response = $this->getJson('/cafes/cafe-beta/dashboard');

        $response->assertStatus(403);
    }

    public function test_tenant_scope_restricts_queries_to_active_cafe(): void
    {
        Branch::create([
            'cafe_id' => $this->cafeA->id,
            'name' => 'Main Branch Alpha',
            'slug' => 'main-alpha',
            'status' => 'active',
        ]);

        Branch::create([
            'cafe_id' => $this->cafeB->id,
            'name' => 'Main Branch Beta',
            'slug' => 'main-beta',
            'status' => 'active',
        ]);

        /** @var TenantContext $tenantContext */
        $tenantContext = app(TenantContext::class);

        $tenantContext->setCafe($this->cafeA);

        $branches = Branch::all();

        $this->assertCount(1, $branches);
        $this->assertEquals('Main Branch Alpha', $branches->first()->name);

        $tenantContext->clear();
    }
}
