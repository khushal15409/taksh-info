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

        // shipped default bands: below Rs 200 -> 20, Rs 200 and above -> 59
        $this->assertSame(70.0, $pricing->finalPrice(Make::product(1), Make::variant(['price' => 60, 'sale_price' => 50])));
        $this->assertSame(309.0, $pricing->finalPrice(Make::product(1), Make::variant(['price' => 300, 'sale_price' => 250])));
        // no sale price: the MRP is the selling price, and exactly Rs 200 is in the upper band
        $this->assertSame(259.0, $pricing->finalPrice(Make::product(1), Make::variant(['price' => 200])));
    }

    public function test_admin_changed_bands_and_product_charge_are_respected(): void
    {
        $this->useTakshBands([
            ['from' => 0, 'to' => 500, 'charge' => 10],
            ['from' => 500, 'to' => null, 'charge' => 20],
        ]);
        $pricing = app(PricingService::class);

        $this->assertSame(160.0, $pricing->finalPrice(Make::product(1), Make::variant(['price' => 200, 'sale_price' => 150])));
        $this->assertSame(520.0, $pricing->finalPrice(Make::product(1), Make::variant(['price' => 500])));
        // a charge saved on the product beats the band
        $this->assertSame(155.0, $pricing->finalPrice(Make::product(1, ['taksh_charge' => 5]), Make::variant(['price' => 200, 'sale_price' => 150])));
    }

    public function test_a_price_in_a_middle_band_gets_that_bands_charge(): void
    {
        $this->useTakshBands([
            ['from' => 0, 'to' => 100, 'charge' => 10],
            ['from' => 100, 'to' => 500, 'charge' => 30],
            ['from' => 500, 'to' => null, 'charge' => 50],
        ]);
        $pricing = app(PricingService::class);

        $this->assertSame(109.0, $pricing->finalPrice(Make::product(1), Make::variant(['price' => 99])));
        $this->assertSame(130.0, $pricing->finalPrice(Make::product(1), Make::variant(['price' => 100])));
        $this->assertSame(529.0, $pricing->finalPrice(Make::product(1), Make::variant(['price' => 499])));
        $this->assertSame(550.0, $pricing->finalPrice(Make::product(1), Make::variant(['price' => 500])));
    }

    public function test_a_sale_price_passed_in_wins_and_discount_percent_is_rounded(): void
    {
        $pricing = app(PricingService::class);

        $this->assertSame(110.0, $pricing->finalPrice(Make::product(1), Make::variant(['customer_price' => 500]), 90));
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
