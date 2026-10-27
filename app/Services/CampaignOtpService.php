<?php

namespace App\Services;

use App\Enums\OtpVerification;
use App\Exceptions\OtpDeliveryFailed;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Campaign execution OTP (legacy Send_Campaign_OTP_DAL): 5 minutes, 3 tries, sent to the
 * mobile/email that received the login OTP.
 */
class CampaignOtpService
{
    public const EXPIRY_SECONDS = 300;
    public const MAX_ATTEMPTS = 3;
    public const RESEND_COOLDOWN_SECONDS = 60;

    private const SESSION_KEY = 'campaign_otp';

    public function __construct(private readonly SmsGateway $sms)
    {
    }

    public function hasRecipients(): bool
    {
        $to = session('otp_recipients');

        return $to && (! empty($to['mobile']) || ! empty($to['email']));
    }

    /** Sends a code unless a valid one is already pending (legacy "already sent"). Returns true when sent now. */
    public function ensureSent(): bool
    {
        $otp = session(self::SESSION_KEY);

        if ($otp && now()->timestamp <= $otp['expires_at']) {
            return false;
        }

        $this->issue();

        return true;
    }

    /** Returns false while the resend cooldown is active. */
    public function resend(): bool
    {
        $otp = session(self::SESSION_KEY);

        if ($otp && now()->timestamp < $otp['sent_at'] + self::RESEND_COOLDOWN_SECONDS) {
            return false;
        }

        $this->issue();

        return true;
    }

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

    public function triesLeft(): int
    {
        return self::MAX_ATTEMPTS - (int) session(self::SESSION_KEY . '.attempts', 0);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    private function issue(): void
    {
        $to = session('otp_recipients', []);
        $code = (string) random_int(10000, 99999);

        session()->put(self::SESSION_KEY, [
            'hash'       => Hash::make($code),
            'expires_at' => now()->addSeconds(self::EXPIRY_SECONDS)->timestamp,
            'sent_at'    => now()->timestamp,
            'attempts'   => 0,
        ]);

        if (! $this->deliver($code, $to['mobile'] ?? null, $to['email'] ?? null)) {
            $this->clear();

            throw new OtpDeliveryFailed();
        }
    }

    private function deliver(string $code, ?string $mobile, ?string $email): bool
    {
        if (config('services.otp.delivery') === 'log') {
            Log::channel('auth')->info('Campaign OTP generated (log mode)', compact('code', 'mobile', 'email'));

            return true;
        }

        $message = "Your SMS Portal Campaign execution OTP Code is {$code}. For any issue, contact your Account Manager.";

        $smsSent = $mobile ? $this->sms->send($mobile, $message) : false;
        $emailSent = false;

        if ($email) {
            try {
                Mail::raw($message, fn ($mail) => $mail->to($email)->subject('Campaign OTP Authentication'));
                $emailSent = true;
            } catch (Throwable $e) {
                Log::channel('auth')->error('Campaign OTP email failed', ['email' => $email, 'error' => $e->getMessage()]);
            }
        }

        return $smsSent || $emailSent;
    }
}