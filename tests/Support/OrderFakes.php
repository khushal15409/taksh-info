<?php

namespace Tests\Support;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Repositories\Contracts\CheckoutRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\StockRepositoryInterface;
use App\Services\Orders\OrderNotifier;
use App\Services\Payments\RazorpayGateway;
use Closure;
use Generator;
use Illuminate\Support\Collection;

/** The rows orders, lines, charges and payments live in; shared by the checkout, payment and stock fakes. */
class FakeOrderDb
{
    /** @var array<int, Order> */
    public array $orders = [];

    /** @var array<int, list<OrderItem>> */
    public array $items = [];

    /** @var array<int, list<array>> */
    public array $charges = [];

    /** @var array<int, Payment> */
    public array $payments = [];

    /** @var array<string, VendorProduct> "vendor_variant" => stock row */
    public array $stock = [];

    public array $couponUsages = [];

    public array $lockedCoupons = [];

    public int $nextOrder = 1;

    public int $nextPayment = 1;

    public function stockRow(int $vendorId, int $variantId, int $qty, int $reserved = 0): VendorProduct
    {
        return $this->stock[$vendorId.'_'.$variantId] = (new VendorProduct)->forceFill(['vendor_id' => $vendorId, 'product_variant_id' => $variantId, 'stock_qty' => $qty, 'reserved_qty' => $reserved]);
    }
}

class FakeCheckoutRepository implements CheckoutRepositoryInterface
{
    /** @var array<int, Address> */
    public array $addresses = [];

    /** @var array<int, Vendor> */
    public array $vendors = [];

    public bool $groupColumn = true;

    public function __construct(public readonly FakeOrderDb $db, private readonly FakeCartRepository $cartStore) {}

    public function transaction(Closure $callback): mixed
    {
        return $callback();
    }

    public function address(int $userId, int $addressId): ?Address
    {
        $a = $this->addresses[$addressId] ?? null;

        return $a && $a->user_id === $userId ? $a : null;
    }

    public function lockedCart(int $userId): ?Cart
    {
        return $this->cartStore->forUser($userId);
    }

    public function cartItems(Cart $cart): Collection
    {
        return collect($this->cartStore->lines[$cart->id])->values();
    }

    public function vendor(int $vendorId): ?Vendor
    {
        return $this->vendors[$vendorId] ?? null;
    }

    public function lockedStock(int $vendorId, array $variantIds): Collection
    {
        return collect($variantIds)->mapWithKeys(fn ($id) => [$id => $this->db->stock[$vendorId.'_'.$id] ?? null])->filter();
    }

    public function reserveStock(VendorProduct $row, int $qty): void
    {
        $row->reserved_qty = (int) $row->reserved_qty + $qty;
    }

    public function itemSnapshot(Product $product, ProductVariant $variant): array
    {
        return ['product_name' => $product->name, 'image_url' => null];
    }

    public function lockCoupon(string $code): void
    {
        $this->db->lockedCoupons[] = $code;
    }

    public function hasCheckoutGroupColumn(): bool
    {
        return $this->groupColumn;
    }

    public function createOrder(array $attributes): Order
    {
        $order = (new Order)->forceFill($attributes + ['id' => $this->db->nextOrder++, 'created_at' => now(), 'updated_at' => now()]);
        $this->db->items[$order->id] = [];
        $this->db->charges[$order->id] = [];

        return $this->db->orders[$order->id] = $order;
    }

    public function createCharge(Order $order, array $attributes): void
    {
        $this->db->charges[$order->id][] = $attributes;
    }

    public function createItem(Order $order, array $attributes): void
    {
        $this->db->items[$order->id][] = (new OrderItem)->forceFill($attributes + ['id' => count($this->db->items[$order->id]) + 1, 'order_id' => $order->id]);
    }

    public function createPayment(array $attributes): Payment
    {
        $p = (new Payment)->forceFill($attributes + ['id' => $this->db->nextPayment++]);

        return $this->db->payments[$p->id] = $p;
    }

    public function recordCouponUsage(int $couponId, ?int $userId, int $orderId, float $discount): void
    {
        $this->db->couponUsages[] = [$couponId, $userId, $orderId, $discount];
    }

    public function removeCartLines(Cart $cart, array $lineIds): void
    {
        foreach ($lineIds as $id) {
            unset($this->cartStore->lines[$cart->id][$id]);
        }
    }

    public function cartHasItems(Cart $cart): bool
    {
        return $this->cartStore->lines[$cart->id] !== [];
    }

    public function deleteCart(Cart $cart): void
    {
        unset($this->cartStore->carts[$cart->id], $this->cartStore->lines[$cart->id]);
    }

    public function vendorSalesSince(int $vendorId, string $date): float
    {
        return 0.0;
    }

    public function withRelations(Order $order): Order
    {
        $order->setRelation('items', collect($this->db->items[$order->id]))
            ->setRelation('charges', collect($this->db->charges[$order->id])->map(fn ($c) => (new \App\Models\OrderCharge)->forceFill($c)))
            ->setRelation('vendor', $this->vendors[$order->vendor_id] ?? null)
            ->setRelation('address', $this->addresses[$order->address_id] ?? null)
            ->setRelation('latestPayment', collect($this->db->payments)->where('order_id', $order->id)->last());

        return $order;
    }

    public function summary(Order $order): array
    {
        return [
            'id' => $order->id, 'order_number' => $order->order_number, 'vendor_id' => $order->vendor_id,
            'shop_name' => $this->vendors[$order->vendor_id]->shop_name ?? null, 'grand_total' => $order->grand_total, 'items_count' => $this->itemsQty($order),
        ];
    }

    public function itemsQty(Order $order): int
    {
        return (int) collect($this->db->items[$order->id])->sum('qty');
    }
}

class FakePaymentRepository implements PaymentRepositoryInterface
{
    public bool $groupColumn = true;

    public function __construct(private readonly FakeOrderDb $db) {}

    public function transaction(Closure $callback): mixed
    {
        return $callback();
    }

    public function hasCheckoutGroupColumn(): bool
    {
        return $this->groupColumn;
    }

    public function ownedOrder(int $userId, int $orderId): ?Order
    {
        $o = $this->db->orders[$orderId] ?? null;

        return $o && $o->user_id === $userId ? $o : null;
    }

    private function paymentsOf(int $orderId): Collection
    {
        return collect($this->db->payments)->where('order_id', $orderId)->sortBy('id')->values();
    }

    public function onlinePayment(int $orderId): ?Payment
    {
        return $this->paymentsOf($orderId)->firstWhere('gateway', 'razorpay');
    }

    public function isPaid(int $orderId): bool
    {
        return $this->paymentsOf($orderId)->contains(fn ($p) => in_array($p->status, ['paid', 'success'], true));
    }

    public function openRazorpayPayment(int $orderId): ?Payment
    {
        return $this->paymentsOf($orderId)->filter(fn ($p) => $p->status === 'initiated' && $p->razorpay_order_id)->last();
    }

    public function groupOrders(string $groupId, int $userId): Collection
    {
        return collect($this->db->orders)->filter(fn ($o) => $o->checkout_group_id === $groupId && $o->user_id === $userId)->sortBy('id')->values();
    }

    public function openPaymentsByRazorpayOrder(array $orderIds): Collection
    {
        return collect($this->db->payments)->filter(fn ($p) => in_array($p->order_id, $orderIds) && $p->status === 'initiated' && $p->razorpay_order_id)->groupBy('razorpay_order_id');
    }

    public function createPayment(array $attributes): Payment
    {
        $p = (new Payment)->forceFill($attributes + ['id' => $this->db->nextPayment++]);

        return $this->db->payments[$p->id] = $p;
    }

    public function lockOrders(array $orderIds): void {}

