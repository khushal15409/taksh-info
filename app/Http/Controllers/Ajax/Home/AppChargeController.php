<?php

namespace App\Http\Controllers\Ajax\Home;

use App\Helpers\AppCharges;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AppChargeController extends Controller
{
    /** GET /app-charges - fees added to an order; a fee admin switched off comes back as 0. */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'platform_fee' => AppCharges::platformFee(),
                'quick_delivery_fee' => AppCharges::quickDeliveryFee(),
            ],
        ]);
    }
}
