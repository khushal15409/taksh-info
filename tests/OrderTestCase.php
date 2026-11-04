<?php

namespace Tests;

use App\Models\Address;
use App\Repositories\Contracts\CheckoutRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\StockRepositoryInterface;
use App\Services\Orders\OrderNotifier;
use App\Services\Payments\RazorpayGateway;
use Tests\Support\FakeCheckoutRepository;
use Tests\Support\FakeOrderRepository;
use Tests\Support\FakeReturnMediaStore;
use Tests\Support\FakeReturnRepository;
use Tests\Support\FakeShipmentSync;
use Tests\Support\FakeOrderDb;
use Tests\Support\FakeOrderNotifier;
use Tests\Support\FakePaymentRepository;
use Tests\Support\FakeRazorpayGateway;
use Tests\Support\FakeStockRepository;
use Tests\Support\FakeVendor;

require_once __DIR__.'/Support/OrderFakes.php';

require_once __DIR__.'/Support/QuickFakes.php';

abstract class OrderTestCase extends CartTestCase
{
    protected FakeOrderDb $db;

    protected FakeCheckoutRepository $checkoutStore;

    protected FakePaymentRepository $paymentStore;

    protected FakeStockRepository $stockStore;

    protected FakeRazorpayGateway $razorpay;

    protected FakeOrderNotifier $notifier;

    protected FakeOrderRepository $orderStore;

    protected FakeReturnRepository $returnStore;

    protected FakeReturnMediaStore $mediaStore;

    protected FakeShipmentSync $shipments;

    protected \Tests\Support\FakeQuickDispatch $dispatch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = new FakeOrderDb;
        $this->app->instance(CheckoutRepositoryInterface::class, $this->checkoutStore = new FakeCheckoutRepository($this->db, $this->cartStore));
        $this->app->instance(PaymentRepositoryInterface::class, $this->paymentStore = new FakePaymentRepository($this->db));
        $this->app->instance(StockRepositoryInterface::class, $this->stockStore = new FakeStockRepository($this->db));
        $this->app->instance(RazorpayGateway::class, $this->razorpay = new FakeRazorpayGateway);
        $this->app->instance(OrderNotifier::class, $this->notifier = new FakeOrderNotifier);
        $this->app->instance(\App\Services\Quick\QuickOrderDispatch::class, $this->dispatch = new \Tests\Support\FakeQuickDispatch);
        $this->app->instance(\App\Repositories\Contracts\OrderRepositoryInterface::class, $this->orderStore = new FakeOrderRepository($this->db, $this->checkoutStore));
        $this->app->instance(\App\Repositories\Contracts\ReturnRepositoryInterface::class, $this->returnStore = new FakeReturnRepository($this->db));
        $this->app->instance(\App\Services\Returns\ReturnMediaStore::class, $this->mediaStore = new FakeReturnMediaStore);
        $this->app->instance(\App\Services\Logistics\ShipmentStatusSync::class, $this->shipments = new FakeShipmentSync);

        // the shop (id 7) is approved and serves everywhere; the customer (id 5) has one address
        $this->checkoutStore->vendors[7] = (new FakeVendor)->forceFill(['id' => 7, 'status' => 'approved', 'shop_name' => 'Fresh Mart']);
        $this->checkoutStore->addresses[1] = (new Address)->forceFill(['id' => 1, 'user_id' => 5, 'pincode' => '395001', 'name' => 'Asha', 'mobile' => '9876543210', 'address_line_1' => '12 Ring Road', 'type' => 'home', 'is_default' => true]);
    }

    /** Put `$qty` of a variant (sold by vendor 7 unless the product says otherwise) in the signed-in customer's cart. */
    protected function inCart(\App\Models\ProductVariant $variant, int $qty = 1, int $userId = 5): void
    {
        $cart = $this->cartStore->firstOrCreateForUser($userId);
        $this->cartStore->addLine($cart, $variant, $qty, fn () => null);
    }

    protected function place(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->customer(5))->postJson('/order/place', $extra + ['address_id' => 1, 'delivery_type' => 'normal', 'payment_method' => 'cod']);
    }

    /** A sellable variant that also has stock rows for the order's reservation (vendor 7 unless the product says otherwise). */
    protected function sold(int $variantId, float $payable = 100, int $free = 10, array $product = []): \App\Models\ProductVariant
    {
        $variant = $this->sellable($variantId, $payable, $free, $product);
        $this->db->stockRow($variant->product->vendor_id ?? 7, $variantId, $free);

        return $variant;
    }
}
