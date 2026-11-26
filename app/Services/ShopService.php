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

    /**
     * @param  ?int  $category  a parent category id from categoryOptions() (its sub-categories count too)
     * @return array{page: LengthAwarePaginator, cards: list<array>, total: int, categories: list<array{id: int, name: string, count: int}>, locations: list<array{value: string, label: string, count: int}>}
     */
    public function index(string $q, ?int $category = null, ?string $location = null): array
    {
        $ids = $category ? array_merge([$category], Category::query()->where('parent_id', $category)->pluck('id')->all()) : [];
        $page = $this->shops->paginate($q, $ids, $location, 'popular', self::PER_PAGE);

        return [
            'page' => $page,
            'cards' => $this->cards($page->items()),
            'total' => $this->shops->total(),
            'categories' => $this->categoryOptions(),
            'locations' => $this->shops->locationOptions(),
        ];
    }

    /** One compact list for the "All Categories" menu: the top-level categories the shops sell in, with how many shops. */
    private function categoryOptions(): array
    {
        $counts = $this->shops->categoryShopCounts();
        if (! $counts) {
            return [];
        }

        $cats = Category::query()->whereIn('id', array_keys($counts))->get(['id', 'name', 'parent_id'])->keyBy('id');
        $parents = Category::query()->whereIn('id', $cats->pluck('parent_id')->filter()->unique())->pluck('name', 'id');

        $by = [];   // parent id => [name, shops]
        foreach ($counts as $cid => $n) {
            $c = $cats->get($cid);
            if (! $c) {
                continue;
            }
            $pid = $c->parent_id ?: $c->id;
            $name = $c->parent_id ? ($parents[$c->parent_id] ?? null) : $c->name;
            if ($name) {
                $by[$pid] ??= ['id' => (int) $pid, 'name' => $name, 'count' => 0];
                $by[$pid]['count'] = max($by[$pid]['count'], $n);   // shops in a parent: at least the busiest child (exact count is not worth a query)
            }
        }
        usort($by, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return array_values($by);
    }

    /** @param iterable<\App\Models\Vendor> $vendors @return list<array> */
    private function cards(iterable $vendors): array
    {
        $ids = collect($vendors)->pluck('id')->map(fn ($i) => (int) $i)->all();
        $ratings = $this->shops->ratings($ids);
        $names = $this->shops->categoryNames($ids, 3);

        return collect($vendors)->map(fn ($v) => ShopCard::from($v, ['rating' => $ratings[(int) $v->id] ?? null, 'categories' => $names[(int) $v->id] ?? []]))->all();
    }

    /** @return list<array> */
    public function featured(int $limit = 6): array
    {
        try {
            return $this->cards($this->shops->featured($limit)->all());
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
