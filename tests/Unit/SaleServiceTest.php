<?php

namespace Tests\Unit;

use App\Services\SaleService;
use Tests\HomeTestCase;
use Tests\Support\Make;

class SaleServiceTest extends HomeTestCase
{
    public function test_no_sales_means_no_discount(): void
    {
        $this->assertNull(app(SaleService::class)->applySale(Make::product(1)));
    }

    public function test_a_global_percentage_sale_discounts_the_cheapest_variant(): void
    {
        $this->sales->sales = collect([Make::sale(1, ['discount_value' => 20])]);
        $product = Make::product(1, [], [Make::variant(['price' => 200]), Make::variant(['price' => 100])]);

        $sale = app(SaleService::class)->applySale($product);

        $this->assertSame(1, $sale['sale_id']);
        $this->assertSame(100.0, $sale['original_price']);
        $this->assertSame(80.0, $sale['sale_price']);
        $this->assertSame('20.00% OFF', $sale['discount_label']);
    }

    public function test_a_flat_discount_never_exceeds_the_price(): void
    {
        $this->sales->sales = collect([Make::sale(1, ['discount_type' => 'flat', 'discount_value' => 500])]);

        $sale = app(SaleService::class)->applySale(Make::product(1, [], [Make::variant(['price' => 120])]));

        $this->assertSame(0.0, $sale['sale_price']);
    }

    public function test_the_highest_priority_applicable_sale_wins(): void
    {
        $this->sales->sales = collect([
            Make::sale(1, ['priority' => 1]),
            Make::sale(2, ['priority' => 5, 'sale_type' => 'category']),
            Make::sale(3, ['priority' => 9, 'sale_type' => 'category']),
        ]);
        $this->sales->categoryIds = [2 => [1], 3 => [99]];   // sale 3 is for another category

        $this->assertSame(2, app(SaleService::class)->applySale(Make::product(1, ['category_id' => 1]))['sale_id']);
    }

    public function test_product_fc_and_express_rules(): void
    {
        $service = fn () => app(SaleService::class);

        $this->sales->sales = collect([Make::sale(1, ['sale_type' => 'product'])]);
        $this->sales->productIds = [1 => [7]];
        $this->assertNotNull($service()->applySale(Make::product(7)));
        $this->assertNull($service()->applySale(Make::product(8)));

        $this->sales->sales = collect([Make::sale(2, ['sale_type' => 'fc'])]);
        $this->sales->fcIds = [2 => [4]];
        $this->assertNull(app(SaleService::class)->applySale(Make::product(1, ['fulfillment_center_id' => 4])));          // no center given
        $this->assertNotNull(app(SaleService::class)->applySale(Make::product(1, ['fulfillment_center_id' => 4]), 4));
        $this->assertNull(app(SaleService::class)->applySale(Make::product(1, ['fulfillment_center_id' => 5]), 4));

        $this->sales->sales = collect([Make::sale(3, ['sale_type' => 'express_30'])]);
        $this->sales->expressCenters = [4];
        $this->assertNull(app(SaleService::class)->applySale(Make::product(1, ['is_express_30' => false]), 4));
        $this->assertNotNull(app(SaleService::class)->applySale(Make::product(1, ['is_express_30' => true]), 4));
        $this->assertNull(app(SaleService::class)->applySale(Make::product(1, ['is_express_30' => true]), 9));            // center does not support express
    }

    public function test_active_sales_can_be_narrowed_to_a_center(): void
    {
        $this->sales->sales = collect([Make::sale(1), Make::sale(2, ['sale_type' => 'fc']), Make::sale(3, ['sale_type' => 'fc'])]);
        $this->sales->fcIds = [2 => [4], 3 => [8]];

        $ids = app(SaleService::class)->getActiveSales(4)->pluck('id')->sort()->values()->all();

        $this->assertSame([1, 2], $ids);
    }
}
