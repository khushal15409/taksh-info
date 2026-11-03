<?php

namespace App\Services;

use App\Exceptions\SupportRequestException;
use App\Http\Resources\Support\SupportRequestResource;
use App\Models\User;
use App\Repositories\Contracts\SupportRequestRepositoryInterface;

/**
 * Saves the customer's enquiries into the admin's queues (status "open"), as the app's API does. When a customer is logged in
 * their account is attached; the callback always uses the logged-in customer's own name and number, never the request's.
 */
class SupportRequestService
{
    public function __construct(private readonly SupportRequestRepositoryInterface $requests) {}

    /** One open callback per customer: they wait for the first before asking again. */
    public function requestCallback(User $user): array
    {
        if ($this->requests->openCallbackFor($user->id)) {
            throw SupportRequestException::callbackAlreadyOpen();
        }

        $row = $this->requests->createCallback(['user_id' => $user->id, 'name' => $user->name ?: 'User', 'mobile' => $user->mobile, 'status' => 'open']);

        return SupportRequestResource::make($row)->additional(['fields' => ['name', 'mobile']])->resolve();
    }

    public function joinAsDeliveryPartner(array $v): array
    {
        $row = $this->requests->createJoinRequest([
            'name' => $v['name'], 'mobile' => $v['mobile'], 'email' => $v['email'] ?? null, 'address' => $v['address'] ?? null,
            'pincode' => $v['pincode'] ?? null, 'description' => $v['description'] ?? null, 'status' => 'open',
        ]);

        return SupportRequestResource::make($row)->resolve();
    }

    public function registerVendor(?User $user, array $v): array
    {
        $row = $this->requests->createVendorRequest([
            'user_id' => $user?->id, 'name' => $user ? $user->name : ($v['name'] ?? null), 'mobile' => $v['mobile'], 'email' => $v['email'],
            'description' => $v['description'], 'business_type' => $v['business_type'], 'status' => 'open',
        ]);

        return SupportRequestResource::make($row)->additional(['fields' => ['business_type', 'mobile', 'email']])->resolve();
    }

    public function requestDevelopment(?User $user, array $v): array
    {
        $row = $this->requests->createDevelopmentRequest([
            'user_id' => $user?->id, 'name' => $user ? $user->name : ($v['name'] ?? null), 'mobile' => $v['mobile'], 'email' => $v['email'],
            'description' => $v['description'], 'request_type' => $v['request_type'], 'status' => 'open',
        ]);

        return SupportRequestResource::make($row)->additional(['fields' => ['request_type', 'mobile', 'email']])->resolve();
    }
}
