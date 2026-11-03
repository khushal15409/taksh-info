<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Http\Resources\Account\WishlistItemResource;
use App\Models\Wishlist;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use App\Repositories\Contracts\WishlistRepositoryInterface;

class WishlistService
{
    public function __construct(
        private readonly WishlistRepositoryInterface $wishlist,
        private readonly CatalogRepositoryInterface $catalog,
        private readonly PricingService $pricing,
    ) {}

    /** @return array{items: array, pagination: array<string, int>} */
    public function list(int $userId, int $perPage = 15): array
    {
        $page = $this->wishlist->paginate($userId, $perPage);
        $rows = collect($page->items());

        // free stock of every listed variant at its product's vendor, read once for the whole page
        $stock = $this->catalog->vendorStock($rows->flatMap(fn (Wishlist $r) => $r->product->variants->pluck('id'))->all());

        $items = $rows->map(function (Wishlist $row) use ($stock) {
            $product = $row->product;
            $active = $product->variants->where('status', 'active');
            // a variant is buyable when it is active and has stock at the vendor (a product without a vendor is not stock-tracked)
            $buyable = $active->filter(function ($v) use ($product, $stock) {
                if (! $product->vendor_id) {
                    return true;
                }
                $vp = $stock->get($product->vendor_id.'_'.$v->id);

                return $vp && max(0, (int) $vp->stock_qty - (int) $vp->reserved_qty) > 0;
            });

            // price of the cheapest variant that can really be bought; when none can, the cheapest one
            $variant = ($buyable->isNotEmpty() ? $buyable : $active)->sortBy('price')->first();

            return WishlistItemResource::make((object) [
                'row' => $row,
                'product' => $product,
                'final_price' => $variant ? $this->pricing->finalPrice($product, $variant) : 0.0,
                'mrp' => $variant ? $this->pricing->mrp($variant) : 0.0,
                'in_stock' => $product->status === 'active' && $buyable->isNotEmpty(),
            ])->resolve();
        })->values()->all();

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ];
    }

    /** Saving a product that is already saved changes nothing. */
    public function add(int $userId, int $productId): void
    {
        $product = $this->wishlist->product($productId);

        if (! $product || $product->status !== 'active') {
            throw new class('api.product_not_approved', 422) extends ApiException
            {
                public function __construct(string $m, int $s)
                {
                    parent::__construct($m);
                    $this->status = $s;
                }
            };
        }

        $this->wishlist->add($userId, $productId);
    }

    /** @return bool false when the product was not on the wishlist */
    public function remove(int $userId, int $productId): bool
    {
        return $this->wishlist->remove($userId, $productId);
    }
}
