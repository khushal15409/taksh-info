<?php

namespace App\Http\Controllers\Ajax\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\DevelopmentRequestRequest;
use App\Http\Requests\Support\JoinRequestRequest;
use App\Http\Requests\Support\VendorRequestRequest;
use App\Services\SupportRequestService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Enquiry forms - same paths and payloads as the Taksh API. */
class SupportRequestController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly SupportRequestService $requests) {}

    /** POST /request-callback - login required, no input: the customer's own name and number are used */
    public function callback(Request $request): JsonResponse
    {
        return $this->success($this->requests->requestCallback($request->user()), 'api.callback_request_created');
    }

    /** POST /delivery-boy/join-request - public */
    public function join(JoinRequestRequest $request): JsonResponse
    {
        return $this->success($this->requests->joinAsDeliveryPartner($request->validated()), 'api.delivery_boy_join_request_created');
    }

    /** POST /vendor-request - public, a logged-in customer is attached */
    public function vendor(VendorRequestRequest $request): JsonResponse
    {
        return $this->success($this->requests->registerVendor($request->user(), $request->validated()), 'api.vendor_request_created');
    }

    /** POST /development-request - public, a logged-in customer is attached */
    public function development(DevelopmentRequestRequest $request): JsonResponse
    {
        return $this->success($this->requests->requestDevelopment($request->user(), $request->validated()), 'api.development_request_created');
    }
}
