<?php

namespace App\Repositories\Eloquent;

use App\Models\Coupon;
use App\Repositories\Contracts\CouponRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CouponRepository implements CouponRepositoryInterface
{
    public function findByCode(string $code): ?Coupon
    {
        return Coupon::where('code', $code)->first();
    }

    public function usageCount(Coupon $coupon): int
    {
        return DB::table('coupon_usages')->where('coupon_id', $coupon->id)->count();
    }

    public function userUsageCount(Coupon $coupon, int $userId): int
    {
        return DB::table('coupon_usages')->where('coupon_id', $coupon->id)->where('user_id', $userId)->count();
    }
}
