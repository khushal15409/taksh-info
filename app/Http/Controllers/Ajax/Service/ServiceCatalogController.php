<?php

namespace App\Http\Controllers\Ajax\Service;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\ServiceBookingQueryService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class ServiceCatalogController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly ServiceBookingQueryService $query) {}

    /** GET /service/services - public: the active services, price = service_charge */
    public function index(): JsonResponse
    {
        return $this->success($this->query->catalog(), 'Services fetched successfully');
    }
}
