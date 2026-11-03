<?php

namespace App\Http\Controllers\Web\Wallet;

use App\Exceptions\ApiException;
use App\Http\Controllers\Web\WebController;
use App\Http\Requests\Web\Wallet\StoreBankPageRequest;
use App\Http\Requests\Web\Wallet\WithdrawalPageRequest;
use App\Services\BankDetailService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Lang;

/** The customer's wallet: points balance and what is withdrawable, bank accounts, withdrawal requests and the ledger (?page=). */
class WalletPageController extends WebController
{
    private const LEDGER_PER_PAGE = 15;

    public function __construct(
        private readonly WalletService $wallet,
        private readonly BankDetailService $banks,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user) {
            return $this->page('wallet.index', ['summary' => null]);
        }

        $ledger = $this->wallet->transactions($user, self::LEDGER_PER_PAGE);
        $p = $ledger['pagination'];

        return $this->page('wallet.index', [
            'summary' => $this->wallet->summary($user),
            'banks' => $this->banks->list($user),
            'withdrawals' => $this->wallet->withdrawals($user, 10)['items'],
            'ledger' => new LengthAwarePaginator($ledger['items'], $p['total'], $p['per_page'], $p['current_page'], ['path' => route('wallet')]),
        ]);
    }

    /** POST /wallet/bank-accounts */
    public function storeBank(StoreBankPageRequest $request): RedirectResponse
    {
        $this->banks->add($request->user(), $request->validated());

        return $this->redirectWith('wallet', 'success', '🏦 Bank account added');
    }

    /** POST /wallet/withdrawals {points, bank_detail_id} */
    public function storeWithdrawal(WithdrawalPageRequest $request): RedirectResponse
    {
        $v = $request->validated();

        try {
            $this->wallet->requestWithdrawal($request->user(), (float) $v['points'], (int) $v['bank_detail_id']);
        } catch (ApiException $e) {
            return $this->redirectWith('wallet', 'error', Lang::has($e->getMessage()) ? __($e->getMessage()) : $e->getMessage());
        }

        return $this->redirectWith('wallet', 'success', '💸 Withdrawal request submitted');
    }
}
