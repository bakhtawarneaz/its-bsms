<?php

namespace App\Support;

final class PhoneNumber
{
    /**
     * Pakistani mobile numbers in any common prefix format, e.g.
     * 03001234567, 3001234567, 923001234567, 00923001234567.
     */
    private const PAKISTAN_PATTERN = '/^(00923|009203|09203|9203|923|03|3)([0-7]\d{8})$/';

    /**
     * Normalize a Pakistani mobile number to the 92XXXXXXXXXX format.
     * Returns null when the number is not valid.
     */
    public static function normalize(string $number): ?string
    {
        $clean = str_replace(['-', ' ', '.', '+', '(', ')', '"', "'", ';', '/', '\\'], '', trim($number));

        if (! preg_match(self::PAKISTAN_PATTERN, $clean, $matches)) {
            return null;
        }

        return '923' . $matches[2];
    }

    public static function isValid(string $number): bool
    {
        return self::normalize($number) !== null;
    }
}