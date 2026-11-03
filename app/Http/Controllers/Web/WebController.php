<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Base for the website's page controllers (Web\{Module}\...). A page controller only gathers what the page needs from the module's
 * services and hands it to a blade view in resources/views/{module}/ - the business rules stay in the services.
 *
 * Messages for the next page go through flash(): the layout shows them as a toast (see components/flash.blade.php).
 */
abstract class WebController extends Controller
{
    public const FLASH_KEY = 'toast';

    /** Render a module view, e.g. page('orders.index', [...]) -> resources/views/orders/index.blade.php. */
    protected function page(string $view, array $data = []): View
    {
        return view($view, $data);
    }

    /** A module view answered with 404 (a missing product, an ended sale): the page explains it instead of a bare error. */
    protected function notFound(string $view, array $data = []): Response
    {
        return response()->view($view, $data, 404);
    }

    /** Queue a toast for the next page. $type: success | error | info. */
    protected function flash(string $type, string $message): void
    {
        session()->flash(self::FLASH_KEY, ['type' => $type, 'message' => $message]);
    }

    /** Redirect to a named route with a toast ("Order cancelled", "Address saved", ...). */
    protected function redirectWith(string $route, string $type, string $message, array $params = []): RedirectResponse
    {
        $this->flash($type, $message);

        return redirect()->route($route, $params);
    }

    /** Go back to where the customer was, with a toast. */
    protected function backWith(string $type, string $message): RedirectResponse
    {
        $this->flash($type, $message);

        return back();
    }
}
