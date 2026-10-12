<?php

namespace App\Services;

use App\Enums\OtpVerification;
use App\Exceptions\OtpDeliveryFailed;
use App\Mail\LoginOtpMail;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OtpService
{
    public const EXPIRY_SECONDS = 300;          // 5 minutes
    public const MAX_ATTEMPTS = 3;
    public const RESEND_COOLDOWN_SECONDS = 60;

    private const SESSION_KEY = 'otp';
    private const LAST_SENT_KEY = 'otp_last_sent_at'; // kept separately so clearing an OTP does not reset the cooldown

    public function __construct(
        private readonly SmsGateway $sms,
    ) {}

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

       if (! $this->deliver($code, $mobile, $email)) {
            // Nothing was sent, so let the user retry immediately
            $this->reset();

            throw new OtpDeliveryFailed();
        }

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
     * Send the OTP by SMS and/or email.
     * Succeeds when at least one channel accepts the code.
     */
    private function deliver(string $code, ?string $mobile, ?string $email): bool
    {
        if (config('services.otp.delivery') === 'log') {
            Log::channel('auth')->info('Login OTP generated (log mode)', compact('code', 'mobile', 'email'));

            return true;
        }

        $message = "Your SMS Portal Login OTP Code is {$code}. For any issue, contact your Account Manager.";

        $smsSent   = $mobile ? $this->sms->send($mobile, $message) : false;
        $emailSent = $email ? $this->sendEmail($email, $code) : false;

        Log::channel('auth')->info('Login OTP sent', [
            'mobile' => $mobile,
            'email'  => $email,
            'sms'    => $smsSent,
            'mail'   => $emailSent,
        ]);

        return $smsSent || $emailSent;
    }

    private function sendEmail(string $email, string $code): bool
    {
        try {
            Mail::to($email)->send(new LoginOtpMail($code, intdiv(self::EXPIRY_SECONDS, 60)));

            return true;
        } catch (Throwable $e) {
            Log::channel('auth')->error('OTP email failed', ['email' => $email, 'error' => $e->getMessage()]);

            return false;
        }
    }

}