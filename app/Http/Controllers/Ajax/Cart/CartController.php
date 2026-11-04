<?php

namespace App\Http\Controllers\Ajax\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\CartIndexRequest;
use App\Http\Requests\Cart\ClearCartRequest;
use App\Http\Requests\Cart\CouponRequest;
use App\Http\Requests\Cart\RemoveCouponRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Services\CartService;
use App\Services\GuestCartSession;
use App\Traits\ApiResponseTrait;
use App\Support\CartOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly CartService $cart,
        private readonly GuestCartSession $guest,
        private readonly \App\Services\Quick\QuickLocation $location,
    ) {}

    /** POST /cart/add {product_variant_id, qty, delivery_type?} - a repeated add adds to the quantity */
    public function add(AddToCartRequest $request): JsonResponse
    {
        $v = $request->validated();

        $quick = \App\Support\DeliveryBucket::isQuick($request->deliveryType());

        return $this->success($this->cart->add($this->owner($request), (int) $v['product_variant_id'], (int) $v['qty'], $request->deliveryType(), $quick ? $this->location->for($request) : null), 'api.item_added_to_cart');
    }

    /** GET /cart/cart?delivery_type=normal|30_min */
    public function index(CartIndexRequest $request): JsonResponse
    {
        return $this->success($this->cart->get($this->owner($request), $request->deliveryType()), 'api.cart_fetched');
    }

    /** POST /cart/update {cart_item_id, qty} */
    public function update(UpdateCartItemRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->success($this->cart->update($this->owner($request), (int) $v['cart_item_id'], (int) $v['qty'], $request->deliveryType()), 'api.cart_updated');
    }

    /** POST /cart/item/{id} - removes the line */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $type = $request->input('delivery_type');

        return $this->success($this->cart->remove($this->owner($request), $id, is_string($type) && $type !== '' ? $type : null), 'api.item_removed_from_cart');
    }

    /** DELETE /cart/clear?delivery_type= */
    public function clear(ClearCartRequest $request): JsonResponse
    {
        return $this->success($this->cart->clear($this->owner($request), $request->deliveryType()), 'api.cart_cleared');
    }

    /** POST /cart/coupon/apply {coupon_code, delivery_type?} */
    public function applyCoupon(CouponRequest $request): JsonResponse
    {
        return $this->success($this->cart->applyCoupon($this->owner($request), $request->validated('coupon_code'), $request->deliveryType()), 'api.coupon_applied');
    }

    /** POST /cart/coupon/remove */
    public function removeCoupon(RemoveCouponRequest $request): JsonResponse
    {
        return $this->success($this->cart->removeCoupon($this->owner($request), $request->deliveryType()), 'api.coupon_removed');
    }

    private function owner(Request $request): CartOwner
    {
        return $this->guest->owner($request->user());
    }
}
