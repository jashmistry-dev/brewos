<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingEvent;
use App\Models\Cafe;
use App\Models\Subscription;
use App\Services\AuditLogger;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Services\WebhookSignatureValidator;

class BillingWebhookController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected AuditLogger $auditLogger,
        protected WebhookSignatureValidator $signatureValidator
    ) {}

    public function handle(Request $request, string $provider): JsonResponse
    {
        // 1. Verify Cryptographic Webhook Signature before reading payload
        if (! $this->signatureValidator->verify($request, $provider)) {
            return response()->json([
                'message' => 'Invalid or unverified webhook cryptographic signature.',
                'status'  => 'rejected',
            ], 400);
        }

        $payload = $request->all();
        $eventId = $request->header('X-Webhook-Event-Id') ?? $payload['event_id'] ?? $payload['id'] ?? null;
        $eventType = $request->header('X-Webhook-Event-Type') ?? $payload['event_type'] ?? $payload['type'] ?? 'subscription.updated';

        if (! $eventId) {
            return response()->json(['message' => 'Missing event identifier.'], 400);
        }

        // Idempotency check: Process event inside database transaction to ensure exactly-once execution
        return DB::transaction(function () use ($provider, $eventId, $eventType, $payload) {
            $eventRecord = BillingEvent::where('provider', $provider)
                ->where('event_id', $eventId)
                ->first();

            if ($eventRecord) {
                return response()->json([
                    'message'    => 'Billing event already processed.',
                    'event_id'   => $eventId,
                    'status'     => 'ignored_duplicate',
                ], 200);
            }

            $providerSubId = $payload['provider_subscription_id'] ?? $payload['data']['object']['id'] ?? null;
            $subscription = null;
            $cafeId = null;

            if ($providerSubId) {
                $subscription = Subscription::where('provider_subscription_id', $providerSubId)->first();
                $cafeId = $subscription?->cafe_id;
            }

            if (! $cafeId && isset($payload['cafe_id'])) {
                $cafe = Cafe::find($payload['cafe_id']);
                $cafeId = $cafe?->id;
            }

            // Record billing event for idempotency tracking
            BillingEvent::create([
                'provider'     => $provider,
                'event_id'     => $eventId,
                'event_type'   => $eventType,
                'cafe_id'      => $cafeId,
                'payload'      => $payload,
                'processed_at' => now(),
            ]);

            switch (strtolower($eventType)) {
                case 'subscription.renewed':
                case 'payment.succeeded':
                case 'invoice.payment_succeeded':
                case 'payment.sale.completed':
                    if ($subscription) {
                        $subscription->update([
                            'status'   => 'active',
                            'starts_at'=> now(),
                            'ends_at'  => now()->addMonth(),
                        ]);

                        $this->auditLogger->log(
                            action: 'subscription.renewed',
                            entityType: 'subscription',
                            entityId: $subscription->id,
                            cafeId: $subscription->cafe_id,
                            oldValues: ['status' => $subscription->getOriginal('status')],
                            newValues: ['status' => 'active', 'ends_at' => $subscription->ends_at->toIso8601String()]
                        );
                    }
                    break;

                case 'payment.failed':
                case 'invoice.payment_failed':
                    if ($subscription) {
                        $this->auditLogger->log(
                            action: 'payment.failed',
                            entityType: 'subscription',
                            entityId: $subscription->id,
                            cafeId: $subscription->cafe_id,
                            oldValues: null,
                            newValues: ['reason' => $payload['reason'] ?? 'Payment authorization failed']
                        );
                    }
                    break;

                case 'subscription.cancelled':
                case 'customer.subscription.deleted':
                    if ($subscription && $cafeId) {
                        $this->subscriptionService->cancelSubscription($cafeId);
                    }
                    break;
            }

            return response()->json([
                'message'  => 'Billing webhook event processed successfully.',
                'event_id' => $eventId,
                'status'   => 'processed',
            ], 200);
        });
    }
}
