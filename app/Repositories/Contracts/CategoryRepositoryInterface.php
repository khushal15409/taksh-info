<?php

namespace App\Repositories\Contracts;

use App\Models\Category;
use Illuminate\Support\Collection;

interface CategoryRepositoryInterface
{
    /**
     * Active top-level categories with their active children.
     *
     * @param  string|null  $type  standard_delivery | express_30: only categories offered for that type
     * @param  bool  $untypedOnly  true: only categories without a delivery type (used when the caller sent only unknown types)
     */
    public function activeTree(?string $type = null, bool $untypedOnly = false): Collection;

    /** Active top-level categories (id, name), A-Z, optionally limited to one delivery type. */
    public function parents(?string $type = null): Collection;

    /** A top-level category, optionally only when it is offered for the delivery type. */
    public function findParent(int $id, ?string $type = null): ?Category;

    /** Active children (id, name) of a parent, A-Z. */
    public function children(int $parentId): Collection;

    /** The category plus its sub-categories, as far as the Standard catalogue offers them. @return int[] */
    public function standardIdsWithChildren(int $categoryId): array;

    /** The category plus its sub-categories, as far as the given delivery type offers them. @return int[] */
    public function idsWithChildren(int $categoryId, string $type): array;
}
