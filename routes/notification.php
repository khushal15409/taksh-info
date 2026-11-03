<?php

use App\Http\Controllers\Ajax\Notification\NotificationController;
use Illuminate\Support\Facades\Route;

// The customer's notification inbox - same payloads as the Taksh API (the list lives at /notifications/items
// because /notifications is the page).
Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('items', [NotificationController::class, 'index'])->name('index');
    Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread');
    Route::post('read-all', [NotificationController::class, 'markAllRead'])->name('read_all');
    Route::post('device-token', [NotificationController::class, 'storeDeviceToken'])->middleware('throttle:30,1')->name('device.store');
    Route::delete('device-token', [NotificationController::class, 'destroyDeviceToken'])->name('device.destroy');
    Route::post('{id}/read', [NotificationController::class, 'markRead'])->whereNumber('id')->name('read');
});
