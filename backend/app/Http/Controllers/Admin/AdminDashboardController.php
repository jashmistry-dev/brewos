<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cafe;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $totalCafes = Cafe::count();
        $activeCafes = Cafe::where('status', 'active')->count();
        $totalPlans = Plan::count();
        $totalSubscriptions = Subscription::count();
        $activeSubscriptions = Subscription::where('status', 'active')->count();

        $recentCafes = Cafe::orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'slug', 'status', 'created_at']);

        return response()->json([
            'metrics' => [
                'total_cafes'          => $totalCafes,
                'active_cafes'         => $activeCafes,
                'total_plans'          => $totalPlans,
                'total_subscriptions'  => $totalSubscriptions,
                'active_subscriptions' => $activeSubscriptions,
            ],
            'recent_cafes' => $recentCafes,
            'message'      => 'Super Admin platform dashboard loaded.',
        ]);
    }
}
