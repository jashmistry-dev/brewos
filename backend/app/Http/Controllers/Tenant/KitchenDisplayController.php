<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KitchenDisplayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('permission', 'order.view');

        $activeStatuses = ['pending', 'confirmed', 'preparing', 'ready'];

        $query = Order::with(['table', 'orderItems.menuItem'])
            ->whereIn('status', $activeStatuses)
            ->orderBy('created_at', 'asc');

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        $orders = $query->get();

        return response()->json([
            'orders' => $orders->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'table_name' => $o->table?->name,
                'status' => $o->status,
                'created_at' => $o->created_at?->toIso8601String(),
                'elapsed_minutes' => (int) $o->created_at?->diffInMinutes(now()),
                'items' => $o->orderItems->map(fn ($oi) => [
                    'id' => $oi->id,
                    'name' => $oi->menuItem?->name,
                    'quantity' => $oi->quantity,
                ]),
            ]),
        ]);
    }
}
