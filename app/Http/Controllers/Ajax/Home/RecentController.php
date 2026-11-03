<?php

namespace App\Http\Controllers\Ajax\Home;

use App\Http\Controllers\Controller;
use App\Http\Requests\Home\RecentViewsRequest;
use App\Services\RecentViewService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class RecentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly RecentViewService $recent) {}

    /** GET /product/recent-views?delivery_type= (customers only) */
    public function recentViews(RecentViewsRequest $request): JsonResponse
    {
        return $this->success($this->recent->forUser($request->user()->id, $request->deliveryType()), 'api.recent_views_fetched');
    }
}
