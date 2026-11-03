<?php

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/privacy-policy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/refund-policy', [PageController::class, 'refund'])->name('refund');

// Customer auth API (OTP login, session, profile)
require __DIR__.'/auth.php';
require __DIR__.'/home.php';
require __DIR__.'/catalog.php';
require __DIR__.'/cart.php';
require __DIR__.'/account.php';
require __DIR__.'/order.php';
require __DIR__.'/wallet.php';
require __DIR__.'/notification.php';
require __DIR__.'/service.php';
require __DIR__.'/support.php';

// Module pages (one file per module; each page has a Web\{Module}\ controller and a resources/views/{module}/ view)
require __DIR__.'/web/auth.php';
require __DIR__.'/web/account.php';
require __DIR__.'/web/notifications.php';
require __DIR__.'/web/catalog.php';
require __DIR__.'/web/cart.php';
require __DIR__.'/web/wishlist.php';
require __DIR__.'/web/checkout.php';
require __DIR__.'/web/orders.php';
require __DIR__.'/web/wallet.php';
require __DIR__.'/web/support.php';
require __DIR__.'/web/services.php';
