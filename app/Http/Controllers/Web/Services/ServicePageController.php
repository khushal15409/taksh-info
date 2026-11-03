<?php

namespace App\Http\Controllers\Web\Services;

use App\Exceptions\ApiException;
use App\Http\Controllers\Web\WebController;
use App\Http\Requests\Web\Services\BookServicePageRequest;
use App\Http\Resources\Home\BannerResource;
use App\Models\ServiceMarketplaceBooking;
use App\Repositories\Contracts\BannerRepositoryInterface;
use App\Services\AddressService;
use App\Services\Courier\CourierQueryService;
use App\Services\Marketplace\ServiceBookingCancellationService;
use App\Services\Marketplace\ServiceBookingQueryService;
use App\Services\Marketplace\ServiceBookingService;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Home services: the catalogue and the customer's bookings (/services), the booking form (/services/{id}/book), and one booking
 * (/services/bookings/{id}) with its timeline, invoice, cancel and - while unpaid - the pay button (only the Razorpay window is JS,
 * modules/service-pay.js). Booking, availability, payment verification and refunds are the same services the JSON endpoints use.
 */
class ServicePageController extends WebController
{
    private const PER_PAGE = 10;

    public function __construct(
        private readonly ServiceBookingQueryService $query,
        private readonly ServiceBookingService $booking,
        private readonly ServiceBookingCancellationService $cancellation,
        private readonly CourierQueryService $courier,
        private readonly BannerRepositoryInterface $banners,
        private readonly AddressService $addresses,
        private readonly RazorpayGateway $gateway,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $category = array_key_exists($request->query('category'), ServiceMarketplaceBooking::CATEGORIES) ? $request->query('category') : null;

        $history = $user ? $this->query->history($user, $category, self::PER_PAGE) : null;

        return $this->page('services.index', [
            'banners' => $this->serviceBanners(),
            'services' => $this->query->catalog(),
            'category' => $category,
            'bookings' => $history ? (new LengthAwarePaginator($history['bookings'], $history['pagination']['total'], $history['pagination']['per_page'], $history['pagination']['current_page'], ['path' => route('services')]))->appends(array_filter(['category' => $category])) : null,
            'courierBookings' => $user ? $this->courier->history($user, 5)['bookings'] : [],
            'courierBag' => $request->session()->get('errors')?->getBag('courier') ?? new \Illuminate\Support\MessageBag,
            'faqs' => config('faq.items'),
            'defaults' => $user ? ['name' => $user->name, 'mobile' => $user->mobile] : [],
        ]);
    }

    /** GET /services/{id}/book */
    public function create(Request $request, int $id): View|RedirectResponse|Response
    {
        $service = collect($this->query->catalog())->firstWhere('id', $id);
        if (! $service) {
            return $this->notFound('services.book', ['service' => null, 'defaults' => []]);
        }

        $user = $request->user();
        $a = $user ? ($this->addresses->list($user->id)['default_address'] ?? null) : null;

        return $this->page('services.book', [
            'service' => $service,
            'defaults' => ['name' => $a['name'] ?? $user?->name, 'mobile' => $a['mobile'] ?? $user?->mobile, 'address_line_1' => $a['address_line_1'] ?? '', 'pincode' => $a['pincode'] ?? '', 'city' => $a['city'] ?? ''],
            'key' => (string) Str::uuid(),
        ]);
    }

    /** POST /services/{id}/book - checks availability, creates the booking and its Razorpay order, then goes to the booking to pay */
    public function store(BookServicePageRequest $request, int $id): RedirectResponse
    {
        $v = $request->validated();

        try {
            $result = $this->booking->book(
                $request->user(), $id, null,
                collect($v)->only(['name', 'mobile', 'address_line_1', 'landmark', 'pincode', 'city'])->filter(fn ($x) => $x !== null && $x !== '')->all(),
                $v['customer_notes'] ?? null, isset($v['scheduled_for']) ? new \DateTimeImmutable($v['scheduled_for']) : null, $v['idempotency_key'],
            );
        } catch (ApiException $e) {
            $this->flash('error', $this->messageOf($e));

            return redirect()->route('services.book', ['id' => $id])->withInput();
        }

        return redirect()->route('services.bookings.show', ['id' => $result['booking']->id, 'pay' => 1]);
    }

    /** GET /services/bookings/{id} (the booking id or its number) */
    public function show(Request $request, string $id): View|Response
    {
        try {
            $booking = $this->query->detail($request->user(), $id);
        } catch (ApiException) {
            return $this->notFound('services.show', ['booking' => null]);
        }

        return $this->page('services.show', [
            'booking' => $booking, 'key' => $this->gateway->keyId(), 'autostart' => $request->boolean('pay') && $booking['pending_payment'],
        ]);
    }

    /** POST /services/bookings/{id}/pay - a new Razorpay order for a booking whose payment failed or was never finished */
    public function repay(Request $request, string $id): RedirectResponse
    {
        try {
            $result = $this->booking->retryPayment($request->user(), $id);
        } catch (ApiException $e) {
            return $this->redirectWith('services.bookings.show', 'error', $this->messageOf($e), ['id' => $id]);
        }

        return redirect()->route('services.bookings.show', ['id' => $result['booking']->id, 'pay' => 1]);
    }

    /** POST /services/bookings/{id}/cancel {reason?} */
    public function cancel(Request $request, int $id): RedirectResponse
    {
        $reason = trim((string) $request->input('reason')) ?: null;

        try {
            $r = $this->cancellation->cancel($request->user()->id, $id, $reason ? mb_substr($reason, 0, 500) : null);
        } catch (ApiException $e) {
            return $this->redirectWith('services.bookings.show', 'error', $this->messageOf($e), ['id' => $id]);
        }

        $refund = (float) ($r['refund']->requested_amount ?? 0);

        return $this->redirectWith('services.bookings.show', 'success', $refund > 0
            ? 'Booking cancelled. Refund of '.\App\Helpers\Rupee::format($refund).' is '.($r['booking']->refund_status === 'success' ? 'done' : 'on its way').'.'
            : 'Booking cancelled.', ['id' => $id]);
    }

    private function serviceBanners(): array
    {
        try {
            return BannerResource::collection($this->banners->active(['type' => 'service']))->resolve();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    private function messageOf(ApiException $e): string
    {
        return Lang::has($e->getMessage()) ? __($e->getMessage()) : $e->getMessage();
    }
}
