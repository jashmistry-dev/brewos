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

class StaffManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafeA;
    protected Cafe $cafeB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected User $ownerA;
    protected User $cashierA;
    protected Role $ownerRoleA;
    protected Role $cashierRoleA;
    protected Role $ownerRoleB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->cafeA = Cafe::create([
            'name' => 'Staff Cafe A',
            'slug' => 'staff-cafe-a',
            'status' => 'active',
        ]);

        $this->cafeB = Cafe::create([
            'name' => 'Staff Cafe B',
            'slug' => 'staff-cafe-b',
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

        $this->ownerRoleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name' => 'Cafe Owner',
            'slug' => 'cafe-owner',
            'scope' => 'tenant',
        ]);

        $this->cashierRoleA = Role::create([
            'cafe_id' => $this->cafeA->id,
            'name' => 'Cashier',
            'slug' => 'cashier',
            'scope' => 'tenant',
        ]);

        $this->ownerRoleB = Role::create([
            'cafe_id' => $this->cafeB->id,
            'name' => 'Cafe Owner B',
            'slug' => 'cafe-owner-b',
            'scope' => 'tenant',
        ]);

        $staffPerms = ['staff.view', 'staff.create', 'staff.update', 'staff.delete'];
        foreach ($staffPerms as $permSlug) {
            $permission = Permission::firstOrCreate(['slug' => $permSlug], ['name' => $permSlug]);
            $this->ownerRoleA->permissions()->attach($permission->id);
        }

        $this->ownerA = User::create([
            'name' => 'Owner A',
            'email' => 'ownerA@stafftest.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->cashierA = User::create([
            'name' => 'Cashier A',
            'email' => 'cashierA@stafftest.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        CafeUser::create([
            'cafe_id' => $this->cafeA->id,
            'user_id' => $this->ownerA->id,
            'role_id' => $this->ownerRoleA->id,
            'branch_id' => $this->branchA->id,
            'status' => 'active',
        ]);

        CafeUser::create([
            'cafe_id' => $this->cafeA->id,
            'user_id' => $this->cashierA->id,
            'role_id' => $this->cashierRoleA->id,
            'branch_id' => $this->branchA->id,
            'status' => 'active',
        ]);
    }

    public function test_staff_creation_and_update(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->postJson('/cafes/staff-cafe-a/staff', [
            'name' => 'New Staff Member',
            'email' => 'newstaff@stafftest.com',
            'password' => 'password123',
            'role_id' => $this->cashierRoleA->id,
            'branch_id' => $this->branchA->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Staff member created successfully.',
                'staff' => [
                    'email' => 'newstaff@stafftest.com',
                ],
            ]);

        $staffUser = User::where('email', 'newstaff@stafftest.com')->firstOrFail();
        $membership = CafeUser::where('cafe_id', $this->cafeA->id)->where('user_id', $staffUser->id)->firstOrFail();

        $updateResponse = $this->putJson("/cafes/staff-cafe-a/staff/{$membership->id}", [
            'name' => 'Updated Staff Name',
            'status' => 'suspended',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Staff member updated successfully.',
                'staff' => [
                    'status' => 'suspended',
                ],
            ]);
    }

    public function test_staff_authorization_denied_for_unprivileged_user(): void
    {
        $this->actingAs($this->cashierA);

        $response = $this->postJson('/cafes/staff-cafe-a/staff', [
            'name' => 'Unauthorized Add',
            'email' => 'unauth@stafftest.com',
            'password' => 'password123',
            'role_id' => $this->cashierRoleA->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_cross_cafe_role_assignment_denied(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->postJson('/cafes/staff-cafe-a/staff', [
            'name' => 'Cross Role Staff',
            'email' => 'crossrole@stafftest.com',
            'password' => 'password123',
            'role_id' => $this->ownerRoleB->id,
            'branch_id' => $this->branchA->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }

    public function test_cross_cafe_branch_assignment_denied(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->postJson('/cafes/staff-cafe-a/staff', [
            'name' => 'Cross Branch Staff',
            'email' => 'crossbranch@stafftest.com',
            'password' => 'password123',
            'role_id' => $this->cashierRoleA->id,
            'branch_id' => $this->branchB->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['branch_id']);
    }
}
