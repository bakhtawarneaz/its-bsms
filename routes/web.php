<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MaskingController;
use App\Http\Controllers\ResellerController;

Route::redirect('/', '/login');

// Guest-only routes
Route::middleware('portal.guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/otp', [OtpController::class, 'recipients'])->name('otp.recipients');
    Route::post('/otp/send', [OtpController::class, 'send'])->middleware('throttle:5,1')->name('otp.send');
    Route::get('/otp/verify', [OtpController::class, 'show'])->name('otp.verify');
    Route::post('/otp/verify', [OtpController::class, 'verify'])->name('otp.verify.store');
    Route::post('/otp/resend', [OtpController::class, 'resend'])->middleware('throttle:5,1')->name('otp.resend');
});

// Authenticated routes
Route::middleware('portal.auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:5,1')->name('profile.password');

    // Users (superadmin only for now)
    Route::middleware('portal.role:superadmin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        
        Route::get('/maskings', [MaskingController::class, 'index'])->name('maskings.index');
        Route::get('/maskings/create', [MaskingController::class, 'create'])->name('maskings.create');
        Route::post('/maskings', [MaskingController::class, 'store'])->name('maskings.store');
        Route::post('/maskings/status', [MaskingController::class, 'status'])->name('maskings.status');
        Route::get('/maskings/requests', [MaskingController::class, 'requests'])->name('maskings.requests');
        Route::get('/maskings/{masking}/edit', [MaskingController::class, 'edit'])->whereNumber('masking')->name('maskings.edit');
        Route::put('/maskings/{masking}', [MaskingController::class, 'update'])->whereNumber('masking')->name('maskings.update');

        Route::get('/resellers', [ResellerController::class, 'index'])->name('resellers.index');
        Route::get('/resellers/create', [ResellerController::class, 'create'])->name('resellers.create');
        Route::post('/resellers', [ResellerController::class, 'store'])->name('resellers.store');
        Route::get('/resellers/{reseller}/edit', [ResellerController::class, 'edit'])->whereNumber('reseller')->name('resellers.edit');
        Route::put('/resellers/{reseller}', [ResellerController::class, 'update'])->whereNumber('reseller')->name('resellers.update');

        Route::whereNumber('user')->group(function () {
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::patch('/users/{user}/status', [UserController::class, 'status'])->name('users.status');
            Route::patch('/users/{user}/unblock', [UserController::class, 'unblock'])->name('users.unblock');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
            Route::post('/users/{user}/balance', [UserController::class, 'balance'])->name('users.balance');
        });
    });
});