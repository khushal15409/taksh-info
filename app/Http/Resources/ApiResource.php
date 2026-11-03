<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** Base for API resources: no {"data": ...} wrapper, because the response envelope already has one. */
abstract class ApiResource extends JsonResource
{
    public static $wrap = null;
}
