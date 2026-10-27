<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExport
{
    /**
     * Stream rows straight to the browser so large exports never sit in memory.
     *
     * @param  array<int, string>  $headings
     * @param  iterable<object|array>  $rows
     */
    public static function stream(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            set_time_limit(0);

            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel opens UTF-8 (Urdu text) correctly

            fputcsv($out, $headings);

            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'cell'], array_values((array) $row)));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Long digit strings (mobiles, big ids) are written as ="…" so Excel keeps every digit
     * instead of showing 9.23E+11; spreadsheet formulas in user text are neutralised.
     */
    private static function cell(mixed $value): string
    {
        $value = (string) $value;

        if (strlen($value) >= 11 && ctype_digit($value)) {
            return '="' . $value . '"';
        }

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) && ! is_numeric($value)
            ? "'" . $value
            : $value;
    }
}