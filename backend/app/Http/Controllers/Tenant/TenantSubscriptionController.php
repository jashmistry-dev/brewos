<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SubscribePlanRequest;
use App\Models\Plan;
use App\Services\SubscriptionService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TenantSubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    public function index(Request $request, string $cafe_slug): JsonResponse|InertiaResponse
    {
        Gate::authorize('permission', 'subscription.view');

        $cafeId = app(TenantContext::class)->getCafeId();
        $overview = $this->subscriptionService->getSubscriptionOverview($cafeId);
        $allPlans = Plan::where('status', 'active')->with('features')->orderBy('price', 'asc')->get();

        $responseData = [
            'overview' => $overview,
            'plans'    => $allPlans->map(fn ($plan) => [
                'id'               => $plan->id,
                'name'             => $plan->name,
                'slug'             => $plan->slug,
                'description'      => $plan->description,
                'price'            => (float) $plan->price,
                'billing_interval' => $plan->billing_interval,
                'features'         => $plan->features->map(fn ($f) => [
                    'id'          => $f->id,
                    'feature_key' => $f->feature_key,
                    'value'       => $f->value,
                ]),
            ]),
        ];

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($responseData['overview']);
        }

        return Inertia::render('Tenant/Subscription', $responseData);
    }

    public function subscribe(SubscribePlanRequest $request, string $cafe_slug): JsonResponse|RedirectResponse
    {
        Gate::authorize('permission', 'subscription.update');

        $cafeId = app(TenantContext::class)->getCafeId();
        $validated = $request->validated();

        $plan = Plan::findOrFail($validated['plan_id']);

        $subscription = $this->subscriptionService->subscribeOrUpgrade(
            cafeId: $cafeId,
            plan: $plan,
            provider: $validated['provider'] ?? 'system',
            providerSubscriptionId: $validated['provider_subscription_id'] ?? ('sub_selfserve_' . time())
        );

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
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

        return redirect()->route('tenant.subscription.show', ['cafe_slug' => $cafe_slug])->with('success', "Subscribed to '{$plan->name}' successfully!");
    }

    public function cancel(Request $request, string $cafe_slug): JsonResponse|RedirectResponse
    {
        Gate::authorize('permission', 'subscription.update');

        $cafeId = app(TenantContext::class)->getCafeId();

        $subscription = $this->subscriptionService->cancelSubscription($cafeId);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message'      => 'Subscription cancelled successfully.',
                'subscription' => [
                    'id'      => $subscription->id,
                    'status'  => $subscription->status,
                    'ends_at' => $subscription->ends_at?->toIso8601String(),
                ],
            ]);
        }

        return redirect()->route('tenant.subscription.show', ['cafe_slug' => $cafe_slug])->with('success', 'Subscription cancelled successfully.');
    }
}
