<?php

namespace App\Repositories\Eloquent;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Support\Collection;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function activeTree(?string $type = null, bool $untypedOnly = false): Collection
    {
        return Category::where('status', 'active')
            ->with(['children' => fn ($q) => $q->where('status', 'active')])
            ->whereNull('parent_id')
            ->when($untypedOnly, fn ($q) => $q->whereNull('delivery_types'))
            ->when($type, fn ($q) => $q->forDeliveryType($type))
            ->get();
    }

    public function parents(?string $type = null): Collection
    {
        return Category::where('status', 'active')
            ->whereNull('parent_id')
            ->when($type, fn ($q) => $q->forDeliveryType($type))
            ->select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function findParent(int $id, ?string $type = null): ?Category
    {
        return Category::where('id', $id)
            ->whereNull('parent_id')
            ->when($type, fn ($q) => $q->forDeliveryType($type))
            ->first();
    }

    public function children(int $parentId): Collection
    {
        return Category::where('status', 'active')
            ->where('parent_id', $parentId)
            ->select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function standardIdsWithChildren(int $categoryId): array
    {
        return Category::forDeliveryType(Category::DELIVERY_STANDARD)
            ->where(fn ($q) => $q->where('categories.id', $categoryId)->orWhere('categories.parent_id', $categoryId))
            ->pluck('categories.id')
            ->all();
    }
}