    public function findByRazorpayOrder(string $razorpayOrderId): ?Payment
    {
        $p = collect($this->db->payments)->firstWhere('razorpay_order_id', $razorpayOrderId);

        return $p?->setRelation('order', $this->db->orders[$p->order_id] ?? null);
    }

    public function groupPayments(Payment $payment): Collection
    {
        $rows = collect($this->db->payments)->filter(fn ($p) => $p->razorpay_order_id === $payment->razorpay_order_id && $p->gateway === 'razorpay')->sortBy('id');

        return $rows->pluck('order_id')->unique()->count() < 2 ? collect() : $rows->groupBy('order_id')->map(fn ($g) => $g->last())->values();
    }

    public function anyOrderOfOtherUser(array $orderIds, int $userId): bool
    {
        return collect($orderIds)->contains(fn ($id) => $this->db->orders[$id]->user_id !== $userId);
    }

    public function lockedOrderAndPayment(int $paymentId): array
    {
        $payment = $this->db->payments[$paymentId];

        return [$this->db->orders[$payment->order_id] ?? null, $payment];
    }

    public function lockedPayment(int $paymentId): ?Payment
    {
        return $this->db->payments[$paymentId] ?? null;
    }

    public function updatePayment(Payment $payment, array $attributes): void
    {
        $payment->forceFill($attributes);
    }

    public function updateOrder(Order $order, array $attributes): void
    {
        $order->forceFill($attributes);
    }

    public function orderWithPayments(int $userId, int $orderId): ?Order
    {
        $o = $this->ownedOrder($userId, $orderId);

        return $o?->setRelation('latestPayment', $this->paymentsOf($orderId)->last());
    }
}

class FakeStockRepository implements StockRepositoryInterface
{
    public bool $marker = true;

    public function __construct(private readonly FakeOrderDb $db) {}

    public function hasCommitMarker(): bool
    {
        return $this->marker;
    }

    public function lockedRows(Order $order, ?string $only = null): Generator
    {
        foreach ($this->db->items[$order->id] ?? [] as $item) {
            $row = $this->db->stock[$order->vendor_id.'_'.$item->product_variant_id] ?? null;
            if ($row) {
                yield [$item, $row];
            }
        }
    }

    public function saveRow($row): void {}

    public function hasPaidPayment(Order $order): bool
    {
        return collect($this->db->payments)->where('order_id', $order->id)->contains(fn ($p) => $p->isPaid());
    }

    public function markCommitted(Order $order): void
    {
        $order->stock_committed_at = now();
    }
}

class FakeRazorpayGateway implements RazorpayGateway
{
    public array $created = [];

    public bool $signatureOk = true;

    /** What Razorpay answers when asked about the payment (null = unreachable). */
    public ?array $remote = ['status' => 'captured', 'method' => 'upi', 'currency' => 'INR'];

    public bool $failCreate = false;

    public array $refunds = [];

    public array $existingRefunds = [];

    public bool $failRefund = false;

    public function createRefund(string $razorpayPaymentId, int $amount, array $notes = []): array
    {
        if ($this->failRefund) {
            throw new \Exception('gateway down');
        }

        $this->refunds[] = [$razorpayPaymentId, $amount, $notes];

        return ['id' => 'rfnd_1', 'status' => 'processed', 'amount' => $amount, 'notes' => $notes];
    }

    public function fetchRefunds(string $razorpayPaymentId): array
    {
        return $this->existingRefunds;
    }

    public function createOrder(int $amount, string $currency = 'INR', array $notes = []): array
    {
        if ($this->failCreate) {
            throw new \Exception('gateway down');
        }

        $this->created[] = [$amount, $notes];

        return ['id' => 'order_rzp_'.count($this->created), 'amount' => $amount, 'currency' => $currency, 'receipt' => 'r', 'status' => 'created', 'created_at' => time()];
    }

