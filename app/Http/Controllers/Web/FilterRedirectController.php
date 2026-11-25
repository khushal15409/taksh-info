<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

/**
 * The target of a search / filter form POST. The `hidefilters` middleware on the route answers every POST with a redirect to the page's
 * GET URL, so this is only reached when that protection is switched off - then the form simply has no POST page.
 */
class FilterRedirectController extends Controller
{
    public function __invoke()
    {
        abort(404);
    }
}
