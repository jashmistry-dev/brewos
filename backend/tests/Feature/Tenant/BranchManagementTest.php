<?php

namespace Tests\Feature\Tenant;

use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafeA;
    protected Cafe $cafeB;
    protected User $ownerA;
    protected User $waiterA;
    protected Role $ownerRoleA;
    protected Role $waiterRoleA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->cafeA = Cafe::create([
            'name' => 'Branch Cafe A',
            'slug' => 'branch-cafe-a',
            'status' => 'active',
        ]);

        $this->cafeB = Cafe::create([
            'name' => 'Branch Cafe B',
            'slug' => 'branch-cafe-b',
            'status' => 'active',
        ]);

        $this->ownerRoleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name' => 'Cafe Owner',
            'slug' => 'cafe-owner',
            'scope' => 'tenant',
        ]);

        $this->waiterRoleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name' => 'Waiter',
            'slug' => 'waiter',
            'scope' => 'tenant',
        ]);

        $branchPerms = ['cafe.view', 'cafe.settings.update', 'branch.view', 'branch.create', 'branch.update'];
        foreach ($branchPerms as $permSlug) {
            $permission = Permission::firstOrCreate(['slug' => $permSlug], ['name' => $permSlug]);
            $this->ownerRoleA->permissions()->attach($permission->id);
        }

        $this->ownerA = User::create([
            'name' => 'Owner A',
            'email' => 'ownerA@branchtest.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->waiterA = User::create([
            'name' => 'Waiter A',
            'email' => 'waiterA@branchtest.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        CafeUser::create([
            'cafe_id' => $this->cafeA->id,
            'user_id' => $this->ownerA->id,
            'role_id' => $this->ownerRoleA->id,
            'status' => 'active',
        ]);

        CafeUser::create([
            'cafe_id' => $this->cafeA->id,
            'user_id' => $this->waiterA->id,
            'role_id' => $this->waiterRoleA->id,
            'status' => 'active',
        ]);
    }

    public function test_cafe_settings_access_and_authorization(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->getJson('/cafes/branch-cafe-a/settings');
        $response->assertStatus(200)
            ->assertJson(['cafe' => ['slug' => 'branch-cafe-a']]);

        $updateResponse = $this->putJson('/cafes/branch-cafe-a/settings', [
            'name' => 'Updated Cafe A',
            'email' => 'updated@cafea.com',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJson(['message' => 'Cafe settings updated successfully.']);

        $this->actingAs($this->waiterA);
        $unauthorizedResponse = $this->putJson('/cafes/branch-cafe-a/settings', [
            'name' => 'Hacked Cafe Name',
            'email' => 'hacked@cafea.com',
        ]);

        $unauthorizedResponse->assertStatus(403);
    }

    public function test_branch_creation_and_update(): void
    {
        $this->actingAs($this->ownerA);

        $createResponse = $this->postJson('/cafes/branch-cafe-a/branches', [
            'name' => 'Downtown Branch',
            'slug' => 'downtown',
        ]);

        $createResponse->assertStatus(201)
            ->assertJson([
                'message' => 'Branch created successfully.',
                'branch' => [
                    'name' => 'Downtown Branch',
                    'slug' => 'downtown',
                ],
            ]);

        $branch = Branch::where('cafe_id', $this->cafeA->id)->where('slug', 'downtown')->firstOrFail();

        $updateResponse = $this->putJson("/cafes/branch-cafe-a/branches/{$branch->id}", [
            'name' => 'Downtown Central',
            'slug' => 'downtown-central',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Branch updated successfully.',
                'branch' => [
                    'name' => 'Downtown Central',
                    'slug' => 'downtown-central',
                ],
            ]);
    }

    public function test_duplicate_branch_slug_denied_within_same_cafe(): void
    {
        $this->actingAs($this->ownerA);

        Branch::create([
            'cafe_id' => $this->cafeA->id,
            'name' => 'Airport Branch',
            'slug' => 'airport',
            'status' => 'active',
        ]);

        $response = $this->postJson('/cafes/branch-cafe-a/branches', [
            'name' => 'Airport Duplicate',
            'slug' => 'airport',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_branch_tenant_isolation(): void
    {
        Branch::create([
            'cafe_id' => $this->cafeA->id,
            'name' => 'Branch Alpha 1',
            'slug' => 'alpha-1',
            'status' => 'active',
        ]);

        Branch::create([
            'cafe_id' => $this->cafeB->id,
            'name' => 'Branch Beta 1',
            'slug' => 'beta-1',
            'status' => 'active',
        ]);

        $this->actingAs($this->ownerA);

        $response = $this->getJson('/cafes/branch-cafe-a/branches');

        $response->assertStatus(200);
        $branches = $response->json('branches');

        $slugs = array_column($branches, 'slug');
        $this->assertContains('alpha-1', $slugs);
        $this->assertNotContains('beta-1', $slugs);
    }
}
