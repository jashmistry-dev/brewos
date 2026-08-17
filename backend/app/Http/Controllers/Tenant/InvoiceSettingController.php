<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateInvoiceSettingRequest;
use App\Models\InvoiceSetting;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class InvoiceSettingController extends Controller
{
    public function show(\Illuminate\Http\Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        Gate::authorize('permission', 'invoice.view');

        $cafeId  = app(TenantContext::class)->getCafeId();
        $setting = InvoiceSetting::where('cafe_id', $cafeId)->first();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'invoice_setting' => $setting ? $this->format($setting) : null,
            ]);
        }

        return redirect()->back();
    }

    public function update(UpdateInvoiceSettingRequest $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        Gate::authorize('permission', 'invoice.settings.update');

        $cafeId    = app(TenantContext::class)->getCafeId();
        $validated = $request->validated();

        $setting = InvoiceSetting::updateOrCreate(
            ['cafe_id' => $cafeId],
            $validated
        );

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message'         => 'Invoice settings updated successfully.',
                'invoice_setting' => $this->format($setting),
            ]);
        }

        return redirect()->back()->with('success', 'Invoice settings updated successfully.');
    }

    private function format(InvoiceSetting $setting): array
    {
        return [
            'id'            => $setting->id,
            'cafe_id'       => $setting->cafe_id,
            'business_name' => $setting->business_name,
            'address'       => $setting->address,
            'gst_number'    => $setting->gst_number,
            'logo'          => $setting->logo,
            'footer_text'   => $setting->footer_text,
            'updated_at'    => $setting->updated_at?->toIso8601String(),
        ];
    }
}
