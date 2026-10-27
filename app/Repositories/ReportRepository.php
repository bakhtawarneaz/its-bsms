<?php

namespace App\Repositories;

use App\Support\Database\StoredProcedure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class ReportRepository
{
    public function smsTypes(): array
    {
        return StoredProcedure::select('usp_reports_sms_types');
    }

    public function outbox(string $source, ?string $field, ?string $value, int $offset, int $limit): array
    {
        return StoredProcedure::select('usp_reports_outbox_list', [
            'source' => $source,
            'field'  => $field,
            'value'  => $value,
            'offset' => $offset,
            'limit'  => $limit,
        ]);
    }

    /** Rows are read one by one from the server (no full result in memory). */
    public function outboxExport(array $filters): \Generator
    {
        return DB::cursor(
            'EXEC dbo.usp_reports_outbox_export @source = ?, @from = ?, @to = ?, @sender = ?, @receiver = ?, @sms_type = ?, @max_rows = ?',
            [
                $filters['source'],
                $filters['from']->format('Y-m-d\TH:i:s'),
                $filters['to']->format('Y-m-d\TH:i:s'),
                $filters['sender'],
                $filters['receiver'],
                $filters['sms_type'],
                $filters['max_rows'],
            ],
        );
    }

        /** $procedure always comes from App\Support\LogReports, never from input. */
    public function logPage(string $procedure, ?string $field, ?string $value, int $offset, int $limit): array
    {
        return StoredProcedure::select($procedure, [
            'field'  => $field,
            'value'  => $value,
            'offset' => $offset,
            'limit'  => $limit,
        ]);
    }

    public function logExport(string $procedure, array $filters): \Generator
    {
        return DB::cursor(
            "EXEC dbo.{$procedure} @from = ?, @to = ?, @sender = ?, @receiver = ?, @max_rows = ?",
            [
                $filters['from']->format('Y-m-d\TH:i:s'),
                $filters['to']->format('Y-m-d\TH:i:s'),
                $filters['sender'],
                $filters['receiver'],
                $filters['max_rows'],
            ],
        );
    }

        /** Procedures always come from App\Support\SummaryReports, never from input. */
    public function summaryUsers(string $procedure): array
    {
        return StoredProcedure::select($procedure);
    }

    /**
     * @return array{0: ?object, 1: array} Totals row, page rows.
     */
    public function summaryPage(string $procedure, array $filters, int $offset, int $limit): array
    {
        $sets = StoredProcedure::multiple($procedure, [...$this->summaryParams($filters), 'offset' => $offset, 'limit' => $limit]);

        return [$sets[0][0] ?? null, $sets[1] ?? []];
    }

    public function summaryExport(string $procedure, array $filters): \Generator
    {
        return DB::cursor(
            "EXEC dbo.{$procedure} @user_id = ?, @from = ?, @to = ?, @sender = ?, @operator = ?, @sms_type = ?, @status = ?, @max_rows = ?",
            [...array_values($this->summaryParams($filters)), $filters['max_rows']],
        );
    }

    private function summaryParams(array $filters): array
    {
        return [
            'user_id'  => $filters['user_id'],
            'from'     => $filters['from'],
            'to'       => $filters['to'],
            'sender'   => $filters['sender'],
            'operator' => $filters['operator'],
            'sms_type' => $filters['sms_type'],
            'status'   => $filters['status'],
        ];
    }

        /**
     * @return array{0: int, 1: array} Total rows, page rows.
     */
    public function activityPage(array $filters, int $offset, int $limit): array
    {
        $sets = StoredProcedure::multiple('usp_reports_activity_list', [
            'from'    => $filters['from_at']->format('Y-m-d\TH:i:s'),
            'to'      => $filters['to_at']->format('Y-m-d\TH:i:s'),
            'user_id' => $filters['user_id'],
            'ip'      => $filters['ip'],
            'search'  => $filters['search'],
            'offset'  => $offset,
            'limit'   => $limit,
        ]);

        return [(int) ($sets[0][0]->total_rows ?? 0), $sets[1] ?? []];
    }
}