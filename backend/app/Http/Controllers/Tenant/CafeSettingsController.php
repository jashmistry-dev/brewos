<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateCafeSettingsRequest;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CafeSettingsController extends Controller
{
    public function show(): JsonResponse|InertiaResponse
    {
        Gate::authorize('permission', 'cafe.view');

        $cafe = app(TenantContext::class)->getCafe();

        if (request()->wantsJson() && ! request()->header('X-Inertia')) {
            return response()->json([
                'cafe' => [
                    'id' => $cafe->id,
                    'name' => $cafe->name,
                    'slug' => $cafe->slug,
                    'email' => $cafe->email,
                    'phone' => $cafe->phone,
                    'status' => $cafe->status,
                ],
            ]);
        }

        return Inertia::render('Tenant/Settings', [
            'cafe' => [
                'id' => $cafe->id,
                'name' => $cafe->name,
                'slug' => $cafe->slug,
                'email' => $cafe->email,
                'phone' => $cafe->phone,
                'timezone' => $cafe->timezone,
                'currency' => $cafe->currency,
                'tax_rate' => $cafe->tax_rate,
                'logo_url' => $cafe->logo_url,
                'status' => $cafe->status,
            ],
        ]);
    }

    public function update(UpdateCafeSettingsRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('permission', 'cafe.settings.update');

        $cafe = app(TenantContext::class)->getCafe();
        $cafe->update($request->validated());

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Cafe settings updated successfully.',
                'cafe' => [
                    'id' => $cafe->id,
                    'name' => $cafe->name,
                    'slug' => $cafe->slug,
                    'email' => $cafe->email,
                    'phone' => $cafe->phone,
                    'status' => $cafe->status,
                ],
            ]);
        }

        return redirect()->back()->with('success', 'Cafe settings updated successfully.');
    }
}
