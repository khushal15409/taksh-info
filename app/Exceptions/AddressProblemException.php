<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

/** Address endpoints answer with the API's plain {status:false, message, data:null} shape. */
class AddressProblemException extends ApiException
{
    public function __construct(string $message, int $status)
    {
        parent::__construct($message);
        $this->status = $status;
    }

    public function render(): JsonResponse
    {
        return response()->json(['status' => false, 'message' => $this->getMessage(), 'data' => null], $this->status);
    }

    public static function notYours(): self
    {
        return new self('Address not found or does not belong to you', 404);
    }

    public static function unknownPincode(): self
    {
        return new self('We could not find this pincode in our service areas. Please check the pincode.', 422);
    }

    public static function onlyAddress(): self
    {
        return new self('Cannot delete the only address', 400);
    }
}
