<?php

use App\Http\Controllers\Web\Account\ProfilePageController;
use Illuminate\Support\Facades\Route;

// Account hub: /profile?tab=profile|addresses|orders|returns. The page is open to guests (it shows a login prompt); changes need a login.
Route::get('/profile', [ProfilePageController::class, 'show'])->middleware('hidefilters:orders')->name('profile');
Route::post('/profile', \App\Http\Controllers\Web\FilterRedirectController::class)->middleware('hidefilters:orders')->name('profile.filter');   // the My Orders filter form

Route::middleware('auth')->group(function () {
    Route::put('/profile', [ProfilePageController::class, 'update'])->name('profile.update');
    Route::post('/profile/addresses/{id}/default', [ProfilePageController::class, 'setDefaultAddress'])->where('id', '([a-z0-9-]+-)?[0-9a-z]{11}|[0-9]+')->middleware('urlkey:address')->name('profile.addresses.default');
    Route::delete('/profile/addresses/{id}', [ProfilePageController::class, 'destroyAddress'])->where('id', '([a-z0-9-]+-)?[0-9a-z]{11}|[0-9]+')->middleware('urlkey:address')->name('profile.addresses.destroy');
    Route::post('/logout', [ProfilePageController::class, 'logout'])->name('logout');
});
