<?php

namespace App\Http\Controllers\Ajax\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CheckDeliveryRequest;
use App\Services\DeliveryCheckService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DeliveryCheckController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly DeliveryCheckService $delivery) {}

    /** POST /ecommerce/products/{id}/check-delivery */
    public function check(int $id, CheckDeliveryRequest $request): JsonResponse
    {
        $v = $request->validated();

        try {
            $result = $this->delivery->check(
                $id,
                trim($v['pincode']),
                isset($v['product_variant_id']) ? (int) $v['product_variant_id'] : null,
                (int) ($v['qty'] ?? 1),
            );
        } catch (\App\Exceptions\ApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Delivery availability check error: '.$e->getMessage(), ['product_id' => $id]);

            return $this->error('Failed to check delivery availability. Please try again later.', 500);
        }

        return $this->success($result, 'Delivery availability checked successfully');
    }
}
