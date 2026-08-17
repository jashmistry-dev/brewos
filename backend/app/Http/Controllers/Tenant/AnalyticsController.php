<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AnalyticsFilterRequest;
use App\Services\AnalyticsService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    public function customers(AnalyticsFilterRequest $request, string $cafe_slug): JsonResponse|\Inertia\Response
    {
        Gate::authorize('permission', 'report.view');

        $cafeId    = app(TenantContext::class)->getCafeId();
        $validated = $request->validated();

        $analytics = $this->analyticsService->getCustomerAnalytics(
            cafeId: $cafeId,
            startDate: $validated['start_date'] ?? null,
            endDate: $validated['end_date'] ?? null,
            branchId: isset($validated['branch_id']) ? (int) $validated['branch_id'] : null
        );

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($analytics);
        }

        return \Inertia\Inertia::render('Tenant/Analytics', [
            'activeTab' => 'customers',
            'analytics' => $analytics,
        ]);
    }

    public function menu(AnalyticsFilterRequest $request, string $cafe_slug): JsonResponse|\Inertia\Response
    {
        Gate::authorize('permission', 'report.view');

        $cafeId    = app(TenantContext::class)->getCafeId();
        $validated = $request->validated();

        $analytics = $this->analyticsService->getMenuAnalytics(
            cafeId: $cafeId,
            startDate: $validated['start_date'] ?? null,
            endDate: $validated['end_date'] ?? null,
            branchId: isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
            limit: isset($validated['limit']) ? (int) $validated['limit'] : null
        );

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($analytics);
        }

        return \Inertia\Inertia::render('Tenant/Analytics', [
            'activeTab' => 'menu',
            'analytics' => $analytics,
        ]);
    }

    public function peakHours(AnalyticsFilterRequest $request, string $cafe_slug): JsonResponse|\Inertia\Response
    {
        Gate::authorize('permission', 'report.view');

        $cafeId    = app(TenantContext::class)->getCafeId();
        $validated = $request->validated();

        $analytics = $this->analyticsService->getPeakHourAnalytics(
            cafeId: $cafeId,
            startDate: $validated['start_date'] ?? null,
            endDate: $validated['end_date'] ?? null,
            branchId: isset($validated['branch_id']) ? (int) $validated['branch_id'] : null
        );

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($analytics);
        }

        return \Inertia\Inertia::render('Tenant/Analytics', [
            'activeTab' => 'peakHours',
            'analytics' => $analytics,
        ]);
    }
}
