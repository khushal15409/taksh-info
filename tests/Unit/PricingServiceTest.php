<?php

namespace Tests\Unit;

use App\Services\PricingService;
use Tests\HomeTestCase;
use Tests\Support\Make;

class PricingServiceTest extends HomeTestCase
{
    public function test_saved_customer_price_wins(): void
    {
        $variant = Make::variant(['price' => 120, 'sale_price' => 100, 'customer_price' => 179]);

        $this->assertSame(179.0, app(PricingService::class)->finalPrice(Make::product(1), $variant));
    }

    public function test_without_a_saved_price_the_taksh_charge_band_is_added(): void
    {
        $pricing = app(PricingService::class);

        // below the Rs 100 break: charge 49; at/above: 79 (shipped defaults)
        $this->assertSame(99.0, $pricing->finalPrice(Make::product(1), Make::variant(['price' => 60, 'sale_price' => 50])));
        $this->assertSame(229.0, $pricing->finalPrice(Make::product(1), Make::variant(['price' => 200, 'sale_price' => 150])));
        // no sale price: the MRP is the selling price
        $this->assertSame(279.0, $pricing->finalPrice(Make::product(1), Make::variant(['price' => 200])));
    }

    public function test_admin_changed_band_and_product_charge_are_respected(): void
    {
        $this->settings->values = ['taksh_charge_threshold' => '500', 'taksh_charge_below' => '10', 'taksh_charge_above' => '20'];
        $pricing = app(PricingService::class);

        $this->assertSame(160.0, $pricing->finalPrice(Make::product(1), Make::variant(['price' => 200, 'sale_price' => 150])));
        $this->assertSame(155.0, $pricing->finalPrice(Make::product(1, ['taksh_charge' => 5]), Make::variant(['price' => 200, 'sale_price' => 150])));
    }

    public function test_a_sale_price_passed_in_wins_and_discount_percent_is_rounded(): void
    {
        $pricing = app(PricingService::class);

        $this->assertSame(139.0, $pricing->finalPrice(Make::product(1), Make::variant(['customer_price' => 500]), 90));
        $this->assertSame(25, $pricing->discountPercent(200, 150));
        $this->assertSame(0, $pricing->discountPercent(100, 120));
        $this->assertSame(0, $pricing->discountPercent(0, 50));
    }

    public function test_no_variant_means_a_zero_price(): void
    {
        $this->assertSame(0.0, app(PricingService::class)->finalPrice(Make::product(1), null));
        $this->assertSame(0.0, app(PricingService::class)->mrp(null));
    }
}
