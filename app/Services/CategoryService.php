<?php

namespace App\Services;

use App\Http\Resources\Catalog\CategoryTreeResource;
use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Support\Collection;

class CategoryService
{
    private const TYPES = [Category::DELIVERY_STANDARD, Category::DELIVERY_EXPRESS];

    public function __construct(private readonly CategoryRepositoryInterface $categories) {}

    /**
     * Active top-level categories with children, filtered like the API:
     * no delivery_type (or "all", or both types) = everything; one type = that type's categories;
     * only unknown values = categories without a delivery type.
     */
    public function tree(mixed $deliveryType): array
    {
        if (! $deliveryType) {
            return $this->resource($this->categories->activeTree());
        }

        $types = collect(is_array($deliveryType) ? $deliveryType : [$deliveryType])
            ->flatMap(fn ($t) => is_string($t) ? explode(',', $t) : [])
            ->map(fn ($t) => strtolower(trim((string) $t)))
            ->filter(fn ($t) => $t !== '')
            ->flatMap(fn ($t) => $t === 'all' ? self::TYPES : [$t])
            ->map(fn ($t) => $t === 'express-30' ? Category::DELIVERY_EXPRESS : $t)
            ->unique()
            ->filter(fn ($t) => in_array($t, self::TYPES, true))
            ->values();

        return match (true) {
            $types->count() === 2 => $this->resource($this->categories->activeTree()),
            $types->isEmpty() => $this->resource($this->categories->activeTree(null, true)),
            default => $this->resource($this->categories->activeTree($types->first())),
        };
    }

    /** `delivery_type` when it is exactly one known type, else null (= no type filter). */
    public function requestedType(?string $deliveryType): ?string
    {
        $type = strtolower(trim((string) $deliveryType));
        $type = $type === 'express-30' ? Category::DELIVERY_EXPRESS : $type;

        return in_array($type, self::TYPES, true) ? $type : null;
    }

    /** @return array{categories: array, count: int} */
    public function parents(?string $deliveryType): array
    {
        $parents = $this->categories->parents($this->requestedType($deliveryType));

        return ['categories' => $parents->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all(), 'count' => $parents->count()];
    }

    /** @return array{parent: array, categories: array, count: int}|null null when the parent is not a (matching) top-level category */
    public function children(int $parentId, ?string $deliveryType): ?array
    {
        $parent = $this->categories->findParent($parentId, $this->requestedType($deliveryType));

        if (! $parent) {
            return null;
        }

        $children = $this->categories->children($parent->id);

        return [
            'parent' => ['id' => $parent->id, 'name' => $parent->name],
            'categories' => $children->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all(),
            'count' => $children->count(),
        ];
    }

    private function resource(Collection $categories): array
    {
        return CategoryTreeResource::collection($categories)->resolve();
    }
}
