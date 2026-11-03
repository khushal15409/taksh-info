<?php

use App\Http\Controllers\Web\Auth\LoginPageController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginPageController::class, 'show'])->name('login');
