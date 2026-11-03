<?php

namespace App\Http\Controllers\Web\Courier;

use App\Exceptions\ApiException;
use App\Http\Controllers\Web\WebController;
use App\Http\Requests\Web\Services\CourierBookingPageRequest;
use App\Services\Courier\CourierBookingService;
use App\Services\Courier\CourierCancellationService;
use App\Services\Courier\CourierQueryService;
use App\Services\Courier\CourierTrackingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;

/**
 * Courier booking. The form is on /services; the rate lookup before it and the payment after it are small calls from the page
 * (modules/courier.js, modules/service-pay.js - the JSON endpoints under /service/courier). Booking creates the booking and its payment order on the
 * server; the booking page (/services/courier/{id}) shows its tracking, invoice, cancel and - while unpaid - the pay button.
 */
class CourierPageController extends WebController
{
    public function __construct(
        private readonly CourierBookingService $booking,
        private readonly CourierQueryService $query,
        private readonly CourierCancellationService $cancellation,
        private readonly CourierTrackingService $tracking,
    ) {}

    /** POST /services/courier (multipart) */
    public function store(CourierBookingPageRequest $request): RedirectResponse
    {
        try {
            $b = $this->booking->create($request->user(), $request->validated(), $request->file('item_photo'));
        } catch (ApiException $e) {
            $this->flash('error', $this->messageOf($e));

            return redirect()->to(route('services').'#courier')->withInput($request->except('item_photo'));
        }

        return redirect()->route('services.courier.show', ['id' => $b['booking_id'], 'pay' => 1]);
    }

    /** GET /services/courier/{id} */
    public function show(Request $request, int $id): View|Response
    {
        try {
            $booking = $this->query->detail($request->user(), $id);
        } catch (ApiException) {
            return $this->notFound('courier.show', ['booking' => null]);
        }

        return $this->page('courier.show', ['booking' => $booking, 'autostart' => $request->boolean('pay')]);
    }

    /** POST /services/courier/{id}/cancel {reason?} */
    public function cancel(Request $request, int $id): RedirectResponse
    {
        $reason = trim((string) $request->input('reason')) ?: null;

        try {
            $state = $this->cancellation->cancel($request->user()->id, $id, $reason ? mb_substr($reason, 0, 500) : null);
        } catch (ApiException $e) {
            return $this->redirectWith('services.courier.show', 'error', $this->messageOf($e), ['id' => $id]);
        }

        return $this->redirectWith('services.courier.show', 'success', match ($state['refund_status']) {
            null => 'Booking cancelled.',
            'success' => 'Booking cancelled and refund processed.',
            'failed' => 'Booking cancelled. The refund could not be completed yet and will be retried.',
            default => 'Booking cancelled. Your refund is being processed.',
        }, ['id' => $id]);
    }

    /** POST /services/courier/{id}/track - pulls the newest tracking from the courier and shows it */
    public function track(Request $request, int $id): RedirectResponse
    {
        try {
            $this->tracking->refresh($request->user(), $id);
        } catch (ApiException $e) {
            return $this->redirectWith('services.courier.show', 'error', $this->messageOf($e), ['id' => $id]);
        }

        return $this->redirectWith('services.courier.show', 'success', 'Tracking updated', ['id' => $id]);
    }

    private function messageOf(ApiException $e): string
    {
        return Lang::has($e->getMessage()) ? __($e->getMessage()) : $e->getMessage();
    }
}
