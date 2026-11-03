<?php

namespace App\Http\Controllers\Ajax\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CreatePaymentRequest;
use App\Http\Requests\Order\VerifyPaymentRequest;
use App\Services\PaymentService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly PaymentService $payments) {}

    /** POST /payments/create {order_id} - the Razorpay order the browser's checkout needs */
    public function create(CreatePaymentRequest $request): JsonResponse
    {
        $result = $this->payments->create($request->user(), (int) $request->validated('order_id'));

        return $this->success($result['data'], $result['message']);
    }

    /** POST /payments/verify {razorpay_order_id, razorpay_payment_id, razorpay_signature} */
    public function verify(VerifyPaymentRequest $request): JsonResponse
    {
        $v = $request->validated();
        $result = $this->payments->verify($request->user(), $v['razorpay_order_id'], $v['razorpay_payment_id'], $v['razorpay_signature']);

        return $this->success($result['data'], $result['message']);
    }

    /** GET /payments/status/{orderId} */
    public function status(Request $request, int $orderId): JsonResponse
    {
        return $this->success($this->payments->status($request->user(), $orderId), 'Payment status retrieved');
    }
}
