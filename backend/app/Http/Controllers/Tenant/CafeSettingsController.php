<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateCafeSettingsRequest;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CafeSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        Gate::authorize('permission', 'cafe.view');

        $cafe = app(TenantContext::class)->getCafe();

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

    public function update(UpdateCafeSettingsRequest $request): JsonResponse
    {
        Gate::authorize('permission', 'cafe.settings.update');

        $cafe = app(TenantContext::class)->getCafe();
        $cafe->update($request->validated());

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
}
