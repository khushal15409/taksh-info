<?php

namespace App\Http\Controllers\Ajax\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\PerPageRequest;
use App\Http\Requests\Wallet\WithdrawalRequest;
use App\Services\WalletService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly WalletService $wallet) {}

    /** GET /wallet/summary (the API's GET /wallet; on the website /wallet is the page) */
    public function show(Request $request): JsonResponse
    {
        return $this->success($this->wallet->summary($request->user()), 'api.wallet_summary_fetched');
    }

    /** GET /wallet/transactions?per_page= */
    public function transactions(PerPageRequest $request): JsonResponse
    {
        return $this->success($this->wallet->transactions($request->user(), $request->perPage()), 'api.wallet_transactions_retrieved');
    }

    /** GET /wallet/withdrawal-requests?per_page= */
    public function withdrawalRequests(PerPageRequest $request): JsonResponse
    {
        return $this->success($this->wallet->withdrawals($request->user(), $request->perPage()), 'api.wallet_withdrawal_requests_fetched');
    }

    /** POST /wallet/withdrawal-requests {points, bank_detail_id} */
    public function storeWithdrawalRequest(WithdrawalRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->success($this->wallet->requestWithdrawal($request->user(), (float) $v['points'], (int) $v['bank_detail_id']), 'api.wallet_withdrawal_request_created', 201);
    }

    /** GET /return/refund/wallet-transactions - the ledger lines that came from refunds */
    public function refundTransactions(Request $request): JsonResponse
    {
        return $this->success($this->wallet->refundTransactions($request->user()), 'api.wallet_transactions_retrieved');
    }
}
