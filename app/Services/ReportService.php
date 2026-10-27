<?php

namespace App\Services;

use App\Repositories\ReportRepository;
use App\Support\CsvExport;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ReportService
{
    public function __construct(private readonly ReportRepository $repository)
    {
    }

    /** @return array<string, string> code => name */
    public function smsTypes(): array
    {
        return Cache::remember('reports.sms_types', now()->addHour(), fn () => collect($this->repository->smsTypes())
            ->mapWithKeys(fn ($type) => [trim((string) $type->code) => trim((string) $type->name)])
            ->all());
    }

    public function outboxPage(array $filters): array
    {
        $perPage = $filters['per_page'];
        $rows = $this->repository->outbox(
            $filters['source'],
            $filters['field'],
            $filters['value'],
            ($filters['page'] - 1) * $perPage,
            $perPage + 1, // one extra row tells us whether a next page exists
        );

        return [
            'rows'     => array_slice($rows, 0, $perPage),
            'hasMore'  => count($rows) > $perPage,
            'filters'  => $filters,
            'smsTypes' => $this->smsTypes(),
        ];
    }

    public function exportOutbox(array $filters): StreamedResponse
    {
        $headings = ['ID', 'User ID'];

        if ($filters['source'] === 'campaign') {
            $headings[] = 'Camp ID';
        }

        $headings = [...$headings, 'SMS Type', 'Sender', 'Receiver', 'Sent time', 'Network time', 'Status', 'Cost', 'Route Name', 'Message'];

        $name = 'outbox_' . $filters['source'] . '_' . $filters['from']->format('Ymd') . '-' . $filters['to']->format('Ymd') . '.csv';

        return CsvExport::stream($name, $headings, $this->repository->outboxExport($filters));
    }

        public function logPage(array $report, array $filters): array
    {
        $perPage = $filters['per_page'];
        $rows = $this->repository->logPage(
            $report['list'],
            $filters['field'],
            $filters['value'],
            ($filters['page'] - 1) * $perPage,
            $perPage + 1, // one extra row tells us whether a next page exists
        );

        return [
            'report'  => $report,
            'rows'    => array_slice($rows, 0, $perPage),
            'hasMore' => count($rows) > $perPage,
            'filters' => $filters,
        ];
    }

    public function exportLog(array $report, array $filters): StreamedResponse
    {
        $name = $report['file'] . '_' . $filters['from']->format('Ymd') . '-' . $filters['to']->format('Ymd') . '.csv';

        return CsvExport::stream($name, $report['headings'], $this->repository->logExport($report['export'], $filters));
    }

        public function summaryPage(array $report, array $filters): array
    {
        $perPage = $filters['per_page'];
        [$totals, $rows] = $this->repository->summaryPage($report['list'], $filters, ($filters['page'] - 1) * $perPage, $perPage);

        $paginator = new LengthAwarePaginator($rows, (int) ($totals->total_rows ?? 0), $perPage, $filters['page'], [
            'path'  => request()->url(),
            'query' => request()->query(),
        ]);

        return [
            'report'  => $report,
            'rows'    => $paginator,
            'totals'  => $totals,
            'filters' => $filters,
            'users'   => $this->summaryUsers($report),
        ];
    }

    /** @return array<int, string> id => username */
    public function summaryUsers(array $report): array
    {
        return Cache::remember("reports.summary_users.{$report['key']}", now()->addMinutes(10), fn () => collect($this->repository->summaryUsers($report['users']))
            ->mapWithKeys(fn ($user) => [(int) $user->id => trim((string) $user->username)])
            ->all());
    }

    public function exportSummary(array $report, array $filters): StreamedResponse
    {
        $name = $report['file'] . '_' . str_replace('-', '', $filters['from']) . '-' . str_replace('-', '', $filters['to']) . '.csv';

        return CsvExport::stream($name, $report['headings'], $this->repository->summaryExport($report['export'], $filters));
    }

        public function activityPage(array $filters): array
    {
        $perPage = $filters['per_page'];
        [$total, $rows] = $this->repository->activityPage($filters, ($filters['page'] - 1) * $perPage, $perPage);

        return [
            'rows'    => new LengthAwarePaginator($rows, $total, $perPage, $filters['page'], [
                'path'  => request()->url(),
                'query' => request()->query(),
            ]),
            'filters' => $filters,
            // Same account list as the SMS summary filter.
            'users'   => $this->summaryUsers(\App\Support\SummaryReports::get('summary')),
        ];
    }
}