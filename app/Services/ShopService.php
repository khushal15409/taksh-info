<?php

namespace App\Services;

use App\Models\Category;
use App\Repositories\Contracts\ShopRepositoryInterface;
use App\Support\Presenters\ShopCard;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Browsing shops (approved vendors) and a shop's own page. Read only - nothing here writes to the database. */
class ShopService
{
    public const PER_PAGE = 12;

    public function __construct(private readonly ShopRepositoryInterface $shops) {}

    /** @return array{page: LengthAwarePaginator, cards: list<array>, total: int} */
    public function index(string $q): array
    {
        $page = $this->shops->paginate($q, [], 'popular', self::PER_PAGE);

        return [
            'page' => $page,
            'cards' => collect($page->items())->map(fn ($v) => ShopCard::from($v))->all(),
            'total' => $this->shops->total(),
        ];
    }

    /** @return list<array> */
    public function featured(int $limit = 6): array
    {
        try {
            return $this->shops->featured($limit)->map(fn ($v) => ShopCard::from($v))->all();
        } catch (\Throwable $e) {
            report($e);

            return [];   // the home still opens without the strip
        }
    }

    /** @return array<string, mixed>|null null when there is no such (approved) shop */
    public function detail(int $id): ?array
    {
        $vendor = $this->shops->find($id);
        if (! $vendor) {
            return null;
        }

        $counts = $this->shops->productCategoryCounts($id);
        $names = $counts ? Category::query()->whereIn('id', array_keys($counts))->get(['id', 'name', 'parent_id', 'image_url'])->keyBy('id') : collect();
        $pictures = $counts ? $this->shops->categoryProductImages($id) : [];
        $categories = [];
        foreach ($counts as $cid => $n) {
            if ($names->has($cid)) {
                $categories[] = ['id' => (int) $cid, 'name' => $names[$cid]->name, 'count' => $n, 'image_url' => $names[$cid]->image_url ?: ($pictures[(int) $cid] ?? null)];
            }
        }
        usort($categories, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return [
            'shop' => ShopCard::from($vendor),
            'tagline' => collect($categories)->take(3)->pluck('name')->implode(' • '),
            'categories' => $categories,
            'total' => $this->shops->productCount($id),
            'rating' => $this->shops->rating($id),
        ];
    }
}
