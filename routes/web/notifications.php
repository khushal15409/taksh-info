<?php

use App\Http\Controllers\Web\Notifications\NotificationPageController;
use Illuminate\Support\Facades\Route;

Route::get('/notifications', [NotificationPageController::class, 'index'])->middleware('hidefilters:notifications')->name('notifications');

Route::middleware('auth')->group(function () {
    Route::get('/notifications/{id}', [NotificationPageController::class, 'show'])->where('id', '([a-z0-9-]+-)?[0-9a-z]{11}|[0-9]+')->middleware('urlkey:notification')->name('notifications.show');
    Route::post('/notifications/mark-all-read', [NotificationPageController::class, 'markAllRead'])->name('notifications.mark-all-read');
});
