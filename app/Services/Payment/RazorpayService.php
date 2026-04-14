<?php

namespace App\Services\Payment;

use App\Services\ApiConfigService;
use Razorpay\Api\Api;

class RazorpayService
{
    private function client(): Api
    {
        return new Api(
            ApiConfigService::get('razorpay', 'key_id', config('services.razorpay.key_id')),
            ApiConfigService::get('razorpay', 'key_secret', config('services.razorpay.key_secret'))
        );
    }

    public function getKeyId(): string
    {
        return ApiConfigService::get('razorpay', 'key_id', config('services.razorpay.key_id'));
    }

    public function createOrder(float $amount): array
    {
        $order = $this->client()->order->create([
            'amount'   => $amount * 100,
            'currency' => 'INR',
            'receipt'  => 'rakhi_' . time(),
        ]);

        return $order->toArray();
    }

    public function verifySignature(
        string $orderId,
        string $paymentId,
        string $signature
    ): bool {
        try {
            $this->client()->utility->verifyPaymentSignature([
                'razorpay_order_id'   => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature'  => $signature,
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}