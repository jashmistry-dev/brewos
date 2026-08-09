<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCafeStatusRequest;
use App\Models\Cafe;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminCafeController extends Controller
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Cafe::withCount(['branches', 'cafeUsers', 'orders', 'subscriptions']);

        if ($request->has('status') && ! empty($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        $cafes = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'cafes' => $cafes->map(fn ($cafe) => [
                'id'                 => $cafe->id,
                'name'               => $cafe->name,
                'slug'               => $cafe->slug,
                'email'              => $cafe->email,
                'phone'              => $cafe->phone,
                'status'             => $cafe->status,
                'branches_count'     => $cafe->branches_count,
                'users_count'        => $cafe->cafe_users_count,
                'orders_count'       => $cafe->orders_count,
                'subscriptions_count'=> $cafe->subscriptions_count,
                'created_at'         => $cafe->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function show(int|string $cafe_id): JsonResponse
    {
        $cafe = Cafe::with(['branches', 'subscriptions.plan'])->withCount(['branches', 'cafeUsers', 'orders'])->findOrFail($cafe_id);

        return response()->json([
            'cafe' => [
                'id'                 => $cafe->id,
                'name'               => $cafe->name,
                'slug'               => $cafe->slug,
                'email'              => $cafe->email,
                'phone'              => $cafe->phone,
                'status'             => $cafe->status,
                'timezone'           => $cafe->timezone,
                'currency'           => $cafe->currency,
                'branches_count'     => $cafe->branches_count,
                'users_count'        => $cafe->cafe_users_count,
                'orders_count'       => $cafe->orders_count,
                'branches'           => $cafe->branches,
                'subscriptions'      => $cafe->subscriptions,
                'created_at'         => $cafe->created_at?->toIso8601String(),
                'updated_at'         => $cafe->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function updateStatus(UpdateCafeStatusRequest $request, int|string $cafe_id): JsonResponse
    {
        $cafe = Cafe::findOrFail($cafe_id);
        $oldStatus = $cafe->status;
        $newStatus = $request->validated('status');

        $cafe->update(['status' => $newStatus]);

        $this->auditLogger->log(
            action: 'cafe.status_updated',
            entityType: 'cafe',
            entityId: $cafe->id,
            cafeId: $cafe->id,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $newStatus]
        );

        return response()->json([
            'message' => 'Cafe status updated successfully.',
            'cafe'    => [
                'id'     => $cafe->id,
                'name'   => $cafe->name,
                'slug'   => $cafe->slug,
                'status' => $cafe->status,
            ],
        ]);
    }

    public function destroy(int|string $cafe_id): JsonResponse
    {
        $cafe = Cafe::findOrFail($cafe_id);

        $this->auditLogger->log(
            action: 'cafe.deleted',
            entityType: 'cafe',
            entityId: $cafe->id,
            cafeId: $cafe->id,
            oldValues: ['name' => $cafe->name, 'slug' => $cafe->slug, 'status' => $cafe->status],
            newValues: null
        );

        $cafe->delete();

        return response()->json([
            'message' => 'Cafe deleted successfully.',
        ]);
    }
}
