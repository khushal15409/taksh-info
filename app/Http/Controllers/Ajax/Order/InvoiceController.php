<?php

namespace App\Http\Controllers\Ajax\Order;

use App\Http\Controllers\Controller;
use App\Services\Orders\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    /** GET /orders/{order_number}/invoice?part=both|tax|label - the customer's own invoice as a PDF download */
    public function download(Request $request, string $orderNumber): Response|\Illuminate\Http\RedirectResponse
    {
        // an order that is not the customer's looks exactly like one that does not exist
        abort_unless($this->invoices->owns($request->user(), $orderNumber), 404);

        $part = in_array($request->query('part'), ['both', 'tax', 'label'], true) ? $request->query('part') : 'both';
        try {
            $pdf = $this->invoices->pdf((int) $request->user()->id, $orderNumber, $part);
        } catch (\RuntimeException $e) {
            // not a bare "503 Service Unavailable" page: back to where the customer was, with a message (and a code to quote to support)
            session()->flash(\App\Http\Controllers\Web\WebController::FLASH_KEY, [
                'type' => 'error',
                'message' => 'The invoice is not available right now. Please try again in a minute. (code: '.$e->getMessage().')',
            ]);

            return redirect()->to(url()->previous(route('tracking', ['id' => $orderNumber])));
        }

        return response($pdf['body'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
