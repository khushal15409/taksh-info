<?php

namespace App\Http\Controllers\Ajax\Service;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\ServiceInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

/** Invoices for the customer's own paid service / courier bookings. */
class ServiceInvoiceController extends Controller
{
    public function __construct(private readonly ServiceInvoiceService $invoices) {}

    /** GET /service/book/{id}/invoice ({id} is the booking id or its booking number) */
    public function marketplace(Request $request, string $id): View
    {
        return view('invoices.service', $this->invoices->forBooking($request->user(), $id));
    }

    /** GET /service/courier/bookings/{id}/invoice */
    public function courier(Request $request, int $id): View
    {
        return view('invoices.service', $this->invoices->forCourier($request->user(), $id));
    }
}
