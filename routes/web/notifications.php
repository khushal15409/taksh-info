<?php

use App\Http\Controllers\Web\Notifications\NotificationPageController;
use Illuminate\Support\Facades\Route;

Route::get('/notifications', [NotificationPageController::class, 'index'])->name('notifications');

Route::middleware('auth')->group(function () {
    Route::get('/notifications/{id}', [NotificationPageController::class, 'show'])->whereNumber('id')->name('notifications.show');
    Route::post('/notifications/mark-all-read', [NotificationPageController::class, 'markAllRead'])->name('notifications.mark-all-read');
});
