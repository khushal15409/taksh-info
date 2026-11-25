<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Web\WebController;
use App\Services\CatalogService;
use App\Services\ReviewService;
use App\Support\Presenters\ProductCard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

/**
 * One product: /product/{key} (?variant= picks an option). The gallery, price, stock, description, details, reviews, Q&A and similar
 * products are rendered here; the delivery-pincode check, add to cart and posting a review are small calls from the page.
 * Opening the page also remembers it for the customer's "recently viewed" (CatalogService::detail does that).
 */
class ProductPageController extends WebController
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly ReviewService $reviews,
    ) {}

    /** The old /product?id= (and the page scripts' links): sent on to the keyed address, keeping ?variant=. */
    public function legacy(Request $request): RedirectResponse|Response
    {
        $raw = (string) $request->query('id', '');
        $id = ctype_digit($raw) ? (int) $raw : (\App\Support\UrlKey::decode('product', $raw) ?? 0);

        return $id
            ? redirect()->route('product', array_filter(['id' => $id, 'variant' => $request->query('variant')]), 301)
            : $this->notFound('catalog.product', ['product' => null]);
    }

    public function show(Request $request, int $id): View|Response
    {
        $user = $request->user();
        $product = $id ? $this->catalog->detail($id, $user) : null;

        if (! $product) {
            return $this->notFound('catalog.product', ['product' => null]);
        }

        $variants = collect($product['variants']);
        $variant = $variants->firstWhere('id', (int) $request->query('variant'))
            ?? $variants->sortBy([['in_stock', 'desc'], ['sale_price', 'asc']])->first()
            ?? ['id' => 0, 'price' => $product['original_price'], 'sale_price' => $product['sale_price'], 'in_stock' => $product['in_stock'], 'images' => [], 'variant_attributes' => []];

        $similar = $this->catalog->similar($id);

        return $this->page('catalog.product', [
            'product' => $product,
            'variant' => $variant,
            'similar' => array_map(fn (array $s) => ProductCard::fromSimilar($s), $similar['data']),
            'reviewState' => $user ? $this->reviews->status($user, $id) : null,
        ]);
    }
}
