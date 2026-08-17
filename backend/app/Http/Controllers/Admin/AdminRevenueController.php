<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cafe;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminRevenueController extends Controller
{
    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $totalOrdersRevenue = (float) Order::where('status', '!=', 'cancelled')->sum('total');

        $activeSubscriptions = Subscription::where('status', 'active')->with('plan')->get();

        $mrr = $activeSubscriptions->sum(fn ($s) => (float) ($s->plan?->price ?? 0.00));
        $arr = $mrr * 12;

        $revenueByPlan = Plan::withCount(['subscriptions' => fn ($q) => $q->where('status', 'active')])
            ->get()
            ->map(fn ($p) => [
                'plan_id'     => $p->id,
                'name'        => $p->name,
                'price'       => (float) $p->price,
                'sub_count'   => $p->subscriptions_count,
                'mrr_contrib' => round((float) $p->price * $p->subscriptions_count, 2),
            ]);

        $topCafesByRevenue = Cafe::withSum(['orders' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total')
            ->orderBy('orders_sum_total', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'slug', 'status'])
            ->map(fn ($c) => [
                'id'            => $c->id,
                'name'          => $c->name,
                'slug'          => $c->slug,
                'status'        => $c->status,
                'total_revenue' => (float) ($c->orders_sum_total ?? 0.00),
            ]);

        $data = [
            'metrics' => [
                'total_platform_revenue' => round($totalOrdersRevenue, 2),
                'mrr'                    => round($mrr, 2),
                'arr'                    => round($arr, 2),
                'active_subscriptions'   => $activeSubscriptions->count(),
            ],
            'revenue_by_plan'     => $revenueByPlan,
            'top_cafes'           => $topCafesByRevenue,
        ];

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($data);
        }

        return Inertia::render('Admin/Revenue', $data);
    }
}
