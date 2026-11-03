<?php

namespace App\Repositories\Contracts;

use App\Models\CallbackRequest;
use App\Models\DeliveryBoyJoinRequest;
use App\Models\DevelopmentRequest;
use App\Models\VendorRequest;

/** The enquiry queues the admin works through: callbacks, delivery-partner applications, vendor and development requests. */
interface SupportRequestRepositoryInterface
{
    public function openCallbackFor(int $userId): ?CallbackRequest;

    public function createCallback(array $attributes): CallbackRequest;

    public function createJoinRequest(array $attributes): DeliveryBoyJoinRequest;

    public function createVendorRequest(array $attributes): VendorRequest;

    public function createDevelopmentRequest(array $attributes): DevelopmentRequest;
}
