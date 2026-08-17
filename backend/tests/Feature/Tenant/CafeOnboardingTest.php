<?php

namespace Tests\Feature\Tenant;

use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CafeOnboardingTest extends TestCase
{
    use DatabaseTransactions;

    protected User $owner;
    protected Cafe $cafe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->cafe = Cafe::create([
            'name'   => 'Onboarding Test Cafe',
            'slug'   => 'onboarding-test-cafe',
            'email'  => 'owner@onboardingtest.com',
            'status' => 'active',
        ]);

        $branch = Branch::create([
            'cafe_id' => $this->cafe->id,
            'name'    => 'Main Branch',
            'slug'    => 'main',
            'status'  => 'active',
        ]);

        $role = Role::create([
            'cafe_id' => $this->cafe->id,
            'name'    => 'Cafe Owner',
            'slug'    => 'cafe-owner',
            'scope'   => 'tenant',
        ]);

        $this->owner = User::factory()->create([
            'name'   => 'Onboarding Owner',
            'email'  => 'owner@onboardingtest.com',
            'status' => 'active',
        ]);

        CafeUser::create([
            'cafe_id'   => $this->cafe->id,
            'user_id'   => $this->owner->id,
            'role_id'   => $role->id,
            'branch_id' => $branch->id,
            'status'    => 'active',
        ]);
    }

    public function test_owner_can_access_onboarding_page(): void
    {
        $response = $this->actingAs($this->owner)
            ->get("/cafes/{$this->cafe->slug}/onboarding");

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Tenant/Onboarding'));
    }

    public function test_owner_can_complete_onboarding_with_logo_and_tax_details(): void
    {
        $diskName = config('filesystems.default', 'public');
        Storage::fake($diskName);

        $logo = UploadedFile::fake()->image('cafe_logo.jpg', 400, 400);

        $response = $this->actingAs($this->owner)
            ->post("/cafes/{$this->cafe->slug}/onboarding", [
                'address'     => '100 Coffee Lane',
                'city'        => 'Portland',
                'state'       => 'OR',
                'postal_code' => '97201',
                'country'     => 'US',
                'tax_number'  => 'TAX-998877',
                'tax_rate'    => 6.50,
                'timezone'    => 'America/Los_Angeles',
                'currency'    => 'USD',
                'logo'        => $logo,
            ]);

        $response->assertStatus(302);

        $this->cafe->refresh();
        $this->assertEquals('100 Coffee Lane', $this->cafe->address);
        $this->assertEquals('TAX-998877', $this->cafe->tax_number);
        $this->assertEquals(6.50, $this->cafe->tax_rate);
        $this->assertNotNull($this->cafe->logo_path);
        $this->assertNotNull($this->cafe->onboarded_at);
        $this->assertNotNull($this->cafe->logo_url);

        Storage::disk($diskName)->assertExists($this->cafe->logo_path);
    }
}
