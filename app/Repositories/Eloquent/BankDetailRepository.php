<?php

namespace App\Repositories\Eloquent;

use App\Models\BankDetail;
use App\Repositories\Contracts\BankDetailRepositoryInterface;
use Illuminate\Support\Collection;

class BankDetailRepository implements BankDetailRepositoryInterface
{
    public function forUser(int $userId): Collection
    {
        return BankDetail::where('user_id', $userId)->orderByDesc('id')->get();
    }

    public function create(array $attributes): BankDetail
    {
        return BankDetail::create($attributes);
    }
}
