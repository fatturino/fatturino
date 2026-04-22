<?php

use Fatturino\FeInvoicetronic\Settings\InvoicetronicSettings;

beforeEach(function () {
    // Configure a webhook secret in settings for HMAC verification
    $settings = app(InvoicetronicSettings::class);
    $settings->webhook_secret = 'test_secret_key';
    $settings->api_key = 'ik_test_key';
    $settings->activated = false;
    $settings->company_id = 0;
    $settings->webhook_id = 0;
    $settings->webhook_url = '';
    $settings->save();
});

function makeWebhookSignature(string $body, string $secret, ?int $timestamp = null): string
{
    $timestamp ??= time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

    return "t={$timestamp},v1={$signature}";
}

it('rejects request without signature header', function () {
    $response = $this->postJson('/api/invoicetronic/webhook', []);

    $response->assertStatus(401);
});

it('rejects request with invalid HMAC', function () {
    $body = json_encode(['Endpoint' => 'update', 'Method' => 'POST', 'ResourceId' => 1]);
    $timestamp = time();

    $response = $this->post('/api/invoicetronic/webhook', json_decode($body, true), [
        'Content-Type' => 'application/json',
        'Invoicetronic-Signature' => "t={$timestamp},v1=deadbeef00000000",
    ]);

    $response->assertStatus(401);
});

it('rejects request with stale timestamp beyond tolerance', function () {
    $body = json_encode(['Endpoint' => 'update', 'Method' => 'POST', 'ResourceId' => 1]);
    $staleTimestamp = time() - 600; // 10 minutes ago, beyond 300s tolerance
    $signature = hash_hmac('sha256', "{$staleTimestamp}.{$body}", 'test_secret_key');

    $response = $this->post('/api/invoicetronic/webhook', json_decode($body, true), [
        'Content-Type' => 'application/json',
        'Invoicetronic-Signature' => "t={$staleTimestamp},v1={$signature}",
    ]);

    $response->assertStatus(401);
});

it('returns 200 on valid unknown event', function () {
    $body = json_encode(['Endpoint' => 'unknown_event', 'Method' => 'POST', 'ResourceId' => 1]);
    $sig = makeWebhookSignature($body, 'test_secret_key');

    $response = $this->call('POST', '/api/invoicetronic/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_INVOICETRONIC-SIGNATURE' => $sig,
    ], $body);

    $response->assertStatus(200)
        ->assertJson(['status' => 'ignored']);
});
