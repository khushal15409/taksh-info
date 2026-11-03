<?php

namespace App\Repositories\Contracts;

use App\Models\BankDetail;
use Illuminate\Support\Collection;

interface BankDetailRepositoryInterface
{
    /** The customer's saved accounts, newest first. */
    public function forUser(int $userId): Collection;

    public function create(array $attributes): BankDetail;
}
