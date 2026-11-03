<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

/** Why a cart action was refused: a message (key or text), an HTTP status and optional typed data for the client. */
class CartProblemException extends ApiException
{
    public function __construct(string $message, int $status = 422, private readonly ?array $data = null)
    {
        parent::__construct($message);
        $this->status = $status;
    }

    public function render(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $this->resolveMessage($this->getMessage()), 'data' => $this->data], $this->status);
    }

    public static function notAvailable(): self
    {
        return new self('api.product_not_available');
    }

    public static function vendorNotSet(): self
    {
        return new self('api.product_vendor_not_set');
    }

    public static function vendorUnavailable(): self
    {
        return new self('api.vendor_not_available');
    }

    public static function insufficientStock(int $available, bool $quick = false): self
    {
        return new self('api.insufficient_stock', 422, array_filter([
            'available_stock' => $available,
            'code' => $quick ? 'item_unavailable' : null,
            'availability_status' => $quick ? 'out_of_stock' : null,
        ], fn ($v) => $v !== null));
    }

    public static function bucketMismatch(bool $productIsQuick): self
    {
        return new self('api.express.bucket_mismatch', 422, [
            'code' => 'delivery_bucket_mismatch',
            'product_delivery_type' => $productIsQuick ? '30_min' : 'normal',
        ]);
    }

    public static function quickOnApp(): self
    {
        return new self('api.cart_quick_on_app', 422, ['code' => 'quick_on_app']);
    }

    public static function notFoundInCart(): self
    {
        return new self('api.not_found_in_cart', 404);
    }

    public static function emptyCart(): self
    {
        return new self('api.cart_empty', 400);
    }

    public static function coupon(string $message): self
    {
        return new self($message, 400);
    }
}
