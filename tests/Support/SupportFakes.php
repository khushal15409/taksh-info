<?php

namespace Tests\Support;

use App\Models\CallbackRequest;
use App\Models\DeliveryBoyJoinRequest;
use App\Models\DevelopmentRequest;
use App\Models\VendorRequest;
use App\Repositories\Contracts\SupportRequestRepositoryInterface;

class FakeSupportRequestRepository implements SupportRequestRepositoryInterface
{
    public array $callbacks = [];

    public array $joins = [];

    public array $vendors = [];

    public array $developments = [];

    private function make(string $class, array $attrs, array &$store)
    {
        $row = (new $class)->forceFill($attrs + ['id' => count($store) + 1, 'created_at' => now()]);
        $store[] = $row;

        return $row;
    }

    public function openCallbackFor(int $userId): ?CallbackRequest
    {
        return collect($this->callbacks)->first(fn ($c) => $c->user_id === $userId && $c->status === 'open');
    }

    public function createCallback(array $attributes): CallbackRequest
    {
        return $this->make(CallbackRequest::class, $attributes, $this->callbacks);
    }

    public function createJoinRequest(array $attributes): DeliveryBoyJoinRequest
    {
        return $this->make(DeliveryBoyJoinRequest::class, $attributes, $this->joins);
    }

    public function createVendorRequest(array $attributes): VendorRequest
    {
        return $this->make(VendorRequest::class, $attributes, $this->vendors);
    }

    public function createDevelopmentRequest(array $attributes): DevelopmentRequest
    {
        return $this->make(DevelopmentRequest::class, $attributes, $this->developments);
    }
}
