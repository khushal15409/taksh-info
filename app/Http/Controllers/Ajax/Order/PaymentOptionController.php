<?php

namespace App\Http\Controllers\Ajax\Order;

use App\Http\Controllers\Controller;
use App\Support\PaymentOptions;
use Illuminate\Http\JsonResponse;

class PaymentOptionController extends Controller
{
    /** GET /payment-options - which ways to pay are open, per kind of order */
    public function index(): JsonResponse
    {
        $data = [];

        foreach (PaymentOptions::all() as $orderType => $methods) {
            $data[$orderType] = ['cod' => $methods['cod'], 'online' => $methods['online'], 'available' => array_keys(array_filter($methods))];
        }

        return response()->json(['success' => true, 'data' => $data]);
    }
}
