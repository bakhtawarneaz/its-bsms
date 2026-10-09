<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// Guest-only routes
Route::middleware('portal.guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/otp', [OtpController::class, 'recipients'])->name('otp.recipients');
    Route::post('/otp/send', [OtpController::class, 'send'])->middleware('throttle:5,1')->name('otp.send');
    Route::get('/otp/verify', [OtpController::class, 'show'])->name('otp.verify');
    Route::post('/otp/verify', [OtpController::class, 'verify'])->name('otp.verify.store');
});

// Authenticated routes
Route::middleware('portal.auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Temporary dashboard until the real module is built
    Route::get('/dashboard', function () {
        $user = session('auth_user');

        return '<h2>Welcome, ' . e($user['fullname'] ?? $user['username']) . ' (' . e($user['user_type']) . ')</h2>'
            . '<form method="POST" action="' . route('logout') . '">' . csrf_field()
            . '<button type="submit">Logout</button></form>';
    })->name('dashboard');
});