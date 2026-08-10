<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTableRequest;
use App\Http\Requests\Tenant\UpdateTableRequest;
use App\Models\Branch;
use App\Models\RestaurantTable;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

use App\Models\Cafe;
use App\Services\EntitlementService;
use Illuminate\Support\Facades\DB;

class TableController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('permission', 'table.view');

        $tenantContext = app(TenantContext::class);
        $cafeId = $tenantContext->getCafeId();

        $branchIds = Branch::where('cafe_id', $cafeId)->pluck('id');

        $query = RestaurantTable::whereIn('branch_id', $branchIds);

        if ($request->has('branch_id')) {
            $branchId = (int) $request->query('branch_id');
            // Ensure queried branch_id belongs to current cafe
            if (! $branchIds->contains($branchId)) {
                return response()->json(['message' => 'Branch not found or does not belong to this cafe.'], 404);
            }
            $query->where('branch_id', $branchId);
        }

        $tables = $query->orderBy('name', 'asc')->get();

        return response()->json([
            'tables' => $tables->map(fn ($t) => [
                'id' => $t->id,
                'branch_id' => $t->branch_id,
                'name' => $t->name,
                'capacity' => $t->capacity,
                'status' => $t->status,
                'qr_token' => $t->qr_token,
            ]),
        ]);
    }

    public function store(StoreTableRequest $request): JsonResponse
    {
        Gate::authorize('permission', 'table.create');

        $cafeId = app(TenantContext::class)->getCafeId();

        $this->entitlementService->checkTableLimit($cafeId);

        $table = DB::transaction(function () use ($request, $cafeId) {
            // Lock cafe record to prevent race conditions during limit checks
            Cafe::where('id', $cafeId)->lockForUpdate()->first();

            return RestaurantTable::create([
                'branch_id' => $request->validated('branch_id'),
                'name'      => $request->validated('name'),
                'capacity'  => $request->validated('capacity', 1),
                'status'    => $request->validated('status', 'available'),
                'qr_token'  => RestaurantTable::generateQrToken(),
            ]);
        });

        return response()->json([
            'message' => 'Table created successfully.',
            'table' => [
                'id' => $table->id,
                'branch_id' => $table->branch_id,
                'name' => $table->name,
                'capacity' => $table->capacity,
                'status' => $table->status,
                'qr_token' => $table->qr_token,
            ],
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateTableRequest $request, string $cafe_slug, int|string $table_id): JsonResponse
    {
        Gate::authorize('permission', 'table.update');

        $table = $this->findTableForTenant($table_id);
        $table->update($request->validated());

        return response()->json([
            'message' => 'Table updated successfully.',
            'table' => [
                'id' => $table->id,
                'branch_id' => $table->branch_id,
                'name' => $table->name,
                'capacity' => $table->capacity,
                'status' => $table->status,
                'qr_token' => $table->qr_token,
            ],
        ]);
    }

    public function regenerateQrToken(string $cafe_slug, int|string $table_id): JsonResponse
    {
        Gate::authorize('permission', 'table.update');

        $table = $this->findTableForTenant($table_id);
        $table->update([
            'qr_token' => RestaurantTable::generateQrToken(),
        ]);

        return response()->json([
            'message' => 'QR token regenerated successfully.',
            'qr_token' => $table->qr_token,
        ]);
    }

    public function destroy(string $cafe_slug, int|string $table_id): JsonResponse
    {
        Gate::authorize('permission', 'table.delete');

        $table = $this->findTableForTenant($table_id);
        $table->delete();

        return response()->json([
            'message' => 'Table deleted successfully.',
        ]);
    }

    private function findTableForTenant(int|string $tableId): RestaurantTable
    {
        $tenantContext = app(TenantContext::class);
        $cafeId = $tenantContext->getCafeId();
        $branchIds = Branch::where('cafe_id', $cafeId)->pluck('id');

        return RestaurantTable::whereIn('branch_id', $branchIds)
            ->where('id', $tableId)
            ->firstOrFail();
    }
}
