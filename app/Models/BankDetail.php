<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A customer's bank account (or UPI id) for wallet withdrawals. */
class BankDetail extends Model
{
    protected $fillable = ['user_id', 'account_holder_name', 'bank_name', 'account_number', 'ifsc_code', 'branch', 'upi'];

    /** "123456789012" -> "12********12". */
    public function maskedAccountNumber(): string
    {
        $n = (string) ($this->account_number ?? '');

        return strlen($n) > 4 ? substr($n, 0, 2).str_repeat('*', strlen($n) - 4).substr($n, -2) : $n;
    }
}
