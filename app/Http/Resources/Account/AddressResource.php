<?php

namespace App\Http\Resources\Account;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class AddressResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'mobile' => $this->mobile,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'city' => $this->city?->name,
            'state' => $this->state?->name,
            'area' => $this->area?->name,
            'pincode' => $this->pincode,
            'landmark' => $this->landmark,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'address_type' => $this->type === 'work' ? 'office' : $this->type,   // stored as "work", shown as "office"
            'is_default' => (bool) $this->is_default,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
