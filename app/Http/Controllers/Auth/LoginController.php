<?php

namespace App\Http\Controllers\Auth;

use App\Enums\LoginStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Enums\UserType;
use App\Services\ActivityLogger;

class LoginController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Show the login form.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle a login attempt.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->session()->forget(['otp_user', 'otp', 'contact_user']);

        $result = $this->authService->attempt(
            trim($request->validated('login')),
            $request->validated('password'),
            $request->getHost(),
        );

        if ($result['status'] !== LoginStatus::Ok) {
            return back()
                ->withInput($request->only('login'))
                ->withErrors(['login' => $result['status']->message()]);
        }

        $user = (array) $result['user'];
        unset($user['login_status']);

        // Prevent session fixation by issuing a new session id on login
        $request->session()->regenerate();

        if (UserType::fromDatabase($user['user_type'])->requiresOtp()) {
            $request->session()->put('otp_user', $user);

            return redirect()->route('otp.recipients');
        }

        $request->session()->put('auth_user', $user);
        $this->activity->log('User Log in', (int) $user['id']);

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Welcome back, ' . ($user['fullname'] ?: $user['username']) . '.');
    }

    /**
     * Log the user out and destroy the session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->activity->log('Logout successful');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been logged out.');
    }
}