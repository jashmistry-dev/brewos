<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cafe;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminDashboardController extends Controller
{
    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $totalCafes = Cafe::count();
        $activeCafes = Cafe::where('status', 'active')->count();
        $totalPlans = Plan::count();
        $totalSubscriptions = Subscription::count();
        $activeSubscriptions = Subscription::where('status', 'active')->count();

        $recentCafes = Cafe::orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'slug', 'status', 'created_at']);

        $data = [
            'metrics' => [
                'total_cafes'          => $totalCafes,
                'active_cafes'         => $activeCafes,
                'total_plans'          => $totalPlans,
                'total_subscriptions'  => $totalSubscriptions,
                'active_subscriptions' => $activeSubscriptions,
            ],
            'recent_cafes' => $recentCafes,
            'message'      => 'Super Admin platform dashboard loaded.',
        ];

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($data);
        }

        return Inertia::render('Admin/Dashboard', $data);
    }
}
