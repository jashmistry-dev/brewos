<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KitchenDisplayController extends Controller
{
    public function index(Request $request): JsonResponse|\Inertia\Response
    {
        if (! (Gate::allows('permission', 'order.view') || Gate::allows('permission', 'order.kitchen.view'))) {
            Gate::authorize('permission', 'order.kitchen.view');
        }

        $activeStatuses = ['pending', 'kitchen_pending', 'confirmed', 'preparing', 'ready'];

        $query = Order::with(['table', 'orderItems.menuItem'])
            ->whereIn('status', $activeStatuses)
            ->where(function ($q) {
                $q->where('order_type', '!=', 'dine_in_qr')
                  ->orWhere('payment_status', 'paid')
                  ->orWhereHas('cafe', fn ($q2) => $q2->where('require_payment_before_kitchen', false));
            })
            ->orderBy('created_at', 'asc');

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        $orders = $query->get();

        $formattedOrders = $orders->map(fn ($o) => [
            'id' => $o->id,
            'order_number' => (string) ($o->public_order_number ?? $o->order_number),
            'table_name' => $o->table?->name,
            'status' => $o->status,
            'created_at' => $o->created_at?->toIso8601String(),
            'elapsed_minutes' => (int) $o->created_at?->diffInMinutes(now()),
            'items' => $o->orderItems->map(fn ($oi) => [
                'id' => $oi->id,
                'name' => $oi->menuItem?->name,
                'quantity' => $oi->quantity,
            ]),
        ]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'orders' => $formattedOrders,
            ]);
        }

        return \Inertia\Inertia::render('Tenant/KitchenDisplay', [
            'orders' => $formattedOrders,
        ]);
    }
}
