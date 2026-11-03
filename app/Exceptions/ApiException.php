<?php

namespace App\Exceptions;

use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/** A failure that is shown to the caller as the standard {success:false} envelope. */
abstract class ApiException extends RuntimeException
{
    use ApiResponseTrait;

    protected int $status = 500;

    public function render(): JsonResponse
    {
        return $this->error($this->getMessage(), $this->status);
    }
}
