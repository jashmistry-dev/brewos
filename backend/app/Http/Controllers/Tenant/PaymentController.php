<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StorePaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function index(\Illuminate\Http\Request $request): JsonResponse|\Inertia\Response
    {
        Gate::authorize('permission', 'payment.view');

        $payments = Payment::with(['order'])->orderBy('created_at', 'desc')->get();

        $formattedPayments = $payments->map(fn ($p) => [
            'id'                    => $p->id,
            'order_id'              => $p->order_id,
            'order_number'          => $p->order?->order_number,
            'amount'                => (float) $p->amount,
            'method'                => $p->method,
            'status'                => $p->status,
            'transaction_reference' => $p->transaction_reference,
            'paid_at'               => $p->paid_at?->toIso8601String(),
            'created_at'            => $p->created_at?->toIso8601String(),
        ]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'payments' => $formattedPayments,
            ]);
        }

        return \Inertia\Inertia::render('Tenant/Payments', [
            'payments' => $formattedPayments,
        ]);
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        Gate::authorize('permission', 'payment.create');

        $cafeId    = app(TenantContext::class)->getCafeId();
        $validated = $request->validated();

        // Verify order belongs to the active cafe (server-side — never trust client cafe_id).
        $order = Order::withoutGlobalScopes()
            ->where('id', $validated['order_id'])
            ->where('cafe_id', $cafeId)
            ->firstOrFail();

        // Store the client-submitted amount as-is (validated numeric >= 0 in FormRequest).
        // The spec does not mandate amount === order.total; that would invent a partial-payment
        // restriction not present in the authoritative documentation.
        $payment = Payment::create([
            'cafe_id'               => $cafeId,
            'order_id'              => $order->id,
            'amount'                => (float) $validated['amount'],
            'method'                => $validated['method'],
            'status'                => $validated['status'] ?? 'pending',
            'transaction_reference' => $validated['transaction_reference'] ?? null,
            'paid_at'               => $validated['paid_at'] ?? null,
        ]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Payment recorded successfully.',
                'payment' => [
                    'id'                    => $payment->id,
                    'order_id'              => $payment->order_id,
                    'amount'                => (float) $payment->amount,
                    'method'                => $payment->method,
                    'status'                => $payment->status,
                    'transaction_reference' => $payment->transaction_reference,
                    'paid_at'               => $payment->paid_at?->toIso8601String(),
                    'created_at'            => $payment->created_at?->toIso8601String(),
                ],
            ], Response::HTTP_CREATED);
        }

        return redirect()->back()->with('success', 'Payment recorded successfully.');
    }
}
