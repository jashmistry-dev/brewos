<?php

namespace App\Services;

use App\Models\CafeUser;
use App\Models\PlanFeature;
use App\Models\RestaurantTable;
use App\Models\Subscription;

class PlanLimitService
{
    /**
     * Get the limit value for a specific feature key for the cafe's active subscription.
     * Returns null if no active subscription, or if feature key is unlimited / not configured.
     */
    public function getFeatureLimit(int $cafeId, string $featureKey): ?int
    {
        $subscription = Subscription::where('cafe_id', $cafeId)
            ->whereIn('status', ['active', 'trial', 'trialing'])
            ->with(['plan.features'])
            ->latest('id')
            ->first();

        if (! $subscription || ! $subscription->plan) {
            return null;
        }

        $feature = $subscription->plan->features
            ->firstWhere('feature_key', $featureKey);

        if (! $feature) {
            return null;
        }

        if ($feature->value === 'unlimited' || $feature->value === '-1') {
            return null;
        }

        return is_numeric($feature->value) ? (int) $feature->value : null;
    }

    public function getCurrentStaffCount(int $cafeId): int
    {
        return CafeUser::where('cafe_id', $cafeId)
            ->where('status', 'active')
            ->count();
    }

    public function getCurrentTableCount(int $cafeId): int
    {
        return RestaurantTable::whereHas('branch', function ($query) use ($cafeId) {
            $query->where('cafe_id', $cafeId);
        })->count();
    }

    public function getCurrentBranchCount(int $cafeId): int
    {
        return \App\Models\Branch::where('cafe_id', $cafeId)->count();
    }

    public function getCurrentMenuItemCount(int $cafeId): int
    {
        return \App\Models\MenuItem::where('cafe_id', $cafeId)->count();
    }

    public function hasReachedStaffLimit(int $cafeId): bool
    {
        $limit = $this->getFeatureLimit($cafeId, 'staff_limit');

        if ($limit === null) {
            return false;
        }

        return $this->getCurrentStaffCount($cafeId) >= $limit;
    }

    public function hasReachedTableLimit(int $cafeId): bool
    {
        $limit = $this->getFeatureLimit($cafeId, 'table_limit');

        if ($limit === null) {
            return false;
        }

        return $this->getCurrentTableCount($cafeId) >= $limit;
    }

    public function hasReachedBranchLimit(int $cafeId): bool
    {
        $limit = $this->getFeatureLimit($cafeId, 'branch_limit');

        if ($limit === null) {
            return false;
        }

        return $this->getCurrentBranchCount($cafeId) >= $limit;
    }

    public function hasReachedMenuItemLimit(int $cafeId): bool
    {
        $limit = $this->getFeatureLimit($cafeId, 'menu_item_limit');

        if ($limit === null) {
            return false;
        }

        return $this->getCurrentMenuItemCount($cafeId) >= $limit;
    }
}
