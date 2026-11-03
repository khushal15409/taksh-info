<?php

namespace App\Repositories\Eloquent;

use App\Models\BusinessSetting;
use App\Models\Express30LogoSetting;
use App\Repositories\Contracts\SettingRepositoryInterface;

class SettingRepository implements SettingRepositoryInterface
{
    /** @var array<string, ?string> per-request memo, so a price band is not looked up once per product */
    private array $memo = [];

    public function value(string $key): ?string
    {
        return array_key_exists($key, $this->memo)
            ? $this->memo[$key]
            : $this->memo[$key] = BusinessSetting::where('key', $key)->value('value');
    }

    public function activeLogo(): ?Express30LogoSetting
    {
        return Express30LogoSetting::where('is_active', true)->first();
    }
}
