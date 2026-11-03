<?php

namespace App\Http\Controllers\Ajax\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\StoreBankDetailRequest;
use App\Services\BankDetailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Same plain {status, message, data} answers as the Taksh API. */
class BankDetailController extends Controller
{
    public function __construct(private readonly BankDetailService $banks) {}

    /** GET /bank-detail */
    public function index(Request $request): JsonResponse
    {
        $rows = $this->banks->list($request->user());

        return response()->json(['status' => true, 'message' => $rows ? 'Bank details fetched successfully' : 'No bank details found', 'data' => $rows]);
    }

    /** POST /bank-detail */
    public function store(StoreBankDetailRequest $request): JsonResponse
    {
        return response()->json(['status' => true, 'message' => 'Bank details saved successfully', 'data' => $this->banks->add($request->user(), $request->validated())], 201);
    }
}
