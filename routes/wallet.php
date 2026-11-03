<?php

use App\Http\Controllers\Ajax\Wallet\BankDetailController;
use App\Http\Controllers\Ajax\Wallet\WalletController;
use Illuminate\Support\Facades\Route;

// The customer's wallet and bank accounts - same payloads as the Taksh API.
Route::middleware('auth')->group(function () {
    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::get('summary', [WalletController::class, 'show'])->name('summary');
        Route::get('transactions', [WalletController::class, 'transactions'])->name('transactions');
        Route::get('withdrawal-requests', [WalletController::class, 'withdrawalRequests'])->name('withdrawals');
        Route::post('withdrawal-requests', [WalletController::class, 'storeWithdrawalRequest'])->middleware('throttle:10,1')->name('withdraw');
    });

    Route::get('return/refund/wallet-transactions', [WalletController::class, 'refundTransactions'])->name('wallet.refunds');

    Route::prefix('bank-detail')->name('bank.')->group(function () {
        Route::get('/', [BankDetailController::class, 'index'])->name('index');
        Route::post('/', [BankDetailController::class, 'store'])->middleware('throttle:10,1')->name('store');
    });
});
