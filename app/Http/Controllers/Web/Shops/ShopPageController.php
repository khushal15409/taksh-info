<?php

namespace App\Http\Controllers\Web\Shops;

use App\Http\Controllers\Web\WebController;
use App\Http\Requests\Web\Catalog\ListingFilters;
use App\Services\CatalogService;
use App\Services\ShopService;
use App\Support\FilterToken;
use App\Support\Presenters\ProductCard;
use Illuminate\Http\Request;

/** /shops (all shops: ?q= &category[]= &sort= &page=) and /shops/{id} (one shop's products: ?category[]= &max= &sort= &page=). */
class ShopPageController extends WebController
{
    public const PRICE_MAX = 100000;

    public const BANNERS = 5;

    /** The dark colour that goes with each banner photo (the header fades from it into the picture). */
    public const BANNER_TONES = [1 => '#1e293b', 2 => '#7f1d1d', 3 => '#0f3d4c', 4 => '#7c2d12', 5 => '#14532d'];

    public function __construct(private readonly ShopService $shops, private readonly CatalogService $catalog) {}

    public function index(Request $request)
    {
        $q = $this->term($request);

        $data = $this->shops->index($q);
        $data['page']->withPath(route('shops'))->appends(FilterToken::pageQuery('shops', $request));

        return $this->page('shops.index', $data + ['q' => $q]);
    }

    public function show(Request $request, int $id)
    {
        $detail = $this->shops->detail($id);
        if (! $detail) {
            return $this->notFound('shops.missing');
        }

        $q = $this->term($request);
        $picked = $this->ids($request->query('category', []));
        $max = is_numeric($request->query('max')) && $request->query('max') > 0 ? min((int) $request->query('max'), self::PRICE_MAX) : 0;
        $min = is_numeric($request->query('min')) && $request->query('min') > 0 ? min((int) $request->query('min'), self::PRICE_MAX) : 0;
        if ($max && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        $page = $this->catalog->list(array_filter([
            'vendor_id' => $id,
            'search' => $q ?: null,
            'category_ids' => $picked ?: null,
            'min_price' => $min ?: null,
            'max_price' => $max ?: null,
            'limit' => ListingFilters::PER_PAGE,
        ]));
        $page->withPath(route('shop', ['id' => $id]))->appends(FilterToken::pageQuery('shop', $request));

        return $this->page('shops.show', $detail + [
            'products' => $page,
            'cards' => collect($page->items())->map(fn (array $p) => ProductCard::fromList($p))->all(),
            'q' => $q,
            'picked' => $picked,
            'min' => $min,
            'max' => $max,
            'priceMax' => self::PRICE_MAX,
            'shareUrl' => route('shop', ['id' => \App\Support\UrlKey::enabled() ? \App\Support\UrlKey::slug('shop', $id, $detail['shop']['name']) : $id]),
            // one of the five banner pictures (and its matching colour), a new pick on every visit
            ...$this->banner(),
        ]);
    }

    /** @return array{banner: string, tone: string} */
    private function banner(): array
    {
        $n = random_int(1, self::BANNERS);

        return ['banner' => asset('assets/img/shop-banners/banner-'.$n.'.jpg'), 'tone' => self::BANNER_TONES[$n]];
    }

    private function term(Request $request): string
    {
        $q = $request->query('q', '');

        return is_string($q) ? mb_substr(trim($q), 0, 255) : '';
    }

    /** @return int[] */
    private function ids(mixed $raw): array
    {
        return collect(is_array($raw) ? $raw : [$raw])->filter(fn ($v) => is_scalar($v))->map(fn ($v) => (int) $v)
            ->filter(fn (int $v) => $v > 0)->unique()->take(50)->values()->all();
    }
}
