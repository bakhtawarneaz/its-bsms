<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Reads a personalized SMS contact file (CSV / TXT / XLSX).
 * Column A = mobile number, columns B..Z = values for the $B$..$Z$ keywords.
 * The first row is a header and is skipped (legacy behaviour).
 */
class PersonalizeFile
{
    /** Keywords the message may use, in column order (B..Z). */
    public const KEYWORDS = [
        '$B$', '$C$', '$D$', '$E$', '$F$', '$G$', '$H$', '$I$', '$J$', '$K$', '$L$', '$M$', '$N$',
        '$O$', '$P$', '$Q$', '$R$', '$S$', '$T$', '$U$', '$V$', '$W$', '$X$', '$Y$', '$Z$',
    ];

    /** Maximum data rows accepted from one file. */
    public const MAX_ROWS = 100000;

    /**
     * @return array{rows: array<int, array<int, string>>, columns: int, truncated: bool}
     */
    public static function read(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $reader = $extension === 'xlsx' ? new XlsxReader() : new CsvReader();
        $reader->open($file->getRealPath());

        $rows = [];
        $columns = 0;
        $header = true;
        $truncated = false;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    if ($header) {
                        $header = false;
                        continue;
                    }

                    $values = array_map([self::class, 'cell'], array_slice($row->toArray(), 0, 26));

                    // Legacy skips rows whose first column is empty.
                    if (($values[0] ?? '') === '') {
                        continue;
                    }

                    if (count($rows) >= self::MAX_ROWS) {
                        $truncated = true;
                        break 2;
                    }

                    $columns = max($columns, count($values));
                    $rows[] = $values;
                }

                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        return ['rows' => $rows, 'columns' => $columns, 'truncated' => $truncated];
    }

    /**
     * Legacy: if more than 90% of the rows are exact duplicates the file is rejected.
     */
    public static function mostlyDuplicates(array $rows): bool
    {
        $total = count($rows);

        if ($total === 0) {
            return false;
        }

        $unique = count(array_unique(array_map(fn (array $row) => implode("\x1F", $row), $rows)));

        return ceil(($total - $unique) / $total * 100) > 90;
    }

    /**
     * Replace $B$..$Z$ in the message with the row's columns B..Z.
     */
    public static function fill(string $message, array $row): string
    {
        $values = [];

        foreach (self::KEYWORDS as $index => $keyword) {
            $values[] = $row[$index + 1] ?? '';
        }

        return str_replace('\\', '', str_replace(self::KEYWORDS, $values, $message));
    }

    /**
     * True when the message contains at least one $B$..$Z$ keyword.
     */
    public static function hasKeyword(string $message): bool
    {
        foreach (self::KEYWORDS as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private static function cell(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_float($value) && floor($value) === $value) {
            // Excel stores long numbers as floats (9.23E+11); keep every digit.
            $value = sprintf('%.0f', $value);
        }

        // Legacy strips quotes from file values.
        return trim(str_replace(["'", '"'], '', (string) $value));
    }
}