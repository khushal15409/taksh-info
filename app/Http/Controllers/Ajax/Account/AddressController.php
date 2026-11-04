<?php

namespace App\Http\Controllers\Ajax\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AddressIdRequest;
use App\Http\Requests\Account\StoreAddressRequest;
use App\Http\Requests\Account\UpdateAddressRequest;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Same endpoints and the same plain {status, message, data} answers as the Taksh API. */
class AddressController extends Controller
{
    public function __construct(private readonly AddressService $addresses) {}

    /** GET /address/addresses (and /address/user/addresses) */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['status' => true, 'message' => 'Addresses fetched successfully', 'data' => $this->addresses->list($request->user()->id)]);
    }

    /** POST /address/add (and /address/user/addresses) */
    public function store(StoreAddressRequest $request): JsonResponse
    {
        $id = $this->addresses->store($request->user()->id, $request->validated());

        return response()->json(['status' => true, 'message' => 'Address added successfully', 'data' => ['address_id' => $id]], 201);
    }

    /** PUT /address/{id} */
    public function update(UpdateAddressRequest $request, int $id): JsonResponse
    {
        return response()->json(['status' => true, 'message' => 'Address updated successfully', 'data' => $this->addresses->update($request->user()->id, $id, $request->validated())]);
    }

    /** POST /address/user/addresses/set-default {address_id} */
    public function setDefault(AddressIdRequest $request): JsonResponse
    {
        $this->addresses->setDefault($request->user()->id, (int) $request->validated('address_id'));

        // the chosen saved address is the delivery place again: forget a pincode typed or picked earlier
        return response()->json(['status' => true, 'message' => 'Default address updated successfully', 'data' => null])
            ->withCookie(\Illuminate\Support\Facades\Cookie::forget(\App\Services\Quick\QuickLocation::COOKIE));
    }

    /** POST /address/user/addresses/delete {address_id} */
    public function destroy(AddressIdRequest $request): JsonResponse
    {
        $this->addresses->delete($request->user()->id, (int) $request->validated('address_id'));

        return response()->json(['status' => true, 'message' => 'Address deleted successfully', 'data' => null]);
    }
}
