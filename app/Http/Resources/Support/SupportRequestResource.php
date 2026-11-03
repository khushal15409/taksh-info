<?php

namespace App\Http\Resources\Support;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/** What the API answers after saving an enquiry: its id, status and time, plus the few fields that kind of request echoes back. */
class SupportRequestResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $extra = collect($this->additional['fields'] ?? [])->mapWithKeys(fn ($f) => [$f => $this->resource->{$f}])->all();

        return ['id' => $this->id] + $extra + ['status' => $this->status, 'created_at' => $this->created_at?->format('Y-m-d H:i:s')];
    }
}
