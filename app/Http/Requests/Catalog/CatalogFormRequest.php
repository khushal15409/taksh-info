<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\ApiFormRequest;

/** Catalogue endpoints report malformed input as the API does: HTTP 400 with the validation errors. */
abstract class CatalogFormRequest extends ApiFormRequest
{
    protected int $failureStatus = 400;
}
