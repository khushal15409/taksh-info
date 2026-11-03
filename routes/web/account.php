<?php

use App\Http\Controllers\Web\Account\ProfilePageController;
use Illuminate\Support\Facades\Route;

// Account hub: /profile?tab=profile|addresses|orders|returns. The page is open to guests (it shows a login prompt); changes need a login.
Route::get('/profile', [ProfilePageController::class, 'show'])->name('profile');

Route::middleware('auth')->group(function () {
    Route::put('/profile', [ProfilePageController::class, 'update'])->name('profile.update');
    Route::post('/profile/addresses/{id}/default', [ProfilePageController::class, 'setDefaultAddress'])->whereNumber('id')->name('profile.addresses.default');
    Route::delete('/profile/addresses/{id}', [ProfilePageController::class, 'destroyAddress'])->whereNumber('id')->name('profile.addresses.destroy');
    Route::post('/logout', [ProfilePageController::class, 'logout'])->name('logout');
});
