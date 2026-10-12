<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OtpVerification;
use App\Http\Controllers\Controller;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OtpController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
    ) {}

    /**
     * Let the user choose which mobile number and email should receive the OTP.
     */
    public function recipients(Request $request): View|RedirectResponse
    {
        $user = $request->session()->get('otp_user');

        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.otp-recipients', [
            'mobiles' => array_map($this->maskMobile(...), $this->splitList($user['user_mobile'] ?? '')),
            'emails'  => array_map($this->maskEmail(...), $this->splitList($user['email'] ?? '')),
        ]);
    }

    /**
     * Generate and send the OTP to the selected recipients.
     */
    public function send(Request $request): RedirectResponse
    {
        $user = $request->session()->get('otp_user');

        if (! $user) {
            return redirect()->route('login');
        }

        $mobiles = $this->splitList($user['user_mobile'] ?? '');
        $emails  = $this->splitList($user['email'] ?? '');

        // Recipients are submitted as list indexes so real numbers/emails never reach the browser
        $data = $request->validate([
            'mobile' => ['nullable', 'integer', 'in:' . implode(',', array_keys($mobiles))],
            'email'  => ['nullable', 'integer', 'in:' . implode(',', array_keys($emails))],
        ]);

        $mobile = isset($data['mobile']) ? $mobiles[$data['mobile']] : null;
        $email  = isset($data['email']) ? $emails[$data['email']] : null;

        if (! $mobile && ! $email) {
            return back()->withErrors(['mobile' => 'Please select at least one OTP recipient.']);
        }

        if (! $this->otpService->issue($mobile, $email)) {
            return redirect()
                ->route($this->otpService->hasPending() ? 'otp.verify' : 'otp.recipients')
                ->with('warning', $this->cooldownMessage());
        }

        return redirect()->route('otp.verify')->with('status', 'A code has been sent to the selected recipients.');
    }

    /**
     * Show the OTP entry form.
     */
    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('otp_user')) {
            return redirect()->route('login');
        }

        if (! $this->otpService->hasPending()) {
            return redirect()->route('otp.recipients');
        }

         return view('auth.otp-verify', [
            'expiresAt' => $this->otpService->expiresAt(),
            'resendAt'  => $this->otpService->resendAvailableAt(),
        ]);
    }

        /**
     * Send a fresh code to the same recipients, respecting the cooldown.
     */
    public function resend(Request $request): RedirectResponse
    {
        if (! $request->session()->has('otp_user')) {
            return redirect()->route('login');
        }

        if (! $this->otpService->hasPending()) {
            return redirect()->route('otp.recipients');
        }

        if (! $this->otpService->resend()) {
            return back()->with('warning', $this->cooldownMessage());
        }

        return redirect()->route('otp.verify')->with('status', 'A new code has been sent.');
    }

    /**
     * Verify the submitted OTP and complete the login.
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:5']]);

        $user = $request->session()->get('otp_user');

        if (! $user) {
            return redirect()->route('login');
        }

        $result = $this->otpService->verify($request->input('code'));

        return match ($result) {
            OtpVerification::Valid   => $this->completeLogin($request, $user),
            OtpVerification::Invalid => back()->withErrors(['code' => $result->message()]),
            default                  => redirect()->route('otp.recipients')->withErrors(['code' => $result->message()]),
        };
    }

    private function completeLogin(Request $request, array $user): RedirectResponse
    {
        $this->otpService->reset();
        $request->session()->forget('otp_user');
        $request->session()->regenerate();
        $request->session()->put('auth_user', $user);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Split a comma-separated DB value into a clean, unique list.
     */
    private function splitList(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)))));
    }

    private function maskMobile(string $mobile): string
    {
        return strlen($mobile) > 7
            ? substr($mobile, 0, 4) . str_repeat('*', strlen($mobile) - 7) . substr($mobile, -3)
            : $mobile;
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($local, 0, 3) . '****@' . $domain;
    }

    private function cooldownMessage(): string
    {
        return 'Please wait ' . $this->otpService->cooldownRemaining() . ' seconds before requesting a new code.';
    }
}