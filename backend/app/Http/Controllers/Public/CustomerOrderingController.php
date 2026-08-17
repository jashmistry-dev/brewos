<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderingSession;
use App\Services\CustomerOrderingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CustomerOrderingController extends Controller
{
    public function __construct(
        protected CustomerOrderingService $orderingService
    ) {}

    /**
     * Renders public customer mobile ordering page or returns JSON data.
     */
    public function showMenu(Request $request, string $cafe_slug, string $qr_token): JsonResponse|InertiaResponse
    {
        $userLat = $request->has('lat') ? (float) $request->query('lat') : null;
        $userLng = $request->has('lng') ? (float) $request->query('lng') : null;

        try {
            $resolved = $this->orderingService->resolveTableAndSession(
                cafeSlug: $cafe_slug,
                qrToken: $qr_token,
                userLat: $userLat,
                userLng: $userLng,
                ip: $request->ip(),
                userAgent: $request->userAgent()
            );
        } catch (\App\Exceptions\EntitlementException $e) {
            $cafe = \App\Models\Cafe::where('slug', $cafe_slug)->first();
            $cafeName = $cafe ? $cafe->name : 'Cafe';

            if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'message'    => 'Online ordering for this cafe is temporarily unavailable. Please contact the cafe staff for assistance.',
                    'error_code' => 'ORDERING_UNAVAILABLE',
                ], 403);
            }

            return Inertia::render('Customer/CustomerOrderingUnavailable', [
                'cafeName' => $cafeName,
                'message'  => 'Online ordering for this cafe is temporarily unavailable. Please contact the cafe staff for assistance.',
            ]);
        }

        $cafe = $resolved['cafe'];
        $branch = $resolved['branch'];
        $table = $resolved['table'];
        $session = $resolved['session'];

        $categories = Category::where('cafe_id', $cafe->id)
            ->orderBy('sort_order', 'asc')
            ->get(['id', 'name']);

        $menuItems = MenuItem::where('cafe_id', $cafe->id)
            ->where('status', 'active')
            ->get(['id', 'category_id', 'name', 'description', 'price', 'image', 'status']);

        $latestOrder = Order::where('ordering_session_id', $session->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('created_at', 'desc')
            ->first();

        $responseData = [
            'active_order' => $latestOrder ? [
                'order_number' => $latestOrder->order_number,
                'status'       => $latestOrder->status,
            ] : null,
            'cafe' => [
                'id'                              => $cafe->id,
                'name'                            => $cafe->name,
                'slug'                            => $cafe->slug,
                'logo_url'                        => $cafe->logo_url,
                'currency'                        => $cafe->currency ?? '₹',
                'tax_rate'                        => (float) ($cafe->tax_rate ?? 0.0),
                'pay_at_counter_enabled'          => (bool) $cafe->pay_at_counter_enabled,
                'online_payment_enabled'          => (bool) $cafe->online_payment_enabled,
                'allow_customer_reorder'          => (bool) $cafe->allow_customer_reorder,
                'call_staff_enabled'              => (bool) $cafe->call_staff_enabled,
                'request_bill_enabled'            => (bool) $cafe->request_bill_enabled,
                'require_location'                => (bool) $cafe->require_location,
                'location_radius_meters'          => (int) ($cafe->location_radius_meters ?? 100),
            ],
            'branch' => [
                'id'   => $branch->id,
                'name' => $branch->name,
                'slug' => $branch->slug,
            ],
            'table' => [
                'id'       => $table->id,
                'name'     => $table->name,
                'capacity' => $table->capacity,
                'qr_token' => $table->qr_token,
            ],
            'session' => [
                'token'      => $session->session_token,
                'expires_at' => $session->expires_at->toIso8601String(),
            ],
            'categories' => $categories,
            'menu_items' => $menuItems->map(fn ($item) => [
                'id'          => $item->id,
                'category_id' => $item->category_id,
                'name'        => $item->name,
                'description' => $item->description,
                'price'       => (float) $item->price,
                'image_url'   => $item->image_url,
                'status'      => $item->status,
            ]),
        ];

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($responseData);
        }

        return Inertia::render('Customer/CustomerOrder', $responseData);
    }

    /**
     * Submit cart & place customer order.
     */
    public function submitOrder(Request $request): JsonResponse
    {
        $request->validate([
            'session_token'  => ['required', 'string'],
            'payment_method' => ['required', 'string', 'in:pay_at_counter,online'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
            'items'          => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'integer'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
        ]);

        $session = OrderingSession::where('session_token', $request->input('session_token'))
            ->where('status', 'active')
            ->firstOrFail();

        $entitlementService = app(\App\Services\EntitlementService::class);
        if (! $entitlementService->isSubscriptionValid($session->cafe_id)) {
            return response()->json([
                'message'    => 'Online ordering for this cafe is temporarily unavailable. Please contact the cafe staff for assistance.',
                'error_code' => 'ORDERING_UNAVAILABLE',
            ], 403);
        }

        $order = $this->orderingService->submitOrder(
            session: $session,
            items: $request->input('items'),
            paymentMethod: $request->input('payment_method'),
            customerNotes: $request->input('customer_notes')
        );

        return response()->json([
            'message' => 'Order submitted successfully.',
            'order' => [
                'id'             => $order->id,
                'order_number'   => $order->order_number,
                'status'         => $order->status,
                'payment_status' => $order->payment_status,
                'total'          => (float) $order->total,
                'created_at'     => $order->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Return live status of a customer order.
     */
    public function orderStatus(Request $request, string $order_number): JsonResponse|InertiaResponse
    {
        $order = Order::where('order_number', $order_number)
            ->with(['table', 'cafe', 'branch', 'orderItems.menuItem'])
            ->firstOrFail();

        $cafeSlug = $order->cafe?->slug;
        $qrToken = $order->table?->qr_token;
        $qrUrl = ($cafeSlug && $qrToken)
            ? route('public.customer.order_menu', ['cafe_slug' => $cafeSlug, 'qr_token' => $qrToken])
            : null;

        $responseData = [
            'qr_url' => $qrUrl,
            'order' => [
                'id'             => $order->id,
                'order_number'   => $order->order_number,
                'cafe_slug'      => $cafeSlug,
                'qr_token'       => $qrToken,
                'cafe_name'      => $order->cafe?->name,
                'branch_name'    => $order->branch?->name,
                'table_name'     => $order->table?->name,
                'status'         => $order->status,
                'payment_status' => $order->payment_status,
                'subtotal'       => (float) $order->subtotal,
                'tax'            => (float) $order->tax,
                'total'          => (float) $order->total,
                'created_at'     => $order->created_at?->toIso8601String(),
                'items'          => $order->orderItems->map(fn ($oi) => [
                    'id'         => $oi->id,
                    'name'       => $oi->menuItem?->name,
                    'quantity'   => $oi->quantity,
                    'unit_price' => (float) $oi->unit_price,
                    'total'      => (float) $oi->total,
                ]),
            ],
        ];

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($responseData['order']);
        }

        return Inertia::render('Customer/CustomerOrderStatus', $responseData);
    }

    /**
     * Submit table request (call_staff, request_bill, water, etc.).
     */
    public function createCustomerRequest(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => ['required', 'string'],
            'request_type'  => ['required', 'string', 'in:call_staff,water,request_bill,assistance,custom'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ]);

        $session = OrderingSession::where('session_token', $request->input('session_token'))
            ->where('status', 'active')
            ->firstOrFail();

        $entitlementService = app(\App\Services\EntitlementService::class);
        if (! $entitlementService->isSubscriptionValid($session->cafe_id)) {
            return response()->json([
                'message'    => 'Online ordering for this cafe is temporarily unavailable. Please contact the cafe staff for assistance.',
                'error_code' => 'ORDERING_UNAVAILABLE',
            ], 403);
        }

        $customerReq = $this->orderingService->createCustomerRequest(
            session: $session,
            requestType: $request->input('request_type'),
            notes: $request->input('notes')
        );

        return response()->json([
            'message' => 'Request sent to cafe staff.',
            'request' => [
                'id'           => $customerReq->id,
                'request_type' => $customerReq->request_type,
                'status'       => $customerReq->status,
                'created_at'   => $customerReq->created_at?->toIso8601String(),
            ],
        ]);
    }
}
