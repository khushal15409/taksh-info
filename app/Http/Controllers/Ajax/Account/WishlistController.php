<?php

namespace App\Http\Controllers\Ajax\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\WishlistAddRequest;
use App\Http\Requests\Account\WishlistIndexRequest;
use App\Services\WishlistService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly WishlistService $wishlist) {}

    /** GET /wishlist/items (the API's GET /wishlist; on the website /wishlist is the page) */
    public function index(WishlistIndexRequest $request): JsonResponse
    {
        return $this->success($this->wishlist->list($request->user()->id, (int) $request->validated('per_page', 15)), 'api.wishlist_fetched');
    }

    /** POST /wishlist/add {product_id} - idempotent */
    public function add(WishlistAddRequest $request): JsonResponse
    {
        $this->wishlist->add($request->user()->id, (int) $request->validated('product_id'));

        return $this->success([], 'api.wishlist_item_added');
    }

    /** POST /wishlist/remove/{productId} */
    public function remove(Request $request, int $productId): JsonResponse
    {
        return $this->wishlist->remove($request->user()->id, $productId)
            ? $this->success([], 'api.wishlist_item_removed')
            : $this->error('api.wishlist_item_not_found', 404);
    }
}
