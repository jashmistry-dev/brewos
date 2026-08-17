<?php

namespace Tests\Feature;

use App\Models\Cafe;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SeederIdempotencyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_super_admin_seeder_is_idempotent(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $firstUserCount = User::where('email', 'admin@brewos.local')->count();
        $firstRoleCount = Role::where('slug', 'super-admin')->where('scope', 'platform')->count();
        $firstCafeCount = Cafe::withoutGlobalScopes()->where('slug', Cafe::PLATFORM_SENTINEL_SLUG)->count();

        // Run seeder a second time
        $this->seed(SuperAdminSeeder::class);

        $secondUserCount = User::where('email', 'admin@brewos.local')->count();
        $secondRoleCount = Role::where('slug', 'super-admin')->where('scope', 'platform')->count();
        $secondCafeCount = Cafe::withoutGlobalScopes()->where('slug', Cafe::PLATFORM_SENTINEL_SLUG)->count();

        $this->assertEquals(1, $firstUserCount);
        $this->assertEquals(1, $secondUserCount);
        $this->assertEquals(1, $firstRoleCount);
        $this->assertEquals(1, $secondRoleCount);
        $this->assertEquals(1, $firstCafeCount);
        $this->assertEquals(1, $secondCafeCount);

        $admin = User::where('email', 'admin@brewos.local')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->isSuperAdmin());
    }

    public function test_database_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $firstTestUserCount = User::where('email', 'test@example.com')->count();
        $firstAdminCount    = User::where('email', 'admin@brewos.local')->count();

        // Run DatabaseSeeder a second time
        $this->seed(DatabaseSeeder::class);

        $secondTestUserCount = User::where('email', 'test@example.com')->count();
        $secondAdminCount    = User::where('email', 'admin@brewos.local')->count();

        $this->assertEquals(1, $firstTestUserCount);
        $this->assertEquals(1, $secondTestUserCount);
        $this->assertEquals(1, $firstAdminCount);
        $this->assertEquals(1, $secondAdminCount);
    }
}
