<?php

namespace App\Enums;

enum OtpVerification
{
    case Valid;
    case Invalid;
    case Expired;
    case TooManyAttempts;

    public function message(): string
    {
        return match ($this) {
            self::Valid           => 'OTP verified successfully.',
            self::Invalid         => 'Invalid OTP code. Please check and try again.',
            self::Expired         => 'Your OTP has expired. Please request a new one.',
            self::TooManyAttempts => 'Too many invalid attempts. Please request a new OTP.',
        };
    }
}