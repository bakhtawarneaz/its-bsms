<?php

namespace App\Services;

use App\Enums\OtpVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OtpService
{
    public const EXPIRY_SECONDS = 300;          // 5 minutes
    public const MAX_ATTEMPTS = 3;
    public const RESEND_COOLDOWN_SECONDS = 60;

    private const SESSION_KEY = 'otp';
    private const LAST_SENT_KEY = 'otp_last_sent_at'; // kept separately so clearing an OTP does not reset the cooldown

    /**
     * Generate a new OTP, store its hash in the session and deliver it.
     * Returns false when the resend cooldown is still active.
     */
    public function issue(?string $mobile, ?string $email): bool
    {
        if ($this->cooldownRemaining() > 0) {
            return false;
        }

        $code = (string) random_int(10000, 99999);

        // Issuing a new code replaces (and invalidates) any previous one
        session()->put(self::SESSION_KEY, [
            'hash'       => Hash::make($code),
            'expires_at' => now()->addSeconds(self::EXPIRY_SECONDS)->timestamp,
            'attempts'   => 0,
            'mobile'     => $mobile,
            'email'      => $email,
        ]);

        session()->put(self::LAST_SENT_KEY, now()->timestamp);

        $this->deliver($code, $mobile, $email);

        return true;
    }

    /**
     * Send a fresh code to the same recipients as the pending OTP.
     */
    public function resend(): bool
    {
        $otp = session(self::SESSION_KEY);

        if (! $otp) {
            return false;
        }

        return $this->issue($otp['mobile'], $otp['email']);
    }

    /**
     * Check a submitted code against the pending OTP.
     */
    public function verify(string $code): OtpVerification
    {
        $otp = session(self::SESSION_KEY);

        if (! $otp || now()->timestamp > $otp['expires_at']) {
            $this->clear();

            return OtpVerification::Expired;
        }

        if (! Hash::check($code, $otp['hash'])) {
            $otp['attempts']++;

            if ($otp['attempts'] >= self::MAX_ATTEMPTS) {
                $this->clear();

                return OtpVerification::TooManyAttempts;
            }

            session()->put(self::SESSION_KEY, $otp);

            return OtpVerification::Invalid;
        }

        $this->clear();

        return OtpVerification::Valid;
    }

    public function hasPending(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    /**
     * Unix timestamp when the pending OTP expires, or null if none.
     */
    public function expiresAt(): ?int
    {
        return session(self::SESSION_KEY . '.expires_at');
    }

    /**
     * Seconds left before another code may be sent.
     */
    public function cooldownRemaining(): int
    {
        $lastSent = session(self::LAST_SENT_KEY);

        return $lastSent ? max(0, $lastSent + self::RESEND_COOLDOWN_SECONDS - now()->timestamp) : 0;
    }

    /**
     * Unix timestamp when resending becomes available.
     */
    public function resendAvailableAt(): int
    {
        return now()->timestamp + $this->cooldownRemaining();
    }

    /**
     * Remove the pending OTP (the cooldown stays in place).
     */
    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Remove all OTP state after a successful login.
     */
    public function reset(): void
    {
        session()->forget([self::SESSION_KEY, self::LAST_SENT_KEY]);
    }

    /**
     * Send the OTP to the selected recipients.
     * In the local environment the code is written to the log instead of being sent.
     */
    private function deliver(string $code, ?string $mobile, ?string $email): void
    {
        if (app()->isLocal()) {
            Log::channel('auth')->info('Login OTP generated', compact('code', 'mobile', 'email'));

            return;
        }

        // SMS, email and WhatsApp channels will be wired in the next step
        throw new RuntimeException('OTP delivery channels are not configured yet.');
    }
}