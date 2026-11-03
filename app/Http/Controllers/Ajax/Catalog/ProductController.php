<?php

namespace App\Http\Controllers\Ajax\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductDetailRequest;
use App\Http\Requests\Catalog\ProductIndexRequest;
use App\Services\CatalogService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly CatalogService $catalog) {}

    /** GET /ecommerce/products - paginated (the page is in `data`, like the API) */
    public function index(ProductIndexRequest $request): JsonResponse
    {
        return $this->success($this->catalog->list($request->validated())->toArray(), 'api.products_fetched');
    }

    /** GET /ecommerce/products/{id} - also remembers the view for a signed-in customer */
    public function show(int $id, ProductDetailRequest $request): JsonResponse
    {
        $v = $request->validated();
        $data = $this->catalog->detail(
            $id,
            $request->user(),
            isset($v['latitude']) ? (float) $v['latitude'] : null,
            isset($v['longitude']) ? (float) $v['longitude'] : null,
        );

        return $data ? $this->success($data, 'api.product.details_loaded') : $this->error('api.product_not_found', 404);
    }

    /** GET /ecommerce/products/{id}/similar - same plain shape as the API ({status, message, data}) */
    public function similar(int $id): JsonResponse
    {
        $result = $this->catalog->similar($id);

        return response()->json([
            'status' => $result['found'],
            'message' => $result['found'] ? 'Similar products fetched successfully' : 'Product not found',
            'data' => $result['data'],
        ], $result['found'] ? 200 : 404);
    }

    /** GET /product/attributes */
    public function attributes(): JsonResponse
    {
        return $this->success($this->catalog->attributes(), 'api.attributes_fetched');
    }
}
