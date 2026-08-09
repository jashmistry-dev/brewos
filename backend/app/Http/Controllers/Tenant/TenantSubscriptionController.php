<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SubscribePlanRequest;
use App\Models\Plan;
use App\Services\SubscriptionService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TenantSubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    public function index(string $cafe_slug): JsonResponse
    {
        Gate::authorize('permission', 'subscription.view');

        $cafeId = app(TenantContext::class)->getCafeId();
        $overview = $this->subscriptionService->getSubscriptionOverview($cafeId);

        return response()->json($overview);
    }

    public function subscribe(SubscribePlanRequest $request, string $cafe_slug): JsonResponse
    {
        Gate::authorize('permission', 'subscription.update');

        $cafeId = app(TenantContext::class)->getCafeId();
        $validated = $request->validated();

        $plan = Plan::findOrFail($validated['plan_id']);

        $subscription = $this->subscriptionService->subscribeOrUpgrade(
            cafeId: $cafeId,
            plan: $plan,
            provider: $validated['provider'] ?? null,
            providerSubscriptionId: $validated['provider_subscription_id'] ?? null
        );

        return response()->json([
            'message'      => 'Subscription updated successfully.',
            'subscription' => [
                'id'                       => $subscription->id,
                'plan_id'                  => $subscription->plan_id,
                'status'                   => $subscription->status,
                'starts_at'                => $subscription->starts_at?->toIso8601String(),
                'ends_at'                  => $subscription->ends_at?->toIso8601String(),
                'provider'                 => $subscription->provider,
                'provider_subscription_id' => $subscription->provider_subscription_id,
            ],
        ]);
    }

    public function cancel(string $cafe_slug): JsonResponse
    {
        Gate::authorize('permission', 'subscription.update');

        $cafeId = app(TenantContext::class)->getCafeId();

        $subscription = $this->subscriptionService->cancelSubscription($cafeId);

        return response()->json([
            'message'      => 'Subscription cancelled successfully.',
            'subscription' => [
                'id'      => $subscription->id,
                'status'  => $subscription->status,
                'ends_at' => $subscription->ends_at?->toIso8601String(),
            ],
        ]);
    }
}
