<?php

namespace App\Exceptions;

/** The customer has not had this product delivered, so cannot rate it. */
class ReviewNotAllowedException extends ApiException
{
    protected int $status = 403;

    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? 'api.review.not_delivered');
    }
}
