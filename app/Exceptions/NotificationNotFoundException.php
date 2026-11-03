<?php

namespace App\Exceptions;

class NotificationNotFoundException extends ApiException
{
    protected int $status = 404;

    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? 'api.notification_not_found');
    }
}
