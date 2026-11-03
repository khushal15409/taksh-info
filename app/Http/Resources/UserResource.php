<?php

namespace App\Http\Resources;

use App\Helpers\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The user as /auth/verify-otp returns it, plus the readable fields the website header needs. */
class UserResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mobile' => $this->mobile,
            'name' => $this->name,
            'is_verified' => (bool) $this->is_verified,
            'first_name' => $this->f_name ?? '',
            'last_name' => $this->l_name ?? '',
            'email' => $this->email ?? '',
            'mobile_display' => Phone::display($this->mobile),
        ];
    }
}
