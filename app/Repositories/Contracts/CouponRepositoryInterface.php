<?php

namespace App\Repositories\Contracts;

use App\Models\Coupon;

interface CouponRepositoryInterface
{
    public function findByCode(string $code): ?Coupon;

    public function usageCount(Coupon $coupon): int;

    public function userUsageCount(Coupon $coupon, int $userId): int;
}
