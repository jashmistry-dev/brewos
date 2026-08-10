<?php

namespace App\Services;

use App\Exceptions\EntitlementException;
use App\Models\Branch;
use App\Models\CafeUser;
use App\Models\MenuItem;
use App\Models\RestaurantTable;
use App\Models\Subscription;

class EntitlementService
{
    public function __construct(
        protected PlanLimitService $planLimitService,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Evaluates if the cafe has an active subscription or valid trial.
     */
    public function isSubscriptionValid(int $cafeId): bool
    {
        $subscription = Subscription::where('cafe_id', $cafeId)
            ->latest('id')
            ->first();

        if (! $subscription) {
            return true;
        }

        if (in_array($subscription->status, ['active', 'trial', 'trialing'])) {
            if ($subscription->ends_at && $subscription->ends_at->isPast()) {
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * Get details on current subscription state and effective entitlement access.
     */
    public function getEntitlementOverview(int $cafeId): array
    {
        $subscription = Subscription::where('cafe_id', $cafeId)
            ->with(['plan.features'])
            ->latest('id')
            ->first();

        $isValid = $this->isSubscriptionValid($cafeId);

        $branchLimit   = $this->planLimitService->getFeatureLimit($cafeId, 'branch_limit');
        $staffLimit    = $this->planLimitService->getFeatureLimit($cafeId, 'staff_limit');
        $tableLimit    = $this->planLimitService->getFeatureLimit($cafeId, 'table_limit');
        $menuItemLimit = $this->planLimitService->getFeatureLimit($cafeId, 'menu_item_limit');

        $currentBranches  = Branch::where('cafe_id', $cafeId)->count();
        $currentStaff     = CafeUser::where('cafe_id', $cafeId)->where('status', 'active')->count();
        $currentTables    = RestaurantTable::whereHas('branch', fn ($q) => $q->where('cafe_id', $cafeId))->count();
        $currentMenuItems = MenuItem::where('cafe_id', $cafeId)->count();

        return [
            'is_valid_subscription' => $isValid,
            'subscription'           => $subscription ? [
                'id'         => $subscription->id,
                'status'     => $subscription->status,
                'starts_at'  => $subscription->starts_at?->toIso8601String(),
                'ends_at'    => $subscription->ends_at?->toIso8601String(),
                'plan_name'  => $subscription->plan?->name,
                'plan_slug'  => $subscription->plan?->slug,
            ] : null,
            'limits' => [
                'branches'   => ['current' => $currentBranches,  'limit' => $branchLimit],
                'staff'      => ['current' => $currentStaff,     'limit' => $staffLimit],
                'tables'     => ['current' => $currentTables,    'limit' => $tableLimit],
                'menu_items' => ['current' => $currentMenuItems, 'limit' => $menuItemLimit],
            ],
        ];
    }

    /**
     * Checks capacity against plan feature limit and throws EntitlementException if reached.
     */
    public function checkCapacity(int $cafeId, string $featureKey, int $currentCount, string $displayName): void
    {
        $limit = $this->planLimitService->getFeatureLimit($cafeId, $featureKey);

        if ($limit !== null && $currentCount >= $limit) {
            $this->auditLogger->log(
                action: 'entitlement.denied',
                entityType: 'subscription',
                entityId: null,
                cafeId: $cafeId,
                oldValues: null,
                newValues: [
                    'feature_key'   => $featureKey,
                    'limit'         => $limit,
                    'current_usage' => $currentCount,
                ]
            );

            throw new EntitlementException(
                message: "You have reached the maximum allowed {$displayName} limit ({$limit}) for your subscription plan. Please upgrade your plan to add more.",
                featureKey: $featureKey,
                limit: $limit,
                currentUsage: $currentCount
            );
        }
    }

    public function checkBranchLimit(int $cafeId): void
    {
        $current = Branch::where('cafe_id', $cafeId)->count();
        $this->checkCapacity($cafeId, 'branch_limit', $current, 'branches');
    }

    public function checkStaffLimit(int $cafeId): void
    {
        $current = CafeUser::where('cafe_id', $cafeId)->where('status', 'active')->count();
        $this->checkCapacity($cafeId, 'staff_limit', $current, 'staff members');
    }

    public function checkTableLimit(int $cafeId): void
    {
        $current = RestaurantTable::whereHas('branch', fn ($q) => $q->where('cafe_id', $cafeId))->count();
        $this->checkCapacity($cafeId, 'table_limit', $current, 'tables');
    }

    public function checkMenuItemLimit(int $cafeId): void
    {
        $current = MenuItem::where('cafe_id', $cafeId)->count();
        $this->checkCapacity($cafeId, 'menu_item_limit', $current, 'menu items');
    }
}
