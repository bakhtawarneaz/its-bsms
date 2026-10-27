<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/** Reads the first column (numbers) of an uploaded CSV / TXT / XLSX file. */
final class NumberFile
{
    /** @return \Generator<int, string> */
    public static function numbers(UploadedFile $file): \Generator
    {
        $path = $file->getRealPath();

        if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
            $reader = new XlsxReader();
            $reader->open($path);

            try {
                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $row) {
                        yield self::text($row->toArray()[0] ?? null);
                    }

                    break; // first sheet only
                }
            } finally {
                $reader->close();
            }

            return;
        }

        $handle = fopen($path, 'r');

        try {
            while (($cells = fgetcsv($handle)) !== false) {
                yield self::text(preg_replace('/^\xEF\xBB\xBF/', '', (string) ($cells[0] ?? '')));
            }
        } finally {
            fclose($handle);
        }
    }

    private static function text(mixed $cell): string
    {
        return is_float($cell) ? sprintf('%.0f', $cell) : trim((string) $cell);
    }
}