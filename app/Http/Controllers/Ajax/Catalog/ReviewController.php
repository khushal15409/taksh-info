<?php

namespace App\Http\Controllers\Ajax\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreReviewRequest;
use App\Services\ReviewService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly ReviewService $reviews) {}

    /** GET /reviews/pending */
    public function pending(Request $request): JsonResponse
    {
        return $this->success($this->reviews->pending($request->user()), 'api.review.pending_listed');
    }

    /** GET /product/{id}/review - may the customer rate it, and what did they write */
    public function show(Request $request, int $id): JsonResponse
    {
        return $this->success($this->reviews->status($request->user(), $id), 'api.review.status_loaded');
    }

    /** POST /product/{id}/review {rating 1-5, review_title?, review_text?} */
    public function store(StoreReviewRequest $request, int $id): JsonResponse
    {
        $result = $this->reviews->submit($request->user(), $id, $request->validated());

        return $this->success($result['payload'], $result['created'] ? 'api.review.submitted' : 'api.review.updated', $result['created'] ? 201 : 200);
    }
}
