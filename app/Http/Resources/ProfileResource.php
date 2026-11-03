<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The profile exactly as /auth/profile and /auth/update-profile return it. */
class ProfileResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'first_name' => $this->f_name ?? '',
            'last_name' => $this->l_name ?? '',
            'email' => $this->email ?? '',
            'mobile' => $this->mobile ?? '',
        ];
    }
}
