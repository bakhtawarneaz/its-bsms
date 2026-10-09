<?php

namespace App\Services;

use App\Enums\OtpVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OtpService
{
    public const EXPIRY_SECONDS = 300; // 5 minutes
    public const MAX_ATTEMPTS = 3;

    private const SESSION_KEY = 'otp';

    /**
     * Generate a new OTP, store its hash in the session and deliver it.
     */
    public function issue(?string $mobile, ?string $email): void
    {
        $code = (string) random_int(10000, 99999);

        session()->put(self::SESSION_KEY, [
            'hash'       => Hash::make($code),
            'expires_at' => now()->addSeconds(self::EXPIRY_SECONDS)->timestamp,
            'attempts'   => 0,
        ]);

        $this->deliver($code, $mobile, $email);
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

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
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