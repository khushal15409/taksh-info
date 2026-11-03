<?php

namespace App\Services;

use App\Http\Resources\Wallet\BankDetailResource;
use App\Models\User;
use App\Repositories\Contracts\BankDetailRepositoryInterface;

class BankDetailService
{
    public function __construct(private readonly BankDetailRepositoryInterface $banks) {}

    /** @return list<array<string, mixed>> */
    public function list(User $user): array
    {
        return BankDetailResource::collection($this->banks->forUser($user->id))->resolve();
    }

    /** Always adds a new account (an existing one is never overwritten). */
    public function add(User $user, array $data): array
    {
        return BankDetailResource::make($this->banks->create([
            'user_id' => $user->id,
            'account_holder_name' => $data['account_holder_name'],
            'bank_name' => $data['bank_name'],
            'account_number' => $data['account_number'],
            'ifsc_code' => strtoupper($data['ifsc_code']),
            'branch' => $data['branch'] ?? null,
            'upi' => $data['upi'] ?? null,
        ]))->resolve();
    }
}
