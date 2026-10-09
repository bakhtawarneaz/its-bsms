<?php

namespace App\Enums;

enum UserType: string
{
    case SuperAdmin    = 'superadmin';
    case Admin         = 'admin';
    case Reseller      = 'reseller';
    case User          = 'user';
    case Reporting     = 'reporting';
    case CampReporting = 'campreporting';
    case LcReporting   = 'lcreporting';
    case CampApprover  = 'campapprover';
    case GatewayUser   = 'gwuser';

    /**
     * Resolve a user type from the database value (case-insensitive).
     */
    public static function fromDatabase(string $value): self
    {
        return self::from(strtolower(trim($value)));
    }

    /**
     * Human-readable label for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin    => 'Super Admin',
            self::Admin         => 'Admin',
            self::Reseller      => 'Reseller',
            self::User          => 'User',
            self::Reporting     => 'Reporting',
            self::CampReporting => 'Campaign Reporting',
            self::LcReporting   => 'LC Reporting',
            self::CampApprover  => 'Campaign Approver',
            self::GatewayUser   => 'Gateway User',
        };
    }

    /**
     * Reporting and gateway users skip the OTP step at login.
     */
    public function requiresOtp(): bool
    {
        return ! in_array($this, [self::Reporting, self::GatewayUser], true);
    }
}