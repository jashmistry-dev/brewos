<?php

namespace Database\Seeders;

use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the platform-scoped Super Admin user.
     *
     * Credentials are read from environment variables with safe local defaults.
     * Running this seeder multiple times is idempotent — no duplicates will be created.
     *
     * Required architecture note:
     *   User::isSuperAdmin() checks cafe_users joined to a role with
     *   scope='platform' AND slug='super-admin'. Because cafe_users.cafe_id is
     *   a non-nullable FK, a sentinel "platform" Cafe row is used as the anchor.
     *   This cafe is NOT a tenant — it is a system-internal placeholder.
     */
    public function run(): void
    {
        // ── 1. Platform-sentinel Cafe ─────────────────────────────────────────
        // Not a real tenant. Exists solely as the FK anchor for the super-admin
        // CafeUser row required by User::isSuperAdmin().
        // withoutGlobalScopes() is used here because the Cafe model's global scope
        // excludes the sentinel from normal queries — we must bypass it in the seeder.
        $platformCafe = Cafe::withoutGlobalScopes()->firstOrCreate(
            ['slug' => Cafe::PLATFORM_SENTINEL_SLUG],
            [
                'name'   => 'BrewOS Platform',
                'status' => 'active',
            ]
        );

        // ── 2. Platform-scoped Super Admin Role ───────────────────────────────
        // cafe_id = null marks this as a platform role (Role::isPlatformRole()).
        // slug and scope must match exactly what isSuperAdmin() queries.
        $superAdminRole = Role::firstOrCreate(
            ['slug' => 'super-admin', 'scope' => 'platform'],
            [
                'cafe_id' => null,
                'name'    => 'Super Admin',
            ]
        );

        // ── 3. Super Admin User ───────────────────────────────────────────────
        $name     = env('SUPER_ADMIN_NAME',     'Super Admin');
        $email    = env('SUPER_ADMIN_EMAIL',    'admin@brewos.local');
        $password = env('SUPER_ADMIN_PASSWORD', 'password');

        $superAdmin = User::withTrashed()->firstOrCreate(
            ['email' => $email],
            [
                'name'     => $name,
                'password' => Hash::make($password),
                'status'   => 'active',
            ]
        );

        // If the user was soft-deleted, restore and refresh their status.
        if ($superAdmin->trashed()) {
            $superAdmin->restore();
        }

        // Ensure the status is always 'active' on re-seed.
        if ($superAdmin->status !== 'active') {
            $superAdmin->update(['status' => 'active']);
        }

        // ── 4. CafeUser Membership ────────────────────────────────────────────
        // Required for User::isSuperAdmin() to return true (see User.php#isSuperAdmin).
        // Anchored to the platform sentinel cafe — not a real tenant membership.
        CafeUser::firstOrCreate(
            [
                'user_id' => $superAdmin->id,
                'cafe_id' => $platformCafe->id,
            ],
            [
                'role_id'   => $superAdminRole->id,
                'branch_id' => null,
                'status'    => 'active',
            ]
        );

        $this->command->info("✓ Super Admin seeded: {$email}");
        $this->command->line("  Role : {$superAdminRole->name} (scope={$superAdminRole->scope})");
        $this->command->line("  Cafe : {$platformCafe->name} [platform sentinel — not a tenant]");
        $this->command->line("  isSuperAdmin() will return: true");
    }
}
