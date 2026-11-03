<?php

namespace App\Exceptions;

class SupportRequestException extends ApiException
{
    protected int $status = 400;

    /** The customer already has a callback waiting in the admin's queue. */
    public static function callbackAlreadyOpen(): self
    {
        return new self('api.callback_request_already_open');
    }
}