    public function verifySignature(string $razorpayOrderId, string $razorpayPaymentId, string $razorpaySignature): bool
    {
        return $this->signatureOk;
    }

    public function fetchPayment(string $razorpayPaymentId): ?array
    {
        return $this->remote;
    }

    public function keyId(): string
    {
        return 'rzp_test_key';
    }
}

class FakeOrderNotifier implements OrderNotifier
{
    public array $placed = [];

    public array $confirmed = [];

    public array $cancelled = [];

    public function placed(iterable $orders): void
    {
        $this->placed[] = collect($orders)->pluck('id')->all();
    }

    public function paymentConfirmed(Order $order): void
    {
        $this->confirmed[] = $order->id;
    }

    public function cancelled(Order $order): void
    {
        $this->cancelled[] = $order->id;
    }
}

class FakeOrderRepository implements \App\Repositories\Contracts\OrderRepositoryInterface
{
    public array $couponReleased = [];

    public function __construct(private readonly FakeOrderDb $db, private readonly FakeCheckoutRepository $checkout) {}

    public function transaction(Closure $callback): mixed
    {
        return $callback();
    }

    public function paginateForUser(int $userId, array $filters, int $perPage): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $rows = collect($this->db->orders)->where('user_id', $userId)
            ->when(! empty($filters['order_status']), fn ($c) => $c->where('order_status', $filters['order_status']))
            ->when(($filters['delivery_mode'] ?? null) === 'quick', fn ($c) => $c->filter(fn ($o) => in_array($o->delivery_type, self::QUICK_TYPES) || $o->is_express))
            ->when(($filters['delivery_mode'] ?? null) === 'standard', fn ($c) => $c->filter(fn ($o) => ! in_array($o->delivery_type, self::QUICK_TYPES) && ! $o->is_express))
            ->sortByDesc('id')->values()->map(fn ($o) => $this->checkout->withRelations($o));

        return new \Illuminate\Pagination\LengthAwarePaginator($rows->forPage(1, $perPage)->values(), $rows->count(), $perPage, 1);
    }

    public function findForDetail(int $userId, int $orderId): ?Order
    {
        $o = $this->db->orders[$orderId] ?? null;

        return $o && $o->user_id === $userId
            ? $this->checkout->withRelations($o)->setRelation('payments', collect($this->db->payments)->where('order_id', $o->id)->values())->setRelation('refundTransactions', collect())
            : null;
    }

    public function lockedOwned(int $userId, int $orderId): ?Order
    {
        $o = $this->db->orders[$orderId] ?? null;

        return $o && $o->user_id === $userId ? $o : null;
    }

    public function setStatus(Order $order, string $status): void
    {
        $order->order_status = $status;
    }

    public function releaseCoupon(int $orderId): void
    {
        $this->couponReleased[] = $orderId;
    }

    public function paidPayment(Order $order): ?Payment
    {
        return collect($this->db->payments)->where('order_id', $order->id)->filter(fn ($p) => $p->isPaid())->sortByDesc('id')->first();
    }

    public function refundablePayment(Order $order): ?Payment
    {
        return collect($this->db->payments)->where('order_id', $order->id)->where('gateway', 'razorpay')->filter(fn ($p) => $p->isPaid() && $p->razorpay_payment_id)->sortByDesc('id')->first();
    }

    public function numberOwnedBy(int $userId, string $orderNumber): bool
    {
        return collect($this->db->orders)->contains(fn ($o) => $o->user_id === $userId && $o->order_number === $orderNumber);
    }

    /** @var array<int, Order> orders prepared for tracking (relations already set) */
    public array $tracking = [];

    public function forTracking(int $orderId): ?Order
    {
        return $this->tracking[$orderId] ?? null;
    }
}

class FakeReturnRepository implements \App\Repositories\Contracts\ReturnRepositoryInterface
{
    /** @var array<int, \App\Models\ProductReturn> */
    public array $returns = [];

    public array $returned = [];            // orderItemId => pieces on non-rejected returns

    public array $media = [];

    public ?object $pickup = null;

    private int $next = 1;

    public function __construct(private readonly FakeOrderDb $db) {}

    public function transaction(Closure $callback): mixed
    {
        return $callback();
    }

    public function ownedOrder(int $userId, int $orderId): ?Order
    {
        $o = $this->db->orders[$orderId] ?? null;

        return $o && $o->user_id === $userId ? $o : null;
    }

    public function orderItem(Order $order, int $itemId): ?\App\Models\OrderItem
    {
        return collect($this->db->items[$order->id] ?? [])->firstWhere('id', $itemId);
    }

    public function alreadyReturned(int $orderItemId): int
    {
        return $this->returned[$orderItemId] ?? 0;
    }

    public function hasOpenReturn(int $orderItemId): bool
    {
        return collect($this->returns)->contains(fn ($r) => $r->order_item_id === $orderItemId && in_array($r->status->value, \App\Enums\ReturnStatus::open()));
    }

    public function create(array $attributes): \App\Models\ProductReturn
    {
        $r = (new \App\Models\ProductReturn)->forceFill($attributes + ['id' => $this->next++, 'created_at' => now()]);
        $r->setRelation('items', collect())->setRelation('statusLogs', collect())->setRelation('media', collect())
            ->setRelation('order', $this->db->orders[$r->order_id] ?? null)
            ->setRelation('orderItem', collect($this->db->items[$r->order_id] ?? [])->firstWhere('id', $r->order_item_id));

        return $this->returns[$r->id] = $r;
    }

    public function addItem(\App\Models\ProductReturn $return, array $attributes): void
    {
        $return->items->push((new \App\Models\ReturnItem)->forceFill($attributes));
        $this->returned[$return->order_item_id] = ($this->returned[$return->order_item_id] ?? 0) + $attributes['quantity'];
    }

    public function addStatusLog(\App\Models\ProductReturn $return, array $attributes): void
    {
        $return->statusLogs->push((new \App\Models\ReturnStatusLog)->forceFill($attributes + ['created_at' => now()]));
    }

    public function ownedReturn(int $userId, int $returnId): ?\App\Models\ProductReturn
    {
        $r = $this->returns[$returnId] ?? null;

        return $r && $r->customer_id === $userId ? $r : null;
    }

    public function addMedia(\App\Models\ProductReturn $return, array $attributes): \App\Models\ReturnMedia
    {
        $m = (new \App\Models\ReturnMedia)->forceFill($attributes + ['id' => count($this->media) + 1]);
        $this->media[] = $m;
        $return->media->push($m);

        return $m;
    }

    public function paginate(int $userId, ?string $status, int $perPage): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $rows = collect($this->returns)->where('customer_id', $userId)->when($status, fn ($c) => $c->filter(fn ($r) => $r->status->value === $status))->values();

        return new \Illuminate\Pagination\LengthAwarePaginator($rows, $rows->count(), $perPage, 1);
    }

    public function detail(int $userId, int $returnId): ?\App\Models\ProductReturn
    {
        return $this->ownedReturn($userId, $returnId);
    }

    public function statusHistory(\App\Models\ProductReturn $return): Collection
    {
        return $return->statusLogs->sortBy('created_at')->values();
    }

    public function pickup(int $returnId): ?object
    {
        return $this->pickup;
    }
}

class FakeReturnMediaStore implements \App\Services\Returns\ReturnMediaStore
{
    public array $stored = [];

    public function put(\Illuminate\Http\UploadedFile $file): string
    {
        return $this->stored[] = 'returns/031126/return_'.count($this->stored).'.jpg';
    }
}

class FakeShipmentSync implements \App\Services\Logistics\ShipmentStatusSync
{
    public array $cancelled = [];

    public function cancelled(Order $order): void
    {
        $this->cancelled[] = $order->id;
    }
}
