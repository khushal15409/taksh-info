<?php

namespace App\Services;

use App\Exceptions\ProductNotFoundException;
use App\Exceptions\ReviewNotAllowedException;
use App\Http\Resources\Catalog\PendingReviewResource;
use App\Http\Resources\Catalog\ReviewResource;
use App\Models\User;
use App\Repositories\Contracts\ReviewRepositoryInterface;

/** Ratings by customers: only a delivered purchase can be rated, once per product (a second submit updates the first). */
class ReviewService
{
    /** How long after delivery the customer keeps being asked for a rating. */
    private const PENDING_DAYS = 30;

    private const PENDING_LIMIT = 5;

    public function __construct(private readonly ReviewRepositoryInterface $reviews) {}

    /** @return array{items: array} delivered products the customer has not rated yet */
    public function pending(User $user): array
    {
        $rows = $this->reviews->pendingRows($user->id, self::PENDING_DAYS, self::PENDING_LIMIT);
        $products = $this->reviews->productsWithImages($rows->pluck('product_id')->all());

        $items = $rows->filter(fn ($row) => $products->has($row->product_id))
            ->map(fn ($row) => PendingReviewResource::make((object) ['row' => $row, 'product' => $products->get($row->product_id)])->resolve())
            ->values()
            ->all();

        return ['items' => $items];
    }

    /** @return array{product_id: int, can_review: bool, has_reviewed: bool, review: ?array} */
    public function status(User $user, int $productId): array
    {
        $this->assertProduct($productId);
        $review = $this->reviews->findByUser($user->id, $productId);

        return [
            'product_id' => $productId,
            'can_review' => $this->reviews->hasDelivered($user->id, $productId),
            'has_reviewed' => (bool) $review,
            'review' => $review ? ReviewResource::make($review)->resolve() : null,
        ];
    }

    /**
     * @param  array{rating: int, review_title?: ?string, review_text?: ?string}  $data
     * @return array{created: bool, payload: array}
     */
    public function submit(User $user, int $productId, array $data): array
    {
        $this->assertProduct($productId);

        if (! $this->reviews->hasDelivered($user->id, $productId)) {
            throw new ReviewNotAllowedException();
        }

        $existing = $this->reviews->findByUser($user->id, $productId);

        $review = $this->reviews->save($user->id, $productId, [
            'rating' => (int) $data['rating'],
            'review_title' => $this->clean($data['review_title'] ?? null),
            'review_text' => $this->clean($data['review_text'] ?? null),
            'is_verified_purchase' => true,
            'is_approved' => true,
        ]);

        return [
            'created' => ! $existing,
            'payload' => ['review' => ReviewResource::make($review)->resolve(), 'rating_summary' => $this->reviews->summary($productId)],
        ];
    }

    private function assertProduct(int $productId): void
    {
        if (! $this->reviews->productExists($productId)) {
            throw new ProductNotFoundException();
        }
    }

    private function clean(mixed $value): ?string
    {
        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : $value;
    }
}
