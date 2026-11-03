<?php

namespace App\Enums;

enum ReturnResolution: string
{
    case REFUND = 'refund';
    case REPLACEMENT = 'replacement';
    case WALLET = 'wallet';

    public function label(): string
    {
        return match ($this) {
            self::REFUND => 'Refund',
            self::REPLACEMENT => 'Replacement',
            self::WALLET => 'Wallet Credit',
        };
    }
}
