<?php

namespace App\Enums;

enum LoginStatus: string
{
    case Ok                 = 'OK';
    case Invalid            = 'INVALID';
    case Disabled           = 'DISABLED';
    case Suspended          = 'SUSPENDED';
    case Expired            = 'EXPIRED';
    case Blocked            = 'BLOCKED';
    case PermanentlyBlocked = 'PERM_BLOCKED';

    /**
     * User-facing message shown on the login page for each status.
     */
    public function message(): string
    {
        return match ($this) {
            self::Ok                 => 'Login successful.',
            self::Invalid            => 'Wrong username or password.',
            self::Disabled           => 'Your account has been disabled by the admin.',
            self::Suspended          => 'Your account has been suspended by the admin.',
            self::Expired            => 'Your account has expired. Please contact the admin.',
            self::Blocked            => 'Your account is blocked due to multiple invalid login attempts.',
            self::PermanentlyBlocked => 'Your account is permanently blocked. Please contact your account manager.',
        };
    }
}