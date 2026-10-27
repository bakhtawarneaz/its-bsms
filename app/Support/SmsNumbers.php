<?php

namespace App\Support;

/** Receiver parsing with the legacy BSMS rules (General_DAL::is_valid_number / convert_number). */
final class SmsNumbers
{
    private const PK = '/^(00923|009203|09203|9203|923|03|3)([0-7])([0-9]{8})$/';

    private const UK = '/^(00447|0447|447)([0-9]{9})$/';

    private const US = '/^(001|01|1)([0-9]{10})$/';

    /** Normalized number (923…, 447…, 1…) or null when invalid. */
    public static function normalize(string $number, bool $allowInternational = false): ?string
    {
        $number = str_replace(['-', ' ', ',', '.', '+', '"', "'", ';', '\\', '/', "\r", "\n", '<', '>'], '', $number);

        return match (true) {
            (bool) preg_match(self::PK, $number, $m)                        => '923' . $m[2] . $m[3],
            $allowInternational && (bool) preg_match(self::UK, $number, $m) => '447' . $m[2],
            $allowInternational && (bool) preg_match(self::US, $number, $m) => '1' . $m[2],
            default                                                         => null,
        };
    }

    /**
     * Splits a pasted list (commas, new lines, semicolons, slashes) like the legacy screen.
     *
     * @return array{numbers: array<int, string>, rejected: int, duplicates: int}
     */
    public static function parse(string $raw, bool $allowInternational = false): array
    {
        $parts = preg_split('/[\r\n;,\/\\\\]+/', $raw);
        $numbers = [];
        $rejected = 0;
        $duplicates = 0;

        foreach ($parts as $part) {
            if (trim($part) === '') {
                continue;
            }

            $number = self::normalize($part, $allowInternational);

            if ($number === null) {
                $rejected++;
            } elseif (isset($numbers[$number])) {
                $duplicates++;
            } else {
                $numbers[$number] = true;
            }
        }

        return ['numbers' => array_keys($numbers), 'rejected' => $rejected, 'duplicates' => $duplicates];
    }
}