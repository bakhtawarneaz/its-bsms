<?php

namespace App\Support;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Definitions of the summary reports (filters + totals + paged rows + export). */
final class SummaryReports
{
    public const OPERATORS = [
        'mobilink' => 'Mobilink',
        'ufone'    => 'Ufone',
        'zong'     => 'Zong',
        'telenor'  => 'Telenor',
        'warid'    => 'Warid',
    ];

    public static function get(string $key): array
    {
        $report = self::all()[$key] ?? throw new NotFoundHttpException();

        return ['key' => $key, ...$report];
    }

    private static function all(): array
    {
        return [
            'summary' => [
                'title'        => 'SMS summary',
                'subtitle'     => 'Daily totals by user, sender, network and status',
                'route'        => 'summary.index',
                'export_route' => 'summary.export',
                'list'         => 'usp_reports_summary_list',
                'export'       => 'usp_reports_summary_export',
                'users'        => 'usp_reports_summary_users',
                'file'         => 'sms_summary',
                'max_rows'     => 500000,
                // Values must match the view data exactly (legacy spelling kept).
                'sms_types'    => ['API' => 'API', 'Campiagn' => 'Campaign', 'Personalize' => 'Personalize', 'Quick SMS' => 'Quick SMS'],
                'statuses'     => [
                    'send' => 'Send', 'sent' => 'Sent', 'Notsent' => 'Not sent', 'deliveredtohandset' => 'Delivered to handset',
                    'deliveryfailed' => 'Delivery failed', 'blocked' => 'Blocked', 'stopped' => 'Stopped',
                ],
                'columns'      => [
                    'id' => ['ID', 'id'], 'username' => ['User', 'strong'], 'summary_date' => ['Date', 'date'],
                    'sms_type' => ['Type', 'text'], 'sender' => ['Sender', 'text'], 'operator' => ['Network', 'text'],
                    'status' => ['Status', 'status'], 'sms_count' => ['SMS', 'count'], 'cost' => ['Cost', 'cost'],
                ],
                'headings'     => ['Id', 'User name', 'Date', 'SMS Type', 'Sender', 'Operator', 'Status', 'Count', 'Cost'],
            ],

            'gateway-summary' => [
                'title'        => 'Gateway SMS summary',
                'subtitle'     => 'Daily totals for gateway users',
                'route'        => 'gateway.summary',
                'export_route' => 'gateway.summary.export',
                'list'         => 'usp_reports_gw_summary_list',
                'export'       => 'usp_reports_gw_summary_export',
                'users'        => 'usp_reports_gw_summary_users',
                'file'         => 'gateway_summary',
                'max_rows'     => 500000,
                'sms_types'    => [],
                // Keys are the gateway status codes (legacy mapping; 8 also covers 9 and 10).
                'statuses'     => [
                    1 => 'Send', 3 => 'Sent', 5 => 'Not sent', 4 => 'Delivered to handset',
                    6 => 'Delivery failed', 7 => 'Blocked', 8 => 'Stopped',
                ],
                'columns'      => [
                    'id' => ['ID', 'id'], 'summary_date' => ['Date', 'date'], 'username' => ['User', 'strong'],
                    'sender' => ['Sender', 'text'], 'status' => ['Status', 'status'], 'sms_count' => ['SMS', 'count'],
                    'cost' => ['Cost', 'cost'], 'operator' => ['Network', 'text'],
                ],
                'headings'     => ['Id', 'Date', 'User name', 'Sender', 'Status', 'Count', 'Cost', 'Operator'],
            ],
        ];
    }
}