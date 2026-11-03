<?php

namespace App\Services;

use App\Http\Resources\Home\RecentViewResource;
use App\Repositories\Contracts\RecentViewRepositoryInterface;

class RecentViewService
{
    public function __construct(private readonly RecentViewRepositoryInterface $views) {}

    /** Recently viewed products of a customer; rows whose product was deleted are dropped. */
    public function forUser(int $userId, ?string $deliveryType = null): array
    {
        $rows = $this->views->forUser($userId, $deliveryType)->filter(fn ($view) => $view->product)->values();

        return RecentViewResource::collection($rows)->resolve();
    }
}
