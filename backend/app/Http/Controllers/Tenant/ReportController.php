<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ReportFilterRequest;
use App\Services\ReportService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    public function sales(ReportFilterRequest $request, string $cafe_slug): JsonResponse
    {
        Gate::authorize('permission', 'report.view');

        $cafeId    = app(TenantContext::class)->getCafeId();
        $validated = $request->validated();

        $report = $this->reportService->getSalesReport(
            cafeId: $cafeId,
            startDate: $validated['start_date'] ?? null,
            endDate: $validated['end_date'] ?? null,
            branchId: isset($validated['branch_id']) ? (int) $validated['branch_id'] : null
        );

        return response()->json($report);
    }

    public function revenue(ReportFilterRequest $request, string $cafe_slug): JsonResponse
    {
        Gate::authorize('permission', 'report.view');

        $cafeId    = app(TenantContext::class)->getCafeId();
        $validated = $request->validated();

        $report = $this->reportService->getRevenueReport(
            cafeId: $cafeId,
            startDate: $validated['start_date'] ?? null,
            endDate: $validated['end_date'] ?? null,
            branchId: isset($validated['branch_id']) ? (int) $validated['branch_id'] : null
        );

        return response()->json($report);
    }

    public function staff(ReportFilterRequest $request, string $cafe_slug): JsonResponse
    {
        Gate::authorize('permission', 'report.view');

        $cafeId    = app(TenantContext::class)->getCafeId();
        $validated = $request->validated();

        $report = $this->reportService->getStaffReport(
            cafeId: $cafeId,
            startDate: $validated['start_date'] ?? null,
            endDate: $validated['end_date'] ?? null,
            branchId: isset($validated['branch_id']) ? (int) $validated['branch_id'] : null
        );

        return response()->json($report);
    }
}
