<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCafeStatusRequest;
use App\Models\Cafe;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use App\Models\Plan;
use App\Models\Subscription;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Http\RedirectResponse;

class AdminCafeController extends Controller
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $query = Cafe::with([
            'subscriptions.plan',
            'cafeUsers' => function ($q) {
                $q->whereHas('role', fn ($r) => $r->where('slug', 'cafe-owner'))->with('user');
            },
        ])->withCount(['branches', 'cafeUsers', 'orders', 'customers', 'subscriptions']);

        // Search by name, slug, or email
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by Cafe status
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        // Filter by Subscription status
        if ($request->filled('subscription_status')) {
            $subStatus = $request->query('subscription_status');
            $query->whereHas('subscriptions', function ($q) use ($subStatus) {
                $q->where('status', $subStatus);
            });
        }

        // Filter by Plan ID
        if ($request->filled('plan_id')) {
            $planId = $request->query('plan_id');
            $query->whereHas('subscriptions', function ($q) use ($planId) {
                $q->where('plan_id', $planId);
            });
        }

        $cafes = $query->orderBy('created_at', 'desc')->get();

        // Calculate SaaS Summary Metrics
        $totalCafes     = Cafe::count();
        $activeCafes    = Cafe::where('status', 'active')->count();
        $suspendedCafes = Cafe::where('status', 'suspended')->count();
        $totalRevenue   = \App\Models\Order::where('payment_status', 'paid')->sum('total');

        $mappedCafes = $cafes->map(function ($cafe) {
            $ownerMembership = $cafe->cafeUsers->first();
            $ownerUser       = $ownerMembership?->user;
            $activeSub       = $cafe->subscriptions->sortByDesc('created_at')->first();
            $plan            = $activeSub?->plan;

            // Compute non-disruptive expiry recommendation
            $expiryRecommendation = 'active';
            if ($activeSub && $activeSub->ends_at) {
                if ($activeSub->ends_at->isPast() || $activeSub->status === 'expired') {
                    $expiryRecommendation = 'expired';
                } elseif ($activeSub->ends_at->diffInDays(now()) <= 7) {
                    $expiryRecommendation = 'expiring_soon';
                }
            }

            // Real Revenue for this cafe
            $cafeRevenue = \App\Models\Order::where('cafe_id', $cafe->id)
                ->where('payment_status', 'paid')
                ->sum('total');

            return [
                'id'                     => $cafe->id,
                'name'                   => $cafe->name,
                'slug'                   => $cafe->slug,
                'email'                  => $cafe->email,
                'phone'                  => $cafe->phone,
                'status'                 => $cafe->status,
                'owner'                  => $ownerUser ? [
                    'name'  => $ownerUser->name,
                    'email' => $ownerUser->email,
                    'phone' => $ownerUser->phone,
                ] : null,
                'plan'                   => $plan ? [
                    'id'               => $plan->id,
                    'name'             => $plan->name,
                    'slug'             => $plan->slug,
                    'price'            => $plan->price,
                    'billing_interval' => $plan->billing_interval,
                ] : null,
                'subscription'           => $activeSub ? [
                    'id'                    => $activeSub->id,
                    'status'                => $activeSub->status,
                    'starts_at'             => $activeSub->starts_at?->toIso8601String(),
                    'ends_at'               => $activeSub->ends_at?->toIso8601String(),
                    'trial_ends_at'         => $activeSub->trial_ends_at?->toIso8601String(),
                    'expiry_recommendation' => $expiryRecommendation,
                ] : null,
                'branches_count'         => $cafe->branches_count,
                'users_count'            => $cafe->cafe_users_count,
                'customers_count'        => $cafe->customers_count,
                'orders_count'           => $cafe->orders_count,
                'subscriptions_count'    => $cafe->subscriptions_count,
                'revenue'                => (float) $cafeRevenue,
                'created_at'             => $cafe->created_at?->toIso8601String(),
            ];
        });

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'cafes' => $mappedCafes,
            ]);
        }

        $allPlans = Plan::get(['id', 'name', 'slug']);

        return Inertia::render('Admin/Cafes', [
            'cafes'   => $mappedCafes,
            'plans'   => $allPlans,
            'metrics' => [
                'total_cafes text' => $totalCafes,
                'total_cafes'      => $totalCafes,
                'active_cafes'     => $activeCafes,
                'suspended_cafes'  => $suspendedCafes,
                'total_revenue'    => (float) $totalRevenue,
            ],
            'filters' => $request->only(['search', 'status', 'subscription_status', 'plan_id']),
        ]);
    }

    public function show(int|string $cafe_id, Request $request): JsonResponse|InertiaResponse
    {
        $cafe = Cafe::with([
            'branches',
            'subscriptions.plan.features',
            'cafeUsers' => function ($q) {
                $q->whereHas('role', fn ($r) => $r->where('slug', 'cafe-owner'))->with('user');
            },
        ])->withCount(['branches', 'cafeUsers', 'orders', 'customers', 'menuItems'])->findOrFail($cafe_id);

        $ownerMembership = $cafe->cafeUsers->first();
        $ownerUser       = $ownerMembership?->user;
        $activeSub       = $cafe->subscriptions->sortByDesc('created_at')->first();
        $plan            = $activeSub?->plan;

        // Compute expiry recommendation
        $expiryRecommendation = 'active';
        if ($activeSub && $activeSub->ends_at) {
            if ($activeSub->ends_at->isPast() || $activeSub->status === 'expired') {
                $expiryRecommendation = 'expired';
            } elseif ($activeSub->ends_at->diffInDays(now()) <= 7) {
                $expiryRecommendation = 'expiring_soon';
            }
        }

        // Usage vs Plan Limits
        $featuresMap = [];
        if ($plan) {
            foreach ($plan->features as $feature) {
                $featuresMap[$feature->feature_key] = $feature->value;
            }
        }

        $branchLimit = isset($featuresMap['branch_limit']) ? ($featuresMap['branch_limit'] === 'unlimited' ? 'unlimited' : (int) $featuresMap['branch_limit']) : 'unlimited';
        $staffLimit  = isset($featuresMap['staff_limit']) ? ($featuresMap['staff_limit'] === 'unlimited' ? 'unlimited' : (int) $featuresMap['staff_limit']) : 'unlimited';
        $tableLimit  = isset($featuresMap['table_limit']) ? ($featuresMap['table_limit'] === 'unlimited' ? 'unlimited' : (int) $featuresMap['table_limit']) : 'unlimited';
        $menuLimit   = isset($featuresMap['menu_item_limit']) ? ($featuresMap['menu_item_limit'] === 'unlimited' ? 'unlimited' : (int) $featuresMap['menu_item_limit']) : 'unlimited';

        // Revenue
        $cafeRevenue = \App\Models\Order::where('cafe_id', $cafe->id)
            ->where('payment_status', 'paid')
            ->sum('total');

        $tableCount = \App\Models\RestaurantTable::whereHas('branch', fn ($q) => $q->where('cafe_id', $cafe->id))->count();

        // Lifecycle Audit Logs for this cafe
        $auditLogs = \App\Models\AuditLog::with('user:id,name,email')
            ->where('cafe_id', $cafe->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $responseData = [
            'cafe' => [
                'id'                 => $cafe->id,
                'name'               => $cafe->name,
                'slug'               => $cafe->slug,
                'email'              => $cafe->email,
                'phone'              => $cafe->phone,
                'status'             => $cafe->status,
                'notes'              => $cafe->notes,
                'timezone'           => $cafe->timezone,
                'currency'           => $cafe->currency,
                'branches_count'     => $cafe->branches_count,
                'users_count'        => $cafe->cafe_users_count,
                'customers_count'    => $cafe->customers_count,
                'orders_count'       => $cafe->orders_count,
                'menu_items_count'   => $cafe->menu_items_count,
                'branches'           => $cafe->branches,
                'subscriptions'      => $cafe->subscriptions,
                'created_at'         => $cafe->created_at?->toIso8601String(),
                'updated_at'         => $cafe->updated_at?->toIso8601String(),
            ],
            'owner' => $ownerUser ? [
                'id'    => $ownerUser->id,
                'name'  => $ownerUser->name,
                'email' => $ownerUser->email,
                'phone' => $ownerUser->phone,
            ] : null,
            'subscription' => $activeSub ? [
                'id'                       => $activeSub->id,
                'status'                   => $activeSub->status,
                'starts_at'                => $activeSub->starts_at?->toIso8601String(),
                'ends_at'                  => $activeSub->ends_at?->toIso8601String(),
                'trial_ends_at'            => $activeSub->trial_ends_at?->toIso8601String(),
                'provider'                 => $activeSub->provider,
                'provider_subscription_id' => $activeSub->provider_subscription_id,
                'expiry_recommendation'    => $expiryRecommendation,
                'plan'                     => $plan ? [
                    'id'               => $plan->id,
                    'name'             => $plan->name,
                    'slug'             => $plan->slug,
                    'price'            => $plan->price,
                    'billing_interval' => $plan->billing_interval,
                ] : null,
            ] : null,
            'plans' => Plan::get(['id', 'name', 'slug', 'price', 'billing_interval']),
            'usage' => [
                'branches'   => ['current' => $cafe->branches_count, 'limit' => $branchLimit],
                'staff'      => ['current' => $cafe->cafe_users_count, 'limit' => $staffLimit],
                'tables'     => ['current' => $tableCount, 'limit' => $tableLimit],
                'menu'       => ['current' => $cafe->menu_items_count, 'limit' => $menuLimit],
                'menu_items' => ['current' => $cafe->menu_items_count, 'limit' => $menuLimit],
            ],
            'metrics' => [
                'customers_count' => $cafe->customers_count,
                'orders_count'    => $cafe->orders_count,
                'total_revenue'   => (float) $cafeRevenue,
            ],
            'audit_logs' => $auditLogs,
        ];

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'cafe' => $responseData['cafe'],
            ]);
        }

        return Inertia::render('Admin/CafeDetails', $responseData);
    }

    public function updateStatus(UpdateCafeStatusRequest $request, int|string $cafe_id): JsonResponse|RedirectResponse
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

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
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

        return redirect()->back()->with('success', "Cafe '{$cafe->name}' status updated to {$newStatus}.");
    }

    public function destroy(int|string $cafe_id, Request $request): JsonResponse|RedirectResponse
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

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Cafe deleted successfully.',
            ]);
        }

        return redirect()->route('admin.cafes.index')->with('success', "Cafe '{$cafe->name}' deleted successfully.");
    }

    public function updateNotes(Request $request, int|string $cafe_id): JsonResponse|RedirectResponse
    {
        $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $cafe = Cafe::findOrFail($cafe_id);
        $oldNotes = $cafe->notes;
        $newNotes = $request->input('notes');

        $cafe->update(['notes' => $newNotes]);

        $this->auditLogger->log(
            action: 'cafe.notes_updated',
            entityType: 'cafe',
            entityId: $cafe->id,
            cafeId: $cafe->id,
            oldValues: ['notes' => $oldNotes],
            newValues: ['notes' => $newNotes]
        );

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Internal admin notes updated successfully.', 'notes' => $newNotes]);
        }

        return redirect()->back()->with('success', 'Internal notes updated successfully.');
    }

    public function extendSubscription(Request $request, int|string $cafe_id): JsonResponse|RedirectResponse
    {
        $request->validate([
            'new_ends_at' => ['required', 'date', 'after:now'],
            'reason'      => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $cafe = Cafe::findOrFail($cafe_id);
        $sub = Subscription::where('cafe_id', $cafe->id)->latest('id')->first();

        if (! $sub) {
            $defaultPlan = Plan::where('status', 'active')->first() ?? Plan::first();
            $sub = new Subscription([
                'cafe_id'   => $cafe->id,
                'plan_id'   => $defaultPlan?->id,
                'status'    => 'active',
                'starts_at' => now(),
                'provider'  => 'manual_override',
            ]);
        }

        $oldEndsAt = $sub->ends_at?->toIso8601String();
        $newEndsAt = \Carbon\Carbon::parse($request->input('new_ends_at'));

        $sub->ends_at = $newEndsAt;
        $sub->status  = 'active';
        $sub->save();

        $this->auditLogger->log(
            action: 'subscription.extended',
            entityType: 'subscription',
            entityId: $sub->id,
            cafeId: $cafe->id,
            oldValues: ['ends_at' => $oldEndsAt],
            newValues: ['ends_at' => $newEndsAt->toIso8601String(), 'reason' => $request->input('reason')]
        );

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Subscription extended successfully.', 'ends_at' => $newEndsAt->toIso8601String()]);
        }

        return redirect()->back()->with('success', "Subscription extended to {$newEndsAt->format('Y-m-d')}.");
    }

    public function changePlan(Request $request, int|string $cafe_id): JsonResponse|RedirectResponse
    {
        $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'reason'  => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $cafe = Cafe::findOrFail($cafe_id);
        $newPlanId = (int) $request->input('plan_id');
        $plan = Plan::findOrFail($newPlanId);

        $sub = Subscription::where('cafe_id', $cafe->id)->latest('id')->first();
        $oldPlanId = $sub?->plan_id;

        $endsAt = $plan->billing_interval === 'yearly' ? now()->addYear() : now()->addMonth();

        if ($sub) {
            $sub->update([
                'plan_id'   => $newPlanId,
                'status'    => 'active',
                'starts_at' => now(),
                'ends_at'   => $endsAt,
                'provider'  => 'manual_override',
            ]);
        } else {
            $sub = Subscription::create([
                'cafe_id'                  => $cafe->id,
                'plan_id'                  => $newPlanId,
                'status'                   => 'active',
                'starts_at'                => now(),
                'ends_at'                  => $endsAt,
                'provider'                 => 'manual_override',
                'provider_subscription_id' => 'override_' . $cafe->slug . '_' . time(),
            ]);
        }

        $this->auditLogger->log(
            action: 'subscription.plan_changed',
            entityType: 'subscription',
            entityId: $sub->id,
            cafeId: $cafe->id,
            oldValues: ['plan_id' => $oldPlanId],
            newValues: ['plan_id' => $newPlanId, 'reason' => $request->input('reason')]
        );

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Subscription plan assigned successfully.', 'subscription_id' => $sub->id]);
        }

        return redirect()->back()->with('success', "Subscription plan changed to {$plan->name}.");
    }

    public function reactivateSubscription(Request $request, int|string $cafe_id): JsonResponse|RedirectResponse
    {
        $request->validate([
            'new_ends_at' => ['required', 'date', 'after:now'],
            'reason'      => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $cafe = Cafe::findOrFail($cafe_id);
        $sub = Subscription::where('cafe_id', $cafe->id)->latest('id')->first();

        if (! $sub) {
            $defaultPlan = Plan::where('status', 'active')->first() ?? Plan::first();
            $sub = new Subscription([
                'cafe_id'   => $cafe->id,
                'plan_id'   => $defaultPlan?->id,
                'provider'  => 'manual_override',
            ]);
        }

        $oldStatus = $sub->status;
        $oldEndsAt = $sub->ends_at?->toIso8601String();
        $newEndsAt = \Carbon\Carbon::parse($request->input('new_ends_at'));

        $sub->status    = 'active';
        $sub->starts_at = $sub->starts_at ?? now();
        $sub->ends_at   = $newEndsAt;
        $sub->save();

        $this->auditLogger->log(
            action: 'subscription.reactivated',
            entityType: 'subscription',
            entityId: $sub->id,
            cafeId: $cafe->id,
            oldValues: ['status' => $oldStatus, 'ends_at' => $oldEndsAt],
            newValues: ['status' => 'active', 'ends_at' => $newEndsAt->toIso8601String(), 'reason' => $request->input('reason')]
        );

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Subscription reactivated successfully.']);
        }

        return redirect()->back()->with('success', 'Subscription reactivated successfully.');
    }

    public function cancelSubscription(Request $request, int|string $cafe_id): JsonResponse|RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $cafe = Cafe::findOrFail($cafe_id);
        $sub = Subscription::where('cafe_id', $cafe->id)->latest('id')->firstOrFail();

        $oldStatus = $sub->status;
        $oldEndsAt = $sub->ends_at?->toIso8601String();

        $sub->update([
            'status'  => 'cancelled',
            'ends_at' => now(),
        ]);

        $this->auditLogger->log(
            action: 'subscription.cancelled',
            entityType: 'subscription',
            entityId: $sub->id,
            cafeId: $cafe->id,
            oldValues: ['status' => $oldStatus, 'ends_at' => $oldEndsAt],
            newValues: ['status' => 'cancelled', 'ends_at' => now()->toIso8601String(), 'reason' => $request->input('reason')]
        );

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Subscription cancelled successfully.']);
        }

        return redirect()->back()->with('success', 'Subscription cancelled by Super Admin.');
    }
}
