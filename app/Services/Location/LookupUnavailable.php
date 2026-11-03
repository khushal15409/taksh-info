<?php

namespace App\Services\Location;

use App\Exceptions\ApiException;

class LookupUnavailable extends ApiException
{
    protected int $status = 503;

    public function __construct()
    {
        parent::__construct('Place search is not available right now. Please type your address instead.');
    }
}
