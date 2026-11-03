<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Web\WebController;
use App\Http\Requests\Web\Catalog\ListingFilters;
use App\Services\CatalogService;
use App\Services\CategoryService;
use App\Support\Presenters\ProductCard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** The shop page and search results: ?q= (search) &category= &max= (price) &page= - filters are plain links and a GET form. */
class ListingPageController extends WebController
{
    public const PRICE_MAX = 2000;

    public function __construct(
        private readonly CatalogService $catalog,
        private readonly CategoryService $categories,
    ) {}

    public function show(Request $request): View
    {
        $filters = ListingFilters::from($request);
        $tree = $this->categories->tree('standard_delivery');
        $page = $this->catalog->list($filters->toCatalog());
        $page->withPath(route('listing'))->appends($filters->query());

        return $this->page('catalog.listing', [
            'filters' => $filters,
            'tree' => $tree,
            'products' => $page,
            'cards' => collect($page->items())->map(fn (array $p) => ProductCard::fromList($p))->all(),
            'categoryName' => $filters->category ? $this->categoryName($tree, $filters->category) : null,
            'priceMax' => self::PRICE_MAX,
        ]);
    }

    /** @param list<array<string, mixed>> $tree */
    private function categoryName(array $tree, int $id): ?string
    {
        foreach ($tree as $c) {
            if ($c['id'] === $id) {
                return $c['name'];
            }
            foreach ($c['children'] ?? [] as $child) {
                if ($child['id'] === $id) {
                    return $child['name'];
                }
            }
        }

        return null;
    }
}
