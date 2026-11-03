<?php

namespace Tests;

use App\Models\ProductVariant;
use App\Models\VendorProduct;
use App\Repositories\Contracts\CartCatalogRepositoryInterface;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\CouponRepositoryInterface;
use Tests\Support\FakeCartCatalogRepository;
use Tests\Support\FakeCartRepository;
use Tests\Support\FakeCouponRepository;
use Tests\Support\Make;

require_once __DIR__.'/Support/CartFakes.php';

abstract class CartTestCase extends CatalogTestCase
{
    protected FakeCartRepository $cartStore;

    protected FakeCartCatalogRepository $cartCatalog;

    protected FakeCouponRepository $couponStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(CartRepositoryInterface::class, $this->cartStore = new FakeCartRepository);
        $this->app->instance(CartCatalogRepositoryInterface::class, $this->cartCatalog = new FakeCartCatalogRepository);
        $this->app->instance(CouponRepositoryInterface::class, $this->couponStore = new FakeCouponRepository);
    }

    /** A variant on sale at the fake vendor (id 7) with `$free` pieces in stock; price is what the customer pays. */
    protected function sellable(int $variantId, float $payable = 100, int $free = 10, array $product = [], array $variant = []): ProductVariant
    {
        $p = Make::product($variantId * 10, $product + ['vendor_id' => 7, 'name' => "Item $variantId"]);
        $v = Make::variant($variant + ['id' => $variantId, 'product_id' => $p->id, 'sku' => "SKU$variantId", 'price' => $payable + 20, 'customer_price' => 0, 'sale_price' => $payable - 49]);
        // the Taksh charge band makes a selling price of ($payable - 49) cost $payable (below the break: +49)
        $v->setRelation('product', $p)->setRelation('images', collect());
        $p->setRelation('variants', collect([$v]));

        $this->cartCatalog->variants[$variantId] = $v;
        $this->cartCatalog->free['7_'.$variantId] = $free;
        $this->catalog->stock->put('7_'.$variantId, (new VendorProduct)->forceFill(['vendor_id' => 7, 'product_variant_id' => $variantId, 'stock_qty' => $free, 'reserved_qty' => 0]));

        return $v;
    }
}
