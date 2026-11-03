<?php

namespace App\Exceptions;

class ProductNotFoundException extends ApiException
{
    protected int $status = 404;

    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? 'api.product_not_found');
    }
}
