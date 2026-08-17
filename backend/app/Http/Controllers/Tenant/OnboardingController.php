<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class OnboardingController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function show(): InertiaResponse
    {
        $cafe = $this->tenantContext->getCafe();

        return Inertia::render('Tenant/Onboarding', [
            'cafe' => [
                'id'             => $cafe->id,
                'name'           => $cafe->name,
                'slug'           => $cafe->slug,
                'email'          => $cafe->email,
                'phone'          => $cafe->phone,
                'logo_url'       => $cafe->logo_url,
                'address'        => $cafe->address,
                'city'           => $cafe->city,
                'state'          => $cafe->state,
                'postal_code'    => $cafe->postal_code,
                'country'        => $cafe->country ?? 'US',
                'tax_number'     => $cafe->tax_number,
                'tax_rate'       => $cafe->tax_rate,
                'timezone'       => $cafe->timezone ?? 'UTC',
                'currency'       => $cafe->currency ?? 'USD',
                'onboarded_at'   => $cafe->onboarded_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $cafe = $this->tenantContext->getCafe();

        $validated = $request->validate([
            'address'     => ['nullable', 'string', 'max:1000'],
            'city'        => ['nullable', 'string', 'max:100'],
            'state'       => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country'     => ['nullable', 'string', 'max:100'],
            'tax_number'  => ['nullable', 'string', 'max:50'],
            'tax_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'timezone'    => ['nullable', 'string', 'max:50'],
            'currency'    => ['nullable', 'string', 'max:10'],
            'logo'        => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            $diskName = config('filesystems.default', 'public');
            if ($cafe->logo_path) {
                Storage::disk($diskName)->delete($cafe->logo_path);
            }
            $validated['logo_path'] = $request->file('logo')->store('cafe_logos', $diskName);
        }

        unset($validated['logo']);
        $validated['onboarded_at'] = now();

        $cafe->update($validated);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Cafe onboarding profile completed successfully.',
                'cafe'    => [
                    'id'           => $cafe->id,
                    'name'         => $cafe->name,
                    'slug'         => $cafe->slug,
                    'logo_url'     => $cafe->logo_url,
                    'onboarded_at' => $cafe->onboarded_at?->toIso8601String(),
                ],
            ]);
        }

        return redirect()->route('tenant.dashboard', ['cafe_slug' => $cafe->slug])
            ->with('success', 'Onboarding completed! Welcome to your BrewOS workspace.');
    }
}
