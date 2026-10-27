<?php

namespace App\Services;

use App\Repositories\ContactRepository;
use App\Support\CsvExport;
use App\Support\PhoneNumber;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class ContactService
{
    public const PER_PAGE = 25;

    public const EXPORT_LIMIT = 250000;

    public const FIELDS = [
        'id'        => 'ID',
        'user_id'   => 'User ID',
        'fullname'  => 'Name (starts with)',
        'numbers'   => 'Mobile',
        'groupname' => 'Group name',
        'option1'   => 'Extra field 1',
        'option2'   => 'Extra field 2',
        'option3'   => 'Extra field 3',
    ];

    public function __construct(
        private readonly ContactRepository $repository,
        private readonly ActivityLogger $activity,
    ) {
    }

    /**
     * One page without a total count (the table holds ~150M rows).
     *
     * @return array{rows: array, hasMore: bool, page: int}
     *
     * @throws ValidationException
     */
    public function page(?string $field, ?string $value, int $page): array
    {
        if (in_array($field, ['id', 'user_id'], true) && $value !== null && ! ctype_digit($value)) {
            throw ValidationException::withMessages(['value' => self::FIELDS[$field] . ' must be a number.']);
        }

        if ($field === 'numbers' && $value !== null && PhoneNumber::isValid($value)) {
            $value = PhoneNumber::normalize($value);
        }

        $rows = $this->repository->list($field, $value, ($page - 1) * self::PER_PAGE, self::PER_PAGE + 1);

        return [
            'rows'    => array_map([$this, 'withGroups'], array_slice($rows, 0, self::PER_PAGE)),
            'hasMore' => count($rows) > self::PER_PAGE,
            'page'    => $page,
        ];
    }

    /** @return array<int, string> id => name */
    public function groups(int $userId): array
    {
        return collect($this->repository->groups($userId))
            ->mapWithKeys(fn ($group) => [(int) $group->id => trim((string) $group->groupname)])
            ->all();
    }

    /** @throws ValidationException */
    public function add(int $userId, array $payload): void
    {
        $result = $this->repository->add($userId, $payload);

        match ($result->status ?? null) {
            'OK'              => null,
            'NUMBER_TAKEN'    => throw ValidationException::withMessages(['mobile' => 'Mobile Number Already Exist.']),
            'GROUP_NOT_FOUND' => throw ValidationException::withMessages(['group_id' => 'Group not found.']),
            default           => throw new \RuntimeException('Contact could not be added.'),
        };

        $this->activity->log("Add New Contact - Contact ({$payload['number']}) Added Successfully.");
    }

    /** @throws ValidationException */
    public function update(int $id, array $payload): void
    {
        $result = $this->repository->update($id, $payload);

        match ($result->status ?? null) {
            'OK'           => null,
            'NUMBER_TAKEN' => throw ValidationException::withMessages(['mobile' => 'Mobile Number Already Exist.']),
            default        => throw new NotFoundHttpException('Contact not found.'),
        };

        $this->activity->log("Edit Contact - Contact Id {$id} Edited Successfully" . ($payload['number'] ? " (number {$payload['number']})." : '.'));
    }

    public function delete(int $id): string
    {
        $result = $this->repository->delete($id);

        if (($result->status ?? null) !== 'OK') {
            throw new NotFoundHttpException('Contact not found.');
        }

        $this->activity->log("Delete Contact - Contact ({$result->numbers}) Id {$id} Deleted Successfully.");

        return (string) $result->numbers;
    }

    /**
     * @param  array<int, int>  $groupIds
     * @return array{added: int, removed: int}
     */
    public function setGroups(int $userId, int $contactId, array $groupIds): array
    {
        $result = $this->repository->setGroups($userId, $contactId, $groupIds);

        if (($result->status ?? null) !== 'OK') {
            throw new NotFoundHttpException('Contact not found.');
        }

        $this->activity->log("Assign Groups Contact - Contact Id {$contactId}: {$result->added} group(s) added, {$result->removed} removed.");

        return ['added' => (int) $result->added, 'removed' => (int) $result->removed];
    }

    /**
     * $scope: "all" (every user, filtered like the list), "mine", or "group:{id}" (own group).
     *
     * @throws ValidationException
     */
    public function export(string $scope, ?string $field, ?string $value, int $signedInId): StreamedResponse
    {
        $userId = $groupId = null;
        $label = 'all';

        if ($scope === 'mine') {
            $userId = $signedInId;
            $label = 'mine';
            $field = $value = null;
        } elseif (str_starts_with($scope, 'group:')) {
            $groupId = (int) substr($scope, 6);
            $groupName = $this->groups($signedInId)[$groupId] ?? throw new NotFoundHttpException('Group not found.');
            $userId = $signedInId;
            $label = preg_replace('/[^A-Za-z0-9_-]+/', '-', $groupName);
            $field = $value = null;
        } elseif ($value !== null) {
            // Same rules as the list search.
            if (in_array($field, ['id', 'user_id'], true) && ! ctype_digit($value)) {
                throw ValidationException::withMessages(['value' => self::FIELDS[$field] . ' must be a number.']);
            }

            if ($field === 'numbers' && PhoneNumber::isValid($value)) {
                $value = PhoneNumber::normalize($value);
            }
        } else {
            $field = null;
        }

        return CsvExport::stream(
            "contacts_{$label}_" . now()->format('Ymd_His') . '.csv',
            ['ID', 'User ID', 'Name', 'Mobile Number', 'Emails', 'Groups', 'Extra field 1', 'Extra field 2', 'Extra field 3'],
            $this->repository->export($userId, $groupId, $field, $value, self::EXPORT_LIMIT),
        );
    }

    /** Turns "id:name‖id:name" into a list of groups on the row. */
    private function withGroups(object $row): object
    {
        $row->groups = [];

        foreach (array_filter(explode("\u{2016}", (string) $row->group_list)) as $item) {
            [$id, $name] = array_pad(explode(':', $item, 2), 2, '');
            $row->groups[] = ['id' => (int) $id, 'name' => $name];
        }

        return $row;
    }

        private const IMPORT_CHUNK = 2000;

    /**
     * Legacy import: column A mobile, B name, C email, D-F extra fields. Invalid numbers
     * (including a header row) are rejected; repeats inside the file are skipped.
     *
     * @return array{total: int, rejected: int, duplicates: int, inserted: int}
     *
     * @throws ValidationException
     */
    public function import(int $userId, UploadedFile $file, ?int $groupId): array
    {
        set_time_limit(0);

        $stats = ['total' => 0, 'rejected' => 0, 'duplicates' => 0, 'inserted' => 0];

        DB::transaction(function () use ($userId, $file, $groupId, &$stats) {
            $seen = [];
            $batch = [];

            foreach ($this->fileRows($file) as $cells) {
                $cells = array_map(fn ($cell) => $this->cellText($cell), $cells);

                if (implode('', $cells) === '') {
                    continue; // blank line
                }

                $stats['total']++;
                $raw = $cells[0] ?? '';

                if (! PhoneNumber::isValid($raw)) {
                    $stats['rejected']++;

                    continue;
                }

                $number = PhoneNumber::normalize($raw);

                if (isset($seen[$number])) {
                    $stats['duplicates']++;

                    continue;
                }

                $seen[$number] = true;
                $batch[] = [
                    'numbers'  => $number,
                    'fullname' => mb_substr($cells[1] ?? '', 0, 50),
                    'emails'   => mb_substr($cells[2] ?? '', 0, 50),
                    'option1'  => mb_substr($cells[3] ?? '', 0, 50),
                    'option2'  => mb_substr($cells[4] ?? '', 0, 50),
                    'option3'  => mb_substr($cells[5] ?? '', 0, 50),
                ];

                if (count($batch) === self::IMPORT_CHUNK) {
                    $stats['inserted'] += $this->importChunk($userId, $groupId, $batch);
                    $batch = [];
                }
            }

            if ($batch) {
                $stats['inserted'] += $this->importChunk($userId, $groupId, $batch);
            }
        });

        $this->activity->log("Import Contacts - Total contacts = {$stats['total']} - Rejected Contacts = {$stats['rejected']}"
            . " - Duplicates in file = {$stats['duplicates']} - Successfuly Import = {$stats['inserted']}"
            . ($groupId ? " - Group Id {$groupId}" : ''));

        return $stats;
    }

    /** @throws ValidationException */
    private function importChunk(int $userId, ?int $groupId, array $rows): int
    {
        $xml = '<contacts>';

        foreach ($rows as $row) {
            $xml .= '<contact>';

            foreach ($row as $tag => $value) {
                // Drop characters that are not allowed in XML before escaping.
                $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value);
                $xml .= "<{$tag}>" . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</{$tag}>";
            }

            $xml .= '</contact>';
        }

        $result = $this->repository->import($userId, $groupId, $xml . '</contacts>');

        if (($result->status ?? null) === 'GROUP_NOT_FOUND') {
            throw ValidationException::withMessages(['group_id' => 'Group not found.']);
        }

        return (int) ($result->inserted ?? 0);
    }

    /** Rows of the first sheet (XLSX) or of the CSV file, as arrays of cells. */
    private function fileRows(UploadedFile $file): \Generator
    {
        $path = $file->getRealPath();

        if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
            $reader = new XlsxReader();
            $reader->open($path);

            try {
                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $row) {
                        yield $row->toArray();
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
            $first = true;

            while (($cells = fgetcsv($handle)) !== false) {
                if ($first) {
                    $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($cells[0] ?? '')); // strip BOM
                    $first = false;
                }

                yield $cells;
            }
        } finally {
            fclose($handle);
        }
    }

    /** Excel stores numbers as floats; keep them as plain digits. */
    private function cellText(mixed $cell): string
    {
        return match (true) {
            $cell === null                       => '',
            is_float($cell)                      => sprintf('%.0f', $cell),
            $cell instanceof \DateTimeInterface  => $cell->format('Y-m-d'),
            default                              => trim((string) $cell),
        };
    }
}