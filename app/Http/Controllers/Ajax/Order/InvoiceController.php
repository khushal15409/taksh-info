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
    public function download(Request $request, string $orderNumber): Response
    {
        // an order that is not the customer's looks exactly like one that does not exist
        abort_unless($this->invoices->owns($request->user(), $orderNumber), 404);

        $part = in_array($request->query('part'), ['both', 'tax', 'label'], true) ? $request->query('part') : 'both';
        $pdf = $this->invoices->pdf($orderNumber, $part);

        abort_if($pdf === null, 503, 'The invoice is not available right now. Please try again shortly.');

        return response($pdf['body'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
