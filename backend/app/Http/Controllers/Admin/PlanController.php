<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Models\Plan;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PlanController extends Controller
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    public function index(): JsonResponse
    {
        $plans = Plan::withCount(['features', 'subscriptions'])->orderBy('id', 'asc')->get();

        return response()->json([
            'plans' => $plans->map(fn ($plan) => [
                'id'                 => $plan->id,
                'name'               => $plan->name,
                'slug'               => $plan->slug,
                'description'        => $plan->description,
                'price'              => (float) $plan->price,
                'billing_interval'   => $plan->billing_interval,
                'status'             => $plan->status,
                'features_count'     => $plan->features_count,
                'subscriptions_count'=> $plan->subscriptions_count,
                'created_at'         => $plan->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $plan = Plan::create([
            'name'             => $validated['name'],
            'slug'             => $validated['slug'],
            'description'      => $validated['description'] ?? null,
            'price'            => $validated['price'],
            'billing_interval' => $validated['billing_interval'],
            'status'           => $validated['status'] ?? 'active',
        ]);

        $this->auditLogger->log(
            action: 'plan.created',
            entityType: 'plan',
            entityId: $plan->id,
            cafeId: null,
            oldValues: null,
            newValues: $plan->only(['name', 'slug', 'price', 'billing_interval', 'status'])
        );

        return response()->json([
            'message' => 'Plan created successfully.',
            'plan'    => [
                'id'               => $plan->id,
                'name'             => $plan->name,
                'slug'             => $plan->slug,
                'description'      => $plan->description,
                'price'            => (float) $plan->price,
                'billing_interval' => $plan->billing_interval,
                'status'           => $plan->status,
                'created_at'       => $plan->created_at?->toIso8601String(),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(int|string $plan_id): JsonResponse
    {
        $plan = Plan::with(['features'])->withCount(['subscriptions'])->findOrFail($plan_id);

        return response()->json([
            'plan' => [
                'id'                 => $plan->id,
                'name'               => $plan->name,
                'slug'               => $plan->slug,
                'description'        => $plan->description,
                'price'              => (float) $plan->price,
                'billing_interval'   => $plan->billing_interval,
                'status'             => $plan->status,
                'subscriptions_count'=> $plan->subscriptions_count,
                'features'           => $plan->features->map(fn ($f) => [
                    'id'          => $f->id,
                    'feature_key' => $f->feature_key,
                    'value'       => $f->value,
                ]),
                'created_at'         => $plan->created_at?->toIso8601String(),
                'updated_at'         => $plan->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(UpdatePlanRequest $request, int|string $plan_id): JsonResponse
    {
        $plan = Plan::findOrFail($plan_id);
        $oldValues = $plan->only(['name', 'slug', 'description', 'price', 'billing_interval', 'status']);
        $validated = $request->validated();

        $plan->update($validated);
        $newValues = $plan->only(['name', 'slug', 'description', 'price', 'billing_interval', 'status']);

        $this->auditLogger->log(
            action: 'plan.updated',
            entityType: 'plan',
            entityId: $plan->id,
            cafeId: null,
            oldValues: $oldValues,
            newValues: $newValues
        );

        return response()->json([
            'message' => 'Plan updated successfully.',
            'plan'    => [
                'id'               => $plan->id,
                'name'             => $plan->name,
                'slug'             => $plan->slug,
                'description'      => $plan->description,
                'price'            => (float) $plan->price,
                'billing_interval' => $plan->billing_interval,
                'status'           => $plan->status,
                'updated_at'       => $plan->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function destroy(int|string $plan_id): JsonResponse
    {
        $plan = Plan::withCount('subscriptions')->findOrFail($plan_id);

        if ($plan->subscriptions_count > 0) {
            return response()->json([
                'message' => 'Cannot delete plan with active or past subscriptions. Disable the plan instead.',
                'errors'  => [
                    'plan' => ['Plan is associated with ' . $plan->subscriptions_count . ' subscription(s).'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $oldValues = $plan->only(['name', 'slug', 'status']);

        $this->auditLogger->log(
            action: 'plan.deleted',
            entityType: 'plan',
            entityId: $plan->id,
            cafeId: null,
            oldValues: $oldValues,
            newValues: null
        );

        $plan->delete();

        return response()->json([
            'message' => 'Plan deleted successfully.',
        ]);
    }
}
