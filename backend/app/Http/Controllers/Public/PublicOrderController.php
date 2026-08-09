<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\CreatePublicOrderRequest;
use App\Models\Cafe;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PublicOrderController extends Controller
{
    public function menu(string $qr_token): JsonResponse
    {
        $table = RestaurantTable::with(['branch.cafe'])
            ->where('qr_token', $qr_token)
            ->firstOrFail();

        $cafe = $table->branch->cafe;

        $categories = Category::where('cafe_id', $cafe->id)
            ->where('status', 'active')
            ->orderBy('sort_order', 'asc')
            ->get();

        $menuItems = MenuItem::where('cafe_id', $cafe->id)
            ->whereIn('status', ['active', 'available'])
            ->orderBy('sort_order', 'asc')
            ->get()
            ->groupBy('category_id');

        return response()->json([
            'cafe' => [
                'name' => $cafe->name,
                'slug' => $cafe->slug,
            ],
            'branch' => [
                'name' => $table->branch->name,
            ],
            'table' => [
                'name' => $table->name,
                'capacity' => $table->capacity,
            ],
            'categories' => $categories->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'description' => $cat->description,
                'sort_order' => $cat->sort_order,
                'items' => ($menuItems->get($cat->id) ?? collect())->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'price' => (float) $item->price,
                    'image' => $item->image,
                    'status' => $item->status,
                ]),
            ]),
        ]);
    }

    public function store(CreatePublicOrderRequest $request, string $qr_token): JsonResponse
    {
        $table = RestaurantTable::with(['branch.cafe'])
            ->where('qr_token', $qr_token)
            ->firstOrFail();

        $cafe = $table->branch->cafe;
        $submittedItems = $request->validated('items');

        // Extract menu item IDs and load from database belonging to this cafe
        $itemIds = array_column($submittedItems, 'menu_item_id');
        $menuItems = MenuItem::where('cafe_id', $cafe->id)
            ->whereIn('status', ['active', 'available'])
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        // Validate that all submitted item IDs exist and belong to this cafe
        foreach ($submittedItems as $submitted) {
            $itemId = $submitted['menu_item_id'];
            if (! $menuItems->has($itemId)) {
                return response()->json([
                    'message' => 'The selected menu item is invalid, unavailable, or does not belong to this cafe.',
                    'errors' => [
                        'items' => ['Selected item ID ' . $itemId . ' is invalid or unavailable for this cafe.'],
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        // Calculate totals server-side
        $subtotal = 0.00;
        $orderItemsData = [];

        foreach ($submittedItems as $submitted) {
            /** @var MenuItem $item */
            $item = $menuItems->get($submitted['menu_item_id']);
            $qty = (int) $submitted['quantity'];
            $unitPrice = (float) $item->price;
            $lineTotal = round($unitPrice * $qty, 2);

            $subtotal += $lineTotal;

            $orderItemsData[] = [
                'menu_item_id' => $item->id,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount' => 0.00,
                'tax' => 0.00,
                'total' => $lineTotal,
            ];
        }

        $tax = 0.00;
        $discount = 0.00;
        $total = round($subtotal + $tax - $discount, 2);
        $customerId = $request->validated('customer_id');

        $order = DB::transaction(function () use ($cafe, $table, $customerId, $subtotal, $tax, $discount, $total, $orderItemsData) {
            // Lock the parent Cafe row exclusively to serialize order number generation per cafe across concurrent requests
            Cafe::where('id', $cafe->id)->lockForUpdate()->first();

            $datePrefix = date('Ymd');

            $count = Order::where('cafe_id', $cafe->id)
                ->whereDate('created_at', today())
                ->count();

            $orderNumber = sprintf('ORD-%s-%04d', $datePrefix, $count + 1);

            $order = Order::create([
                'cafe_id' => $cafe->id,
                'branch_id' => $table->branch_id,
                'table_id' => $table->id,
                'customer_id' => $customerId,
                'order_number' => $orderNumber,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'payment_status' => 'unpaid',
            ]);

            foreach ($orderItemsData as $itemData) {
                $order->orderItems()->create($itemData);
            }

            return $order;
        });

        $order->load(['orderItems.menuItem']);

        return response()->json([
            'message' => 'Order created successfully.',
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'subtotal' => (float) $order->subtotal,
                'tax' => (float) $order->tax,
                'total' => (float) $order->total,
                'items' => $order->orderItems->map(fn ($oi) => [
                    'id' => $oi->id,
                    'menu_item_id' => $oi->menu_item_id,
                    'name' => $oi->menuItem?->name,
                    'quantity' => $oi->quantity,
                    'unit_price' => (float) $oi->unit_price,
                    'total' => (float) $oi->total,
                ]),
            ],
        ], Response::HTTP_CREATED);
    }
}
