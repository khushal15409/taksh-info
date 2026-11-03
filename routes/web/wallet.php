<?php

use App\Http\Controllers\Web\Wallet\WalletPageController;
use Illuminate\Support\Facades\Route;

Route::get('/wallet', [WalletPageController::class, 'index'])->name('wallet');

Route::middleware('auth')->group(function () {
    Route::post('/wallet/bank-accounts', [WalletPageController::class, 'storeBank'])->middleware('throttle:10,1')->name('wallet.bank.store');
    Route::post('/wallet/withdrawals', [WalletPageController::class, 'storeWithdrawal'])->middleware('throttle:10,1')->name('wallet.withdrawal.store');
});
