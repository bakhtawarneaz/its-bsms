<?php

namespace App\Http\Controllers\Auth;

use App\Enums\LoginStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
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
        $request->session()->put('auth_user', $user);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Log the user out and destroy the session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been logged out.');
    }
}