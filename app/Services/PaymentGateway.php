<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaymentGateway
{
    private string $baseUrl;

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('payments.mongike.api_key');
        $this->baseUrl = config('payments.mongike.base_url');
    }

    public function initiatePayment(string $orderId, float $amount, string $buyerPhone, ?string $webhookUrl = null): array
    {
        $payload = [
            'order_id' => $orderId,
            'amount' => $amount,
            'buyer_phone' => $buyerPhone,
            'fee_payer' => 'MERCHANT',
        ];

        if ($webhookUrl) {
            $payload['webhook_url'] = $webhookUrl;
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/payments/mobile-money/tanzania", $payload);

        if ($response->failed()) {
            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Payment initiation failed.',
                'status' => $response->status(),
            ];
        }

        return [
            'success' => true,
            'data' => $response->json(),
        ];
    }

    public function checkBalance(): array
    {
        $response = Http::withToken($this->apiKey)
            ->get("{$this->baseUrl}/wallet/balance");

        return $response->successful() ? $response->json() : [];
    }
}
