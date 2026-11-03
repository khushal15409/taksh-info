<?php

namespace App\Http\Controllers\Ajax\Home;

use App\Http\Controllers\Controller;
use App\Http\Requests\Home\LocationRequest;
use App\Services\SaleCatalogService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class SaleController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly SaleCatalogService $sales) {}

    /** GET /ecommerce/sales */
    public function index(LocationRequest $request): JsonResponse
    {
        return $this->success($this->sales->list($this->fcId($request)), 'api.sales_fetched');
    }

    /** GET /ecommerce/sales/{id} */
    public function show(int $id, LocationRequest $request): JsonResponse
    {
        $sale = $this->sales->find($id);

        return $sale
            ? $this->success($this->sales->details($sale, $this->fcId($request)), 'api.sale_details_loaded')
            : $this->error('api.sale_not_found', 404);
    }

    /** GET /ecommerce/sales/{id}/products */
    public function products(int $id, LocationRequest $request): JsonResponse
    {
        $sale = $this->sales->find($id);

        return $sale
            ? $this->success($this->sales->productsOf($sale, $this->fcId($request)), 'api.sale_products_fetched')
            : $this->error('api.sale_not_found', 404);
    }

    private function fcId(LocationRequest $request): ?int
    {
        $v = $request->validated();

        return $this->sales->fulfillmentCenterFor(
            isset($v['latitude']) ? (float) $v['latitude'] : null,
            isset($v['longitude']) ? (float) $v['longitude'] : null,
        );
    }
}
