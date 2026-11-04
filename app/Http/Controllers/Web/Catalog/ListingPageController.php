<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Helpers\QuickDelivery;
use App\Http\Controllers\Web\WebController;
use App\Http\Requests\Web\Catalog\ListingFilters;
use App\Services\CatalogService;
use App\Services\CategoryService;
use App\Services\Quick\QuickLocation;
use App\Services\Quick\QuickServiceability;
use App\Support\Presenters\ProductCard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/** The shop page and search results: ?q= (search) &category[]= (several allowed) &max= (price) &quick=1 &page= - filters are plain links and a GET form. */
class ListingPageController extends WebController
{
    public const PRICE_MAX = 2000;

    public function __construct(
        private readonly CatalogService $catalog,
        private readonly CategoryService $categories,
        private readonly QuickLocation $location,
        private readonly QuickServiceability $quick,
    ) {}

    public function show(Request $request): View
    {
        $filters = ListingFilters::from($request);
        $tree = $this->categories->tree($filters->quick ? 'express_30' : 'standard_delivery');
        $where = $filters->quick ? $this->location->for($request) : null;
        if ($filters->quick) {
            $this->rememberTypedPincode($request);
        }
        $vendorIds = $where ? $this->quick->vendorIds($where['latitude'], $where['longitude'], $where['pincode']) : [];

        // Quick: the filter lists only the categories that have quick products for this place, with counts; with none picked they are all
        // shown ticked (that is what the list holds), and ticking every one of them is the same as no filter.
        $checked = null;
        if ($filters->quick) {
            [$tree, $counts, $totals] = $this->quickTree($tree, $this->catalog->quickCategoryCounts($vendorIds));
            $withProducts = array_keys(array_filter($counts));
            if ($filters->categories === [] || ! array_diff($withProducts, $filters->categories)) {
                $filters = $filters->withCategories([]);
                $checked = array_keys($counts);
            }
        }

        $page = $this->catalog->list($filters->toCatalog() + ['vendor_ids' => $vendorIds]);
        $page->withPath(route('listing'))->appends($filters->query());

        return $this->page('catalog.listing', [
            'filters' => $filters,
            'tree' => $tree,
            'counts' => $counts ?? [],
            'totals' => $totals ?? [],
            'checked' => $checked ?? $filters->categories,
            'products' => $page,
            'cards' => collect($page->items())->map(fn (array $p) => ProductCard::fromList($p))->all(),
            'pickedCategories' => $this->pickedCategories($tree, $filters->categories),
            'priceMax' => self::PRICE_MAX,
            // Quick delivery: where the customer is, and whether any shop serves it (null for the normal shop)
            'quickLocation' => $where ? $where + ['located' => $where['pincode'] !== null || $where['latitude'] !== null, 'serviceable' => $vendorIds !== []] : null,
        ]);
    }

    /**
     * Keep the categories that hold quick products (a parent also counts its own sub-categories' products).
     *
     * @param  list<array<string, mixed>>  $tree
     * @param  array<int, int>  $own  category id => products in it
     * @return array{0: list<array<string, mixed>>, 1: array<int, int>, 2: array<int, int>} the trimmed tree, each kept category's own
     *                                                                                      count, and each kept parent's count with its sub-categories
     */
    private function quickTree(array $tree, array $own): array
    {
        $kept = [];
        $counts = [];
        $totals = [];
        foreach ($tree as $parent) {
            $children = [];
            $sum = $own[$parent['id']] ?? 0;
            foreach ($parent['children'] ?? [] as $child) {
                $n = $own[$child['id']] ?? 0;
                if ($n > 0) {
                    $children[] = $child;
                    $counts[$child['id']] = $n;
                    $sum += $n;
                }
            }
            if ($sum > 0) {
                $counts[$parent['id']] = $own[$parent['id']] ?? 0;
                $totals[$parent['id']] = $sum;
                $kept[] = ['children' => $children] + $parent;
            }
        }

        return [$kept, $counts, $totals];
    }

    /** A pincode typed on the Quick page is remembered for the next pages; ?location=saved goes back to the saved address. */
    private function rememberTypedPincode(Request $request): void
    {
        $typed = QuickDelivery::normalizePincode($request->query('pincode'));
        if ($typed !== null) {
            Cookie::queue(Cookie::make(QuickLocation::COOKIE, QuickLocation::cookieValue($typed), 60 * 24 * 365, '/', null, null, false, false, 'Lax'));
        } elseif ($this->location->forgotten($request)) {
            Cookie::queue(Cookie::forget(QuickLocation::COOKIE));
        }
    }

    /**
     * The picked categories as [id => name], in the order they were asked for (ids that are not in the tree are dropped).
     *
     * @param  list<array<string, mixed>>  $tree
     * @param  int[]  $ids
     * @return array<int, string>
     */
    private function pickedCategories(array $tree, array $ids): array
    {
        $names = [];
        foreach ($tree as $c) {
            $names[$c['id']] = $c['name'];
            foreach ($c['children'] ?? [] as $child) {
                $names[$child['id']] = $child['name'];
            }
        }

        $picked = [];
        foreach ($ids as $id) {
            if (isset($names[$id])) {
                $picked[$id] = $names[$id];
            }
        }

        return $picked;
    }
}
