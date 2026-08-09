<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Subscription::with(['cafe:id,name,slug', 'plan:id,name,slug,price']);

        if ($request->has('status') && ! empty($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        $subscriptions = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'subscriptions' => $subscriptions->map(fn ($sub) => [
                'id'                       => $sub->id,
                'cafe_id'                  => $sub->cafe_id,
                'cafe_name'                => $sub->cafe?->name,
                'cafe_slug'                => $sub->cafe?->slug,
                'plan_id'                  => $sub->plan_id,
                'plan_name'                => $sub->plan?->name,
                'plan_price'               => (float) $sub->plan?->price,
                'status'                   => $sub->status,
                'starts_at'                => $sub->starts_at?->toIso8601String(),
                'ends_at'                  => $sub->ends_at?->toIso8601String(),
                'trial_ends_at'            => $sub->trial_ends_at?->toIso8601String(),
                'provider'                 => $sub->provider,
                'provider_subscription_id' => $sub->provider_subscription_id,
                'created_at'               => $sub->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function show(int|string $subscription_id): JsonResponse
    {
        $sub = Subscription::with(['cafe', 'plan.features'])->findOrFail($subscription_id);

        return response()->json([
            'subscription' => [
                'id'                       => $sub->id,
                'cafe_id'                  => $sub->cafe_id,
                'cafe'                     => [
                    'id'   => $sub->cafe?->id,
                    'name' => $sub->cafe?->name,
                    'slug' => $sub->cafe?->slug,
                ],
                'plan_id'                  => $sub->plan_id,
                'plan'                     => [
                    'id'               => $sub->plan?->id,
                    'name'             => $sub->plan?->name,
                    'slug'             => $sub->plan?->slug,
                    'price'            => (float) $sub->plan?->price,
                    'billing_interval' => $sub->plan?->billing_interval,
                ],
                'status'                   => $sub->status,
                'starts_at'                => $sub->starts_at?->toIso8601String(),
                'ends_at'                  => $sub->ends_at?->toIso8601String(),
                'trial_ends_at'            => $sub->trial_ends_at?->toIso8601String(),
                'provider'                 => $sub->provider,
                'provider_subscription_id' => $sub->provider_subscription_id,
                'created_at'               => $sub->created_at?->toIso8601String(),
                'updated_at'               => $sub->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function cancel(int|string $subscription_id): JsonResponse
    {
        $sub = Subscription::findOrFail($subscription_id);
        $oldStatus = $sub->status;

        $sub->update([
            'status'  => 'cancelled',
            'ends_at' => $sub->ends_at ?? now(),
        ]);

        $this->auditLogger->log(
            action: 'subscription.cancelled',
            entityType: 'subscription',
            entityId: $sub->id,
            cafeId: $sub->cafe_id,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'cancelled', 'ends_at' => $sub->ends_at?->toIso8601String()]
        );

        return response()->json([
            'message'      => 'Subscription cancelled successfully.',
            'subscription' => [
                'id'      => $sub->id,
                'status'  => $sub->status,
                'ends_at' => $sub->ends_at?->toIso8601String(),
            ],
        ]);
    }
}
