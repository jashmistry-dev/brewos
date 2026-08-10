<?php

namespace Tests\Feature\Admin;

use App\Models\BillingEvent;
use App\Models\Cafe;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WebhookSignatureVerificationTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafe;
    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flushHeaders();

        $this->cafe = Cafe::create([
            'name'   => 'Webhook Test Cafe',
            'slug'   => 'webhook-test-cafe',
            'email'  => 'webhook@test.com',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'name'             => 'Test Plan',
            'slug'             => 'test-plan',
            'price'            => 29.99,
            'billing_interval' => 'monthly',
            'status'           => 'active',
        ]);

        $this->subscription = Subscription::create([
            'cafe_id'                  => $this->cafe->id,
            'plan_id'                  => $plan->id,
            'status'                   => 'active',
            'starts_at'                => now()->subMonth(),
            'ends_at'                  => now()->addDays(5),
            'provider'                 => 'stripe',
            'provider_subscription_id' => 'sub_webhook_test_123',
        ]);
    }

    protected function tearDown(): void
    {
        $this->flushHeaders();
        config(['app.env' => 'testing']);
        parent::tearDown();
    }

    public function test_invalid_stripe_signature_returns_400(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret_key_12345']);

        $response = $this->withHeaders([
            'Stripe-Signature' => 't=1234567890,v1=invalid_fake_signature_hash',
        ])->postJson('/api/webhooks/billing/stripe', [
            'event_id'                 => 'evt_invalid_sig_1',
            'event_type'               => 'subscription.renewed',
            'provider_subscription_id' => 'sub_webhook_test_123',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('status', 'rejected');
    }

    public function test_valid_stripe_signature_is_processed(): void
    {
        $secret = 'whsec_test_secret_key_12345';
        config(['services.stripe.webhook_secret' => $secret]);

        $payload = json_encode([
            'event_id'                 => 'evt_valid_sig_2',
            'event_type'               => 'subscription.renewed',
            'provider_subscription_id' => 'sub_webhook_test_123',
        ]);

        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        $header = "t={$timestamp},v1={$signature}";

        $response = $this->flushHeaders()->call('POST', '/api/webhooks/billing/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $header,
            'CONTENT_TYPE'          => 'application/json',
        ], $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'processed');

        $this->assertDatabaseHas('billing_events', [
            'provider' => 'stripe',
            'event_id' => 'evt_valid_sig_2',
        ]);
    }

    public function test_duplicate_valid_webhook_is_idempotent(): void
    {
        $secret = 'whsec_test_secret_key_12345';
        config(['services.stripe.webhook_secret' => $secret]);

        $payload = json_encode([
            'event_id'                 => 'evt_repeat_sig_3',
            'event_type'               => 'subscription.renewed',
            'provider_subscription_id' => 'sub_webhook_test_123',
        ]);

        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        $header = "t={$timestamp},v1={$signature}";

        // 1st Delivery
        $this->flushHeaders()->call('POST', '/api/webhooks/billing/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $header,
            'CONTENT_TYPE'          => 'application/json',
        ], $payload)->assertStatus(200)->assertJsonPath('status', 'processed');

        // 2nd Delivery (Duplicate)
        $response = $this->flushHeaders()->call('POST', '/api/webhooks/billing/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $header,
            'CONTENT_TYPE'          => 'application/json',
        ], $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ignored_duplicate');
    }

    public function test_paypal_webhook_missing_headers_is_rejected(): void
    {
        config(['services.paypal.webhook_id' => 'WH-PAYPAL-ID-12345']);

        $response = $this->flushHeaders()->postJson('/api/webhooks/billing/paypal', [
            'event_id'                 => 'evt_paypal_missing_headers',
            'event_type'               => 'PAYMENT.SALE.COMPLETED',
            'provider_subscription_id' => 'sub_webhook_test_123',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('status', 'rejected');
    }

    public function test_paypal_webhook_invalid_cert_url_ssrf_attempt_is_rejected(): void
    {
        config(['services.paypal.webhook_id' => 'WH-PAYPAL-ID-12345']);

        $response = $this->postJson('/api/webhooks/billing/paypal', [
            'event_id'                 => 'evt_paypal_ssrf',
            'event_type'               => 'PAYMENT.SALE.COMPLETED',
            'provider_subscription_id' => 'sub_webhook_test_123',
        ], [
            'PAYPAL-TRANSMISSION-ID'   => 'TRANS-123',
            'PAYPAL-TRANSMISSION-TIME' => now()->toIso8601String(),
            'PAYPAL-TRANSMISSION-SIG'  => 'fake_sig_123',
            'PAYPAL-CERT-URL'          => 'https://malicious-attacker-domain.com/cert.pem',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('status', 'rejected');
    }

    public function test_paypal_webhook_valid_mock_signature_is_accepted(): void
    {
        config(['services.paypal.webhook_id' => 'WH-PAYPAL-ID-12345']);

        $response = $this->postJson('/api/webhooks/billing/paypal', [
            'event_id'                 => 'evt_paypal_valid_mock',
            'event_type'               => 'PAYMENT.SALE.COMPLETED',
            'provider_subscription_id' => 'sub_webhook_test_123',
        ], [
            'PAYPAL-TRANSMISSION-ID'   => 'TRANS-123',
            'PAYPAL-TRANSMISSION-TIME' => now()->toIso8601String(),
            'PAYPAL-TRANSMISSION-SIG'  => 'test_mock_signature_valid',
            'PAYPAL-CERT-URL'          => 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'processed');
    }
}
