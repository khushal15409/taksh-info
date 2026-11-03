<?php

namespace App\Http\Controllers\Ajax\Home;

use App\Http\Controllers\Controller;
use App\Http\Requests\Home\BannerIndexRequest;
use App\Http\Resources\Home\BannerResource;
use App\Repositories\Contracts\BannerRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class BannerController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly BannerRepositoryInterface $banners) {}

    /** GET /ecommerce/banners?type=&position=&fc_id= */
    public function index(BannerIndexRequest $request): JsonResponse
    {
        $banners = $this->banners->active($request->validated());

        return $banners->isEmpty()
            ? $this->success([], 'api.banners.not_found')
            : $this->success(BannerResource::collection($banners)->resolve(), 'api.banners.fetched');
    }
}
