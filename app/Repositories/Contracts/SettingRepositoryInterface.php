<?php

namespace App\Repositories\Contracts;

use App\Models\Express30LogoSetting;

/** Read access to the shared admin settings (`business_settings` and the logo setting). */
interface SettingRepositoryInterface
{
    public function value(string $key): ?string;

    /** The active dashboard logo, if admin set one. */
    public function activeLogo(): ?Express30LogoSetting;
}
