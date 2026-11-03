<?php

namespace Tests\Unit;

use App\Helpers\Money;
use App\Helpers\PriceBreakup;
use Tests\HomeTestCase;
use Tests\Support\FakeCheckoutRepository;
use Tests\Support\Make;

class PriceBreakupTest extends HomeTestCase
{
    public function test_money_is_converted_to_exact_paise(): void
    {
        $this->assertSame(123456, Money::toMinorUnits(1234.56));         // 1234.56 * 100 would truncate to 123455
        $this->assertSame(10000, Money::toMinorUnits('100'));
        $this->assertSame(1, Money::toMinorUnits(0.005));
        $this->assertSame(0, Money::toMinorUnits(0));
    }

    public function test_the_split_of_a_price_takes_gst_out_and_keeps_the_settlement(): void
    {
        // selling price 105 with a 5% slab and the shipped Taksh charge band (49 below 100, 79 from 100)
        $a = PriceBreakup::make(105, ['gst_slab' => 5, 'commission_percent' => 10]);

        $this->assertSame(100.0, $a['actual_price']);
        $this->assertSame(5.0, $a['gst_amount']);
        $this->assertSame(79.0, $a['taksh_charge']);
        $this->assertSame(184.0, $a['customer_price']);                  // what the customer pays: price + charge
        $this->assertSame(10.0, $a['commission_amount']);                // 10% of the price without GST
        $this->assertSame(66.95, $a['taksh_charge_base']);               // 79 without its 18% GST
        $this->assertSame(2.1, $a['gateway_fee']);                       // 2% of the selling price
        $this->assertSame(0.38, $a['gateway_fee_gst']);
        $this->assertSame(92.52, $a['settlement_amount']);               // 105 - 10 - 2.10 - 0.38
    }

    public function test_a_product_with_its_own_charge_and_the_tds_threshold(): void
    {
        $this->assertSame(10.0, PriceBreakup::make(100, ['gst_slab' => 0, 'taksh_charge' => 10])['taksh_charge']);

        $repo = new FakeCheckoutRepository(new \Tests\Support\FakeOrderDb, new \Tests\Support\FakeCartRepository);
        $this->app->instance(\App\Repositories\Contracts\CheckoutRepositoryInterface::class, $repo);
        \App\Helpers\TaxThreshold::flush();

        $product = Make::product(1, ['vendor_id' => 7, 'gst_slab' => 0, 'tds_percent' => 1, 'taksh_charge' => null, 'commission_percent' => 0, 'tcs_percent' => 0, 'vendor_gst_percent' => 0]);
        $line = PriceBreakup::forOrderItem(Make::variant(['price' => 100, 'sale_price' => null]), $product);

        $this->assertSame(0, $line['tds_percent']);                      // the shop's year-to-date sales (0) are under the limit: no TDS yet
        $this->assertSame(0.0, $line['tds_amount']);
        $this->assertSame(7, $line['return_days']);                      // the shipped default return window
        $this->assertEquals(79, $line['return_charge']);
        $this->assertEquals(2, $line['gateway_percent']);
    }
}
