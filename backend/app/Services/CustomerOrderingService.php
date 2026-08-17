<?php

namespace App\Services;

use App\Exceptions\EntitlementException;
use App\Models\Cafe;
use App\Models\CustomerRequest;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderingSession;
use App\Models\Payment;
use App\Models\RestaurantTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerOrderingService
{
    public function __construct(
        protected EntitlementService $entitlementService
    ) {}

    /**
     * Resolves Cafe, Branch, and Table from public QR token and creates/retrieves an OrderingSession.
     */
    public function resolveTableAndSession(
        string $cafeSlug,
        string $qrToken,
        ?float $userLat = null,
        ?float $userLng = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        $table = RestaurantTable::where('qr_token', $qrToken)
            ->whereHas('branch.cafe', fn ($q) => $q->where('slug', $cafeSlug))
            ->with(['branch.cafe'])
            ->first();

        if (! $table || ! $table->branch || ! $table->branch->cafe) {
            throw ValidationException::withMessages([
                'qr_token' => ['Invalid or expired QR code.'],
            ]);
        }

        $cafe = $table->branch->cafe;

        if (! $cafe->qr_ordering_enabled) {
            throw ValidationException::withMessages([
                'qr_ordering' => ['Customer QR ordering is currently disabled for this cafe.'],
            ]);
        }

        // Verify Subscription / Entitlement
        if (! $this->entitlementService->isSubscriptionValid($cafe->id)) {
            throw new EntitlementException('This cafe does not have an active subscription to process customer orders.');
        }

        // Proximity / Location Check if enabled
        if ($cafe->require_location && $cafe->latitude !== null && $cafe->longitude !== null) {
            if ($userLat === null || $userLng === null) {
                throw ValidationException::withMessages([
                    'location' => ['Location permission is required to order at this cafe table.'],
                ]);
            }

            $distanceMeters = $this->calculateHaversineDistance(
                (float) $userLat,
                (float) $userLng,
                (float) $cafe->latitude,
                (float) $cafe->longitude
            );

            if ($distanceMeters > ($cafe->location_radius_meters ?? 100)) {
                throw ValidationException::withMessages([
                    'location' => ['You must be physically present at the cafe to place a dine-in order.'],
                ]);
            }
        }

        // Find existing active session or create a new one (valid for 2 hours)
        $session = OrderingSession::where('table_id', $table->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $session) {
            $session = OrderingSession::create([
                'cafe_id'       => $cafe->id,
                'branch_id'     => $table->branch_id,
                'table_id'      => $table->id,
                'session_token' => OrderingSession::generateToken(),
                'qr_token_used' => $qrToken,
                'ip_address'    => $ip,
                'user_agent'    => $userAgent,
                'status'        => 'active',
                'expires_at'    => now()->addHours(2),
            ]);
        }

        return [
            'cafe'    => $cafe,
            'branch'  => $table->branch,
            'table'   => $table,
            'session' => $session,
        ];
    }

    /**
     * Submits customer order from active session.
     */
    public function submitOrder(
        OrderingSession $session,
        array $items,
        string $paymentMethod = 'pay_at_counter',
        ?string $customerNotes = null
    ): Order {
        if ($session->isExpired()) {
            throw ValidationException::withMessages([
                'session' => ['Your ordering session has expired. Please rescan the table QR code.'],
            ]);
        }

        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => ['Your order cart cannot be empty.'],
            ]);
        }

        $cafe = $session->cafe;

        return DB::transaction(function () use ($session, $cafe, $items, $paymentMethod, $customerNotes) {
            $subtotal = 0.0;
            $orderItemsData = [];

            foreach ($items as $itemData) {
                $menuItem = MenuItem::where('id', $itemData['menu_item_id'])
                    ->where('cafe_id', $cafe->id)
                    ->where('status', 'active')
                    ->first();

                if (! $menuItem) {
                    throw ValidationException::withMessages([
                        'menu_item' => ["Menu item ID {$itemData['menu_item_id']} is unavailable."],
                    ]);
                }

                $qty = max(1, (int) ($itemData['quantity'] ?? 1));
                $unitPrice = (float) $menuItem->price;
                $lineTotal = $unitPrice * $qty;
                $subtotal += $lineTotal;

                $orderItemsData[] = [
                    'menu_item_id' => $menuItem->id,
                    'quantity'     => $qty,
                    'unit_price'   => $unitPrice,
                    'discount'     => 0.0,
                    'tax'          => 0.0,
                    'total'        => $lineTotal,
                ];
            }

            $taxRate = (float) ($cafe->tax_rate ?? 0.0);
            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $totalAmount = round($subtotal + $taxAmount, 2);

            $orderNumber = 'ORD-' . strtoupper(substr(md5(uniqid()), 0, 6));

            $isPaymentRequired = (bool) $cafe->require_payment_before_kitchen;
            $initialStatus = $isPaymentRequired ? 'payment_pending' : 'kitchen_pending';
            $paymentStatus = ($paymentMethod === 'online') ? 'paid' : 'pending_counter_confirmation';

            if ($paymentMethod === 'online') {
                $initialStatus = 'kitchen_pending';
            }

            $order = Order::create([
                'cafe_id'             => $session->cafe_id,
                'branch_id'           => $session->branch_id,
                'table_id'            => $session->table_id,
                'ordering_session_id' => $session->id,
                'order_number'        => $orderNumber,
                'order_type'          => 'dine_in_qr',
                'status'              => $initialStatus,
                'payment_status'      => $paymentStatus,
                'subtotal'            => $subtotal,
                'tax'                 => $taxAmount,
                'discount'            => 0.0,
                'total'               => $totalAmount,
                'customer_notes'      => $customerNotes,
            ]);

            foreach ($orderItemsData as $itemData) {
                $itemData['order_id'] = $order->id;
                OrderItem::create($itemData);
            }

            // Create automatic payment record if paid online
            if ($paymentMethod === 'online') {
                Payment::create([
                    'cafe_id'               => $session->cafe_id,
                    'order_id'              => $order->id,
                    'amount'                => $totalAmount,
                    'method'                => 'online',
                    'status'                => 'completed',
                    'transaction_reference' => 'tx_online_' . time(),
                    'paid_at'               => now(),
                ]);
            }

            return $order;
        });
    }

    /**
     * Cashier confirms pay-at-counter payment for an order.
     */
    public function confirmCounterPayment(Order $order, string $paymentMethod = 'cash'): Order
    {
        return DB::transaction(function () use ($order, $paymentMethod) {
            $order->update([
                'payment_status' => 'paid',
                'status'         => 'kitchen_pending',
            ]);

            Payment::create([
                'cafe_id'               => $order->cafe_id,
                'order_id'              => $order->id,
                'amount'                => $order->total,
                'method'                => $paymentMethod,
                'status'                => 'completed',
                'transaction_reference' => 'tx_counter_' . time(),
                'paid_at'               => now(),
            ]);

            return $order;
        });
    }

    /**
     * Submits a customer table request (call_staff, water, request_bill, etc.).
     */
    public function createCustomerRequest(
        OrderingSession $session,
        string $requestType,
        ?string $notes = null
    ): CustomerRequest {
        return CustomerRequest::create([
            'cafe_id'             => $session->cafe_id,
            'branch_id'           => $session->branch_id,
            'table_id'            => $session->table_id,
            'ordering_session_id' => $session->id,
            'request_type'        => $requestType,
            'status'              => 'pending',
            'notes'               => $notes,
        ]);
    }

    /**
     * Calculates Haversine distance in meters between two lat/lng coordinates.
     */
    private function calculateHaversineDistance(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
