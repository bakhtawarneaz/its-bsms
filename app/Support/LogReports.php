<?php

namespace App\Support;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Definitions of the "message log" reports (one field filter + date-range export).
 * Field types: number, phone (normalized to 92…), status (name → code), text.
 */
final class LogReports
{
    private const GW_OUTBOX_STATUSES = [
        'undefined' => 0, 'send' => 1, 'sending' => 2, 'deliveredtonetwork' => 3,
        'deliveredtohandset' => 4, 'not sent' => 5, 'deliveryfailed' => 6, 'blocked' => 7, 'stopped' => 8,
    ];

    public static function get(string $key): array
    {
        $report = self::all()[$key] ?? throw new NotFoundHttpException();

        return ['key' => $key, ...$report];
    }

    private static function all(): array
    {
        return [
            'inbox' => [
                'title'        => 'Inbox',
                'subtitle'     => 'Incoming messages, newest first',
                'icon'         => 'inbox',
                'route'        => 'inbox.index',
                'export_route' => 'inbox.export',
                'list'         => 'usp_reports_inbox_list',
                'export'       => 'usp_reports_inbox_export',
                'file'         => 'inbox',
                'max_rows'     => 500000,
                'fields'       => [
                    'id'       => ['ID', 'number'],
                    'user_id'  => ['User ID', 'number'],
                    'prefix'   => ['Prefix', 'text'],
                    'sender'   => ['Sender', 'phone'],
                    'receiver' => ['Receiver', 'text'],
                    'msgdata'  => ['Message', 'text'],
                    'operator' => ['Operator', 'text'],
                ],
                'columns'      => [
                    'id' => ['ID', 'id'], 'user_id' => ['User', 'number'], 'sender' => ['Sender', 'phone'],
                    'prefix' => ['Prefix', 'text'], 'receiver' => ['Receiver', 'phone'], 'msgdata' => ['Message', 'message'],
                    'senttime' => ['Received', 'time'], 'operator' => ['Operator', 'text'],
                ],
                'headings'     => ['ID', 'User ID', 'Sender', 'Prefix', 'Receiver', 'Message', 'Sent time', 'Operator'],
            ],

            'gateway-outbox' => [
                'title'        => 'Gateway outbox',
                'subtitle'     => 'Messages sent by gateway users, newest first',
                'icon'         => 'server-stack',
                'route'        => 'gateway.outbox',
                'export_route' => 'gateway.outbox.export',
                'list'         => 'usp_reports_gw_outbox_list',
                'export'       => 'usp_reports_gw_outbox_export',
                'file'         => 'gateway_outbox',
                'max_rows'     => 500000,
                'statuses'     => self::GW_OUTBOX_STATUSES,
                'fields'       => [
                    'id'       => ['ID', 'number'],
                    'user_id'  => ['User ID', 'number'],
                    'sender'   => ['Sender', 'text'],
                    'receiver' => ['Receiver', 'phone'],
                    'status'   => ['Status', 'status'],
                    'msgdata'  => ['Message', 'text'],
                    'operator' => ['Operator', 'text'],
                ],
                'columns'      => [
                    'id' => ['ID', 'id'], 'user_id' => ['User', 'number'], 'sender' => ['Sender', 'strong'],
                    'receiver' => ['Receiver', 'phone'], 'msgdata' => ['Message', 'message'], 'senttime' => ['Sent', 'time'],
                    'network_time' => ['Network', 'time'], 'status' => ['Status', 'status'], 'cost' => ['Cost', 'cost'],
                    'operator' => ['Operator', 'text'],
                ],
                'headings'     => ['ID', 'User ID', 'Sender', 'Receiver', 'Sent time', 'Network time', 'Status', 'Cost', 'Operator', 'Message'],
            ],

            'gateway-inbox' => [
                'title'        => 'Gateway inbox',
                'subtitle'     => 'Messages received for gateway users, newest first',
                'icon'         => 'server',
                'route'        => 'gateway.inbox',
                'export_route' => 'gateway.inbox.export',
                'list'         => 'usp_reports_gw_inbox_list',
                'export'       => 'usp_reports_gw_inbox_export',
                'file'         => 'gateway_inbox',
                'max_rows'     => 500000,
                'fields'       => [
                    'id'       => ['ID', 'number'],
                    'user_id'  => ['User ID', 'number'],
                    'sender'   => ['Sender', 'phone'],
                    'receiver' => ['Receiver', 'text'],
                    'msgdata'  => ['Message', 'text'],
                ],
                'columns'      => [
                    'id' => ['ID', 'id'], 'user_id' => ['User', 'number'], 'sender' => ['Sender', 'phone'],
                    'receiver' => ['Receiver', 'phone'], 'msgdata' => ['Message', 'message'], 'senttime' => ['Received', 'time'],
                    'network_time' => ['Sent', 'time'], 'cost' => ['Cost', 'cost'], 'operator' => ['Operator', 'text'],
                ],
                'headings'     => ['ID', 'User ID', 'Sender', 'Receiver', 'Message', 'Sent time', 'Network time', 'Cost', 'Operator'],
            ],
        ];
    }
}