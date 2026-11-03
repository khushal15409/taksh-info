<?php

namespace App\Repositories\Contracts;

use App\Models\ProductReview;
use Illuminate\Support\Collection;

interface ReviewRepositoryInterface
{
    public function productExists(int $productId): bool;

    /** Has this customer had the product delivered (an order in `delivered` status)? */
    public function hasDelivered(int $userId, int $productId): bool;

    public function findByUser(int $userId, int $productId): ?ProductReview;

    public function save(int $userId, int $productId, array $attributes): ProductReview;

    /** @return array{average_rating: float, total_reviews: int} over approved reviews */
    public function summary(int $productId): array;

    /**
     * Delivered products not rated yet, newest delivery first, one row per product.
     *
     * @return Collection<int, object> rows: order_id, order_number, delivered_at, product_id, product_name, image_url
     */
    public function pendingRows(int $userId, int $withinDays, int $limit): Collection;

    /** Products (with images, primary first) keyed by id. */
    public function productsWithImages(array $productIds): Collection;
}
