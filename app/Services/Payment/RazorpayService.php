<?php

namespace App\Services\Payment;

use Razorpay\Api\Api;

class RazorpayService
{
    private Api $api;

    public function __construct()
    {
        $this->api = new Api(
            config('services.razorpay.key_id'),
            config('services.razorpay.key_secret')
        );
    }

    public function createOrder(float $amount): array
    {
        $order = $this->api->order->create([
            'amount'   => $amount * 100, // paise
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
            $this->api->utility->verifyPaymentSignature([
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