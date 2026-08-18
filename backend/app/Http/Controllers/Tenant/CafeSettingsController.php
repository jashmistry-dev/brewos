<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateCafeSettingsRequest;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
                    'id'          => $cafe->id,
                    'name'        => $cafe->name,
                    'slug'        => $cafe->slug,
                    'email'       => $cafe->email,
                    'phone'       => $cafe->phone,
                    'status'      => $cafe->status,
                    'address'     => $cafe->address,
                    'city'        => $cafe->city,
                    'state'       => $cafe->state,
                    'postal_code' => $cafe->postal_code,
                    'country'     => $cafe->country,
                    'tax_number'  => $cafe->tax_number,
                    'tax_rate'    => $cafe->tax_rate,
                    'timezone'    => $cafe->timezone,
                    'currency'    => $cafe->currency,
                    'logo_url'    => $cafe->logo_url,
                ],
            ]);
        }

        return Inertia::render('Tenant/Settings', [
            'cafe' => [
                'id'          => $cafe->id,
                'name'        => $cafe->name,
                'slug'        => $cafe->slug,
                'email'       => $cafe->email,
                'phone'       => $cafe->phone,
                'address'     => $cafe->address,
                'city'        => $cafe->city,
                'state'       => $cafe->state,
                'postal_code' => $cafe->postal_code,
                'country'     => $cafe->country ?? 'US',
                'tax_number'  => $cafe->tax_number,
                'tax_rate'    => $cafe->tax_rate ?? 0.0,
                'timezone'    => $cafe->timezone ?? 'UTC',
                'currency'    => $cafe->currency ?? 'USD',
                'logo_url'    => $cafe->logo_url,
                'status'      => $cafe->status,
            ],
        ]);
    }

    public function update(UpdateCafeSettingsRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('permission', 'cafe.settings.update');

        $cafe = app(TenantContext::class)->getCafe();
        $validated = $request->validated();
        $diskName = config('filesystems.default', 'public');

        if ($request->boolean('remove_logo')) {
            if ($cafe->logo_path && !str_starts_with($cafe->logo_path, 'http')) {
                Storage::disk($diskName)->delete($cafe->logo_path);
            }
            $validated['logo_path'] = null;
        } elseif ($request->input('logo_base64')) {
            $base64Str = $request->input('logo_base64');
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Str, $type)) {
                $data = substr($base64Str, strpos($base64Str, ',') + 1);
                $ext = strtolower($type[1]);
                if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
                    $decoded = base64_decode($data);
                    $fileName = 'cafe_logos/' . uniqid('logo_') . '.' . $ext;
                    Storage::disk($diskName)->put($fileName, $decoded);
                    if ($cafe->logo_path && !str_starts_with($cafe->logo_path, 'http')) {
                        Storage::disk($diskName)->delete($cafe->logo_path);
                    }
                    $validated['logo_path'] = $fileName;
                }
            }
        } elseif ($request->hasFile('logo')) {
            if ($cafe->logo_path && !str_starts_with($cafe->logo_path, 'http')) {
                Storage::disk($diskName)->delete($cafe->logo_path);
            }
            $validated['logo_path'] = $request->file('logo')->store('cafe_logos', $diskName);
        } elseif (isset($validated['logo_url']) && !empty($validated['logo_url'])) {
            $validated['logo_path'] = $validated['logo_url'];
        }

        unset($validated['logo_url'], $validated['logo'], $validated['logo_base64'], $validated['remove_logo']);

        $cafe->update($validated);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Cafe settings updated successfully.',
                'cafe' => [
                    'id'       => $cafe->id,
                    'name'     => $cafe->name,
                    'slug'     => $cafe->slug,
                    'email'    => $cafe->email,
                    'phone'    => $cafe->phone,
                    'status'   => $cafe->status,
                    'logo_url' => $cafe->logo_url,
                ],
            ]);
        }

        return redirect()->back()->with('success', 'Cafe settings updated successfully.');
    }
}
