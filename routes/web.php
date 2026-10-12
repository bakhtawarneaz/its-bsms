<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\ContactController;
use App\Http\Controllers\DashboardController;

Route::redirect('/', '/login');

// Guest-only routes
Route::middleware('portal.guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/contact', [ContactController::class, 'edit'])->name('contact.edit');
    Route::post('/contact', [ContactController::class, 'update'])->name('contact.update');
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
});