<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;

class SubscriptionService
{
    public function __construct(
        protected PlanLimitService $planLimitService,
        protected AuditLogger $auditLogger
    ) {}

    public function getSubscriptionOverview(int $cafeId): array
    {
        $subscription = Subscription::where('cafe_id', $cafeId)
            ->with(['plan.features'])
            ->latest('id')
            ->first();

        $branchLimit   = $this->planLimitService->getFeatureLimit($cafeId, 'branch_limit');
        $staffLimit    = $this->planLimitService->getFeatureLimit($cafeId, 'staff_limit');
        $tableLimit    = $this->planLimitService->getFeatureLimit($cafeId, 'table_limit');
        $menuItemLimit = $this->planLimitService->getFeatureLimit($cafeId, 'menu_item_limit');

        $currentBranches  = $this->planLimitService->getCurrentBranchCount($cafeId);
        $currentStaff     = $this->planLimitService->getCurrentStaffCount($cafeId);
        $currentTables    = $this->planLimitService->getCurrentTableCount($cafeId);
        $currentMenuItems = $this->planLimitService->getCurrentMenuItemCount($cafeId);

        return [
            'subscription' => $subscription ? [
                'id'                       => $subscription->id,
                'cafe_id'                  => $subscription->cafe_id,
                'plan_id'                  => $subscription->plan_id,
                'status'                   => $subscription->status,
                'starts_at'                => $subscription->starts_at?->toIso8601String(),
                'ends_at'                  => $subscription->ends_at?->toIso8601String(),
                'trial_ends_at'            => $subscription->trial_ends_at?->toIso8601String(),
                'provider'                 => $subscription->provider,
                'provider_subscription_id' => $subscription->provider_subscription_id,
                'plan'                     => [
                    'id'               => $subscription->plan?->id,
                    'name'             => $subscription->plan?->name,
                    'slug'             => $subscription->plan?->slug,
                    'price'            => (float) $subscription->plan?->price,
                    'billing_interval' => $subscription->plan?->billing_interval,
                    'features'         => $subscription->plan?->features->map(fn ($f) => [
                        'feature_key' => $f->feature_key,
                        'value'       => $f->value,
                    ]),
                ],
            ] : null,
            'usage' => [
                'branches' => [
                    'current' => $currentBranches,
                    'limit'   => $branchLimit,
                ],
                'staff' => [
                    'current' => $currentStaff,
                    'limit'   => $staffLimit,
                ],
                'tables' => [
                    'current' => $currentTables,
                    'limit'   => $tableLimit,
                ],
                'menu_items' => [
                    'current' => $currentMenuItems,
                    'limit'   => $menuItemLimit,
                ],
            ],
        ];
    }

    public function subscribeOrUpgrade(int $cafeId, Plan $plan, ?string $provider = null, ?string $providerSubscriptionId = null): Subscription
    {
        $existing = Subscription::where('cafe_id', $cafeId)->latest('id')->first();
        $oldValues = $existing ? [
            'plan_id' => $existing->plan_id,
            'status'  => $existing->status,
        ] : null;

        $startsAt = now();
        $endsAt = $plan->billing_interval === 'yearly' ? now()->addYear() : now()->addMonth();

        if ($existing) {
            $existing->update([
                'plan_id'                  => $plan->id,
                'status'                   => 'active',
                'starts_at'                => $startsAt,
                'ends_at'                  => $endsAt,
                'provider'                 => $provider ?? $existing->provider,
                'provider_subscription_id' => $providerSubscriptionId ?? $existing->provider_subscription_id,
            ]);
            $subscription = $existing->fresh();
        } else {
            $subscription = Subscription::create([
                'cafe_id'                  => $cafeId,
                'plan_id'                  => $plan->id,
                'status'                   => 'active',
                'starts_at'                => $startsAt,
                'ends_at'                  => $endsAt,
                'provider'                 => $provider,
                'provider_subscription_id' => $providerSubscriptionId,
            ]);
        }

        $newValues = [
            'plan_id'                  => $subscription->plan_id,
            'status'                   => $subscription->status,
            'starts_at'                => $subscription->starts_at?->toIso8601String(),
            'provider'                 => $subscription->provider,
            'provider_subscription_id' => $subscription->provider_subscription_id,
        ];

        $this->auditLogger->log(
            action: 'subscription.updated',
            entityType: 'subscription',
            entityId: $subscription->id,
            cafeId: $cafeId,
            oldValues: $oldValues,
            newValues: $newValues
        );

        return $subscription;
    }

    public function cancelSubscription(int $cafeId): Subscription
    {
        $subscription = Subscription::where('cafe_id', $cafeId)
            ->whereIn('status', ['active', 'trialing', 'trial'])
            ->latest('id')
            ->firstOrFail();

        $oldValues = [
            'status'  => $subscription->status,
            'ends_at' => $subscription->ends_at?->toIso8601String(),
        ];

        $endsAt = $subscription->ends_at ?? now();

        $subscription->update([
            'status'  => 'cancelled',
            'ends_at' => $endsAt,
        ]);

        $newValues = [
            'status'  => 'cancelled',
            'ends_at' => $endsAt->toIso8601String(),
        ];

        $this->auditLogger->log(
            action: 'subscription.cancelled',
            entityType: 'subscription',
            entityId: $subscription->id,
            cafeId: $cafeId,
            oldValues: $oldValues,
            newValues: $newValues
        );

        return $subscription;
    }
}
