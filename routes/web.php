<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// Temporary dashboard until the real module is built
Route::get('/dashboard', function () {
    $user = session('auth_user');

    if (! $user) {
        return redirect()->route('login');
    }

    return '<h2>Welcome, ' . e($user['fullname'] ?? $user['username']) . ' (' . e($user['user_type']) . ')</h2>'
        . '<form method="POST" action="' . route('logout') . '">' . csrf_field()
        . '<button type="submit">Logout</button></form>';
})->name('dashboard');