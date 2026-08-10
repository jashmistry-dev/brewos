<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanFeatureRequest;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlanFeatureController extends Controller
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    public function index(int|string $plan_id, Request $request): JsonResponse|RedirectResponse
    {
        $plan = Plan::findOrFail($plan_id);
        $features = $plan->features()->orderBy('feature_key', 'asc')->get();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'plan_id'  => $plan->id,
                'features' => $features->map(fn ($f) => [
                    'id'          => $f->id,
                    'feature_key' => $f->feature_key,
                    'value'       => $f->value,
                    'created_at'  => $f->created_at?->toIso8601String(),
                ]),
            ]);
        }

        return redirect()->route('admin.plans.index');
    }

    public function store(StorePlanFeatureRequest $request, int|string $plan_id): JsonResponse|RedirectResponse
    {
        $plan = Plan::findOrFail($plan_id);
        $validated = $request->validated();

        $exists = PlanFeature::where('plan_id', $plan->id)
            ->where('feature_key', $validated['feature_key'])
            ->exists();

        if ($exists) {
            if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'message' => 'Feature key already exists for this plan.',
                    'errors'  => [
                        'feature_key' => ['The feature key has already been added to this plan.'],
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return redirect()->back()->withErrors(['feature_key' => 'The feature key has already been added to this plan.']);
        }

        $feature = PlanFeature::create([
            'plan_id'     => $plan->id,
            'feature_key' => $validated['feature_key'],
            'value'       => $validated['value'],
        ]);

        $this->auditLogger->log(
            action: 'plan_feature.created',
            entityType: 'plan_feature',
            entityId: $feature->id,
            cafeId: null,
            oldValues: null,
            newValues: ['plan_id' => $plan->id, 'feature_key' => $feature->feature_key, 'value' => $feature->value]
        );

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Plan feature added successfully.',
                'feature' => [
                    'id'          => $feature->id,
                    'plan_id'     => $feature->plan_id,
                    'feature_key' => $feature->feature_key,
                    'value'       => $feature->value,
                    'created_at'  => $feature->created_at?->toIso8601String(),
                ],
            ], Response::HTTP_CREATED);
        }

        return redirect()->back()->with('success', 'Plan feature added successfully.');
    }

    public function destroy(Request $request, int|string $plan_id, int|string $feature_id): JsonResponse|RedirectResponse
    {
        $plan = Plan::findOrFail($plan_id);
        $feature = PlanFeature::where('plan_id', $plan->id)->where('id', $feature_id)->firstOrFail();

        $oldValues = ['plan_id' => $plan->id, 'feature_key' => $feature->feature_key, 'value' => $feature->value];

        $this->auditLogger->log(
            action: 'plan_feature.deleted',
            entityType: 'plan_feature',
            entityId: $feature->id,
            cafeId: null,
            oldValues: $oldValues,
            newValues: null
        );

        $feature->delete();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Plan feature removed successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Plan feature removed successfully.');
    }
}
