<?php

namespace App\Repositories\Eloquent;

use App\Models\CallbackRequest;
use App\Models\DeliveryBoyJoinRequest;
use App\Models\DevelopmentRequest;
use App\Models\VendorRequest;
use App\Repositories\Contracts\SupportRequestRepositoryInterface;

class SupportRequestRepository implements SupportRequestRepositoryInterface
{
    public function openCallbackFor(int $userId): ?CallbackRequest
    {
        return CallbackRequest::where('user_id', $userId)->where('status', 'open')->first();
    }

    public function createCallback(array $attributes): CallbackRequest
    {
        return CallbackRequest::create($attributes);
    }

    public function createJoinRequest(array $attributes): DeliveryBoyJoinRequest
    {
        return DeliveryBoyJoinRequest::create($attributes);
    }

    public function createVendorRequest(array $attributes): VendorRequest
    {
        return VendorRequest::create($attributes);
    }

    public function createDevelopmentRequest(array $attributes): DevelopmentRequest
    {
        return DevelopmentRequest::create($attributes);
    }
}
