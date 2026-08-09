<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateOrderStatusRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('permission', 'order.view');

        $query = Order::with(['table', 'orderItems.menuItem'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        if ($request->has('table_id')) {
            $query->where('table_id', $request->query('table_id'));
        }

        $orders = $query->get();

        return response()->json([
            'orders' => $orders->map(fn ($o) => [
                'id' => $o->id,
                'branch_id' => $o->branch_id,
                'table_id' => $o->table_id,
                'table_name' => $o->table?->name,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'subtotal' => (float) $o->subtotal,
                'tax' => (float) $o->tax,
                'total' => (float) $o->total,
                'created_at' => $o->created_at?->toIso8601String(),
                'items_count' => $o->orderItems->count(),
            ]),
        ]);
    }

    public function show(string $cafe_slug, int|string $order_id): JsonResponse
    {
        Gate::authorize('permission', 'order.view');

        $order = Order::with(['table', 'orderItems.menuItem'])
            ->findOrFail($order_id);

        return response()->json([
            'order' => [
                'id' => $order->id,
                'branch_id' => $order->branch_id,
                'table_id' => $order->table_id,
                'table_name' => $order->table?->name,
                'customer_id' => $order->customer_id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'subtotal' => (float) $order->subtotal,
                'tax' => (float) $order->tax,
                'discount' => (float) $order->discount,
                'total' => (float) $order->total,
                'created_at' => $order->created_at?->toIso8601String(),
                'items' => $order->orderItems->map(fn ($oi) => [
                    'id' => $oi->id,
                    'menu_item_id' => $oi->menu_item_id,
                    'name' => $oi->menuItem?->name,
                    'quantity' => $oi->quantity,
                    'unit_price' => (float) $oi->unit_price,
                    'total' => (float) $oi->total,
                ]),
            ],
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, string $cafe_slug, int|string $order_id): JsonResponse
    {
        Gate::authorize('permission', 'order.update');

        $order = Order::findOrFail($order_id);
        $order->update([
            'status' => $request->validated('status'),
        ]);

        return response()->json([
            'message' => 'Order status updated successfully.',
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
            ],
        ]);
    }
}
