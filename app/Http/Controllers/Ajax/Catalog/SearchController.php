<?php

namespace App\Http\Controllers\Ajax\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductSearchRequest;
use App\Services\ProductSearchService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly ProductSearchService $search) {}

    /** GET /products/search?keyword=&vendor_id=&limit=&delivery_type= (plain {status, keyword, count, data}, like the API) */
    public function search(ProductSearchRequest $request): JsonResponse
    {
        $v = $request->validated();

        try {
            return response()->json($this->search->search(
                trim($v['keyword']),
                isset($v['vendor_id']) ? (int) $v['vendor_id'] : null,
                (int) ($v['limit'] ?? 10),
                ! empty($v['delivery_type']) ? (string) $v['delivery_type'] : null,
                $request->user(),
            ));
        } catch (\Throwable $e) {
            Log::error('Product search error: '.$e->getMessage(), ['keyword' => $v['keyword']]);

            return response()->json([
                'status' => false, 'keyword' => $v['keyword'], 'count' => 0, 'data' => [],
                'message' => 'An error occurred while searching products.',
            ], 500);
        }
    }

    /** GET /product/recent-searches (customers only) */
    public function recent(Request $request, \App\Repositories\Contracts\RecentSearchRepositoryInterface $recent): JsonResponse
    {
        return $this->success($recent->keywords($request->user()->id), 'api.recent_searches_fetched');
    }
}
