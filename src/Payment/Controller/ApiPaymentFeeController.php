<?php

namespace Module\Payment\Controller;

use App\Http\Controllers\Controller;
use App\Providers\PaymentFeeProvider;

class ApiPaymentFeeController extends Controller
{
    /**
     * Get payment fee percentage for a provider.
     *
     * GET /api/payment-fees/{provider}
     */
    public function show(string $provider)
    {
        $paymentFee = PaymentFeeProvider::getPaymentFee($provider);

        return response()->json([
            'paymentFee' => $paymentFee,
        ]);
    }
}
