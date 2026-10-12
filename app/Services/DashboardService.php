<?php

namespace App\Services;

use App\Enums\UserType;
use App\Repositories\DashboardRepository;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

class DashboardService
{
    /**
     * Operator order and colors. The order is part of the colorblind-safety
     * validation, so keep it fixed even when an operator has no traffic.
     */
    public const OPERATORS = [
        'Jazz'    => '#eda100',
        'Zong'    => '#1baf7a',
        'Ufone'   => '#eb6834',
        'Telenor' => '#2a78d6',
        'Other'   => '#9aa1ab',
    ];

    /** Validated categorical order for channels; the rest fold into "Other". */
    private const CHANNEL_COLORS = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100'];

    private const STATUSES = [
        'delivered' => ['label' => 'Delivered',   'color' => '#0ca30c', 'icon' => 'check-circle'],
        'failed'    => ['label' => 'Failed',      'color' => '#d03b3b', 'icon' => 'x-circle'],
        'pending'   => ['label' => 'In progress', 'color' => '#fab219', 'icon' => 'clock'],
        'stopped'   => ['label' => 'Stopped',     'color' => '#9aa1ab', 'icon' => 'pause-circle'],
    ];

    private const CHANNEL_LABELS = [
        'API'    => 'API',
        'OTPAPI' => 'OTP API',
    ];

    public function __construct(
        private readonly DashboardRepository $repository,
    ) {}

    /**
     * Gateway users' traffic lives in the ITSSMSGateway database (separate module).
     */
    public function supports(UserType $type): bool
    {
        return $type !== UserType::GatewayUser;
    }

    public function scopeLabel(UserType $type): string
    {
        return match ($type) {
            UserType::SuperAdmin, UserType::Admin => 'All accounts',
            UserType::Reseller                    => 'Your account and your users',
            UserType::Reporting,
            UserType::CampReporting,
            UserType::LcReporting                 => 'Your reporting account',
            default                               => 'Your account',
        };
    }

    public function summary(array $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        [$userId, $resellerId] = $this->scopeFor($user);

        $data = $this->repository->summary($from, $to, $userId, $resellerId);

        $current  = $this->totals($data['totals']);
        $previous = $this->totals($data['previous']);

        return [
            'totals'  => $current,
            'changes' => [
                'messages'      => $this->change($current['messages'], $previous['messages']),
                'credits'       => $this->change($current['credits'], $previous['credits']),
                'failed'        => $this->change($current['failed'], $previous['failed']),
                // Percentage points, not percent
                'delivery_rate' => $previous['messages'] ? round($current['delivery_rate'] - $previous['delivery_rate'], 1) : null,
            ],
            'daily'     => $this->daily($data['daily'], $from, $to),
            'statuses'  => $this->statuses($data['statuses'], $current['messages']),
            'operators' => $this->operators($data['operators'], $current['messages']),
            'channels'  => $this->channels($data['channels'], $current['messages']),
            'monthly'   => $this->monthly($data['monthly'], $to),
            'incoming'  => $this->incoming($data['incoming']),
            'senders'   => $this->ranking($data['senders'], $current['messages']),
            // Rankings of accounts only make sense when more than one account is in scope
            'accounts'  => $userId === null ? $this->ranking($data['accounts'], $current['messages']) : [],
        ];
    }

    /**
     * Returns [user_id, reseller_id] filters, matching the legacy dashboard scoping.
     */
    private function scopeFor(array $user): array
    {
        return match (UserType::fromDatabase($user['user_type'])) {
            UserType::SuperAdmin, UserType::Admin => [null, null],
            UserType::Reseller                    => [null, (int) $user['id']],
            UserType::Reporting,
            UserType::CampReporting,
            UserType::LcReporting                 => [(int) ($user['reporting_user_id'] ?: $user['id']), null],
            default                               => [(int) $user['id'], null],
        };
    }

    private function totals(?object $row): array
    {
        $messages  = (int) ($row->messages ?? 0);
        $delivered = (int) ($row->delivered ?? 0);
        $failed    = (int) ($row->failed ?? 0);

        return [
            'messages'      => $messages,
            'credits'       => (int) ($row->credits ?? 0),
            'delivered'     => $delivered,
            'failed'        => $failed,
            'pending'       => (int) ($row->pending ?? 0),
            'delivery_rate' => $messages ? round($delivered / $messages * 100, 1) : 0,
            'failure_rate'  => $messages ? round($failed / $messages * 100, 1) : 0,
        ];
    }

    /**
     * Percent change from the previous period, or null when there is nothing to compare with.
     */
    private function change(int $current, int $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
    }

    /**
     * One point per day, including days without traffic.
     */
    private function daily(array $rows, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $byDay = collect($rows)->keyBy(fn ($row) => substr((string) $row->day, 0, 10));

        $series = ['dates' => [], 'messages' => [], 'delivered' => [], 'failed' => [], 'credits' => [], 'rate' => []];

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $row = $byDay[$day->toDateString()] ?? null;
            $messages = (int) ($row->messages ?? 0);

            $series['dates'][]     = $day->toDateString();
            $series['messages'][]  = $messages;
            $series['delivered'][] = (int) ($row->delivered ?? 0);
            $series['failed'][]    = (int) ($row->failed ?? 0);
            $series['credits'][]   = (int) ($row->credits ?? 0);
            $series['rate'][]      = $messages ? round(((int) $row->delivered) / $messages * 100, 1) : 0;
        }

        return $series;
    }

    private function statuses(array $rows, int $total): array
    {
        $byStatus = collect($rows)->mapWithKeys(fn ($row) => [$row->status_group => (int) $row->messages]);

        return collect(self::STATUSES)
            ->map(fn ($meta, $key) => $meta + [
                'messages' => $byStatus[$key] ?? 0,
                'share'    => $total ? round(($byStatus[$key] ?? 0) / $total * 100, 1) : 0,
            ])
            ->filter(fn ($status) => $status['messages'] > 0)
            ->values()
            ->all();
    }

    private function operators(array $rows, int $total): array
    {
        $byOperator = collect($rows)->keyBy('operator');
        $max = max(1, (int) collect($rows)->max('messages'));

        return collect(self::OPERATORS)
            ->map(function ($color, $name) use ($byOperator, $max, $total) {
                $row = $byOperator[$name] ?? null;
                $messages = (int) ($row->messages ?? 0);

                return [
                    'name'          => $name,
                    'color'         => $color,
                    'messages'      => $messages,
                    'share'         => $total ? round($messages / $total * 100, 1) : 0,
                    'delivery_rate' => $messages ? round(((int) $row->delivered) / $messages * 100, 1) : 0,
                    'width'         => round($messages / $max * 100, 1),
                ];
            })
            ->reject(fn ($operator) => $operator['name'] === 'Other' && $operator['messages'] === 0)
            ->values()
            ->all();
    }

    /**
     * Top four channels in validated colors, everything else folded into "Other".
     */
    private function channels(array $rows, int $total): array
    {
        $rows = collect($rows)->map(fn ($row) => [
            'name'     => self::CHANNEL_LABELS[$row->sms_type] ?? $row->sms_type,
            'messages' => (int) $row->messages,
        ]);

        $items = $rows->take(4)->values()->map(fn ($item, $index) => $item + ['color' => self::CHANNEL_COLORS[$index]]);

        $rest = $rows->slice(4)->sum('messages');

        if ($rest > 0) {
            $items->push(['name' => 'Other', 'messages' => $rest, 'color' => '#9aa1ab']);
        }

        return $items
            ->map(fn ($item) => $item + ['share' => $total ? round($item['messages'] / $total * 100, 1) : 0])
            ->all();
    }

    /**
     * Twelve monthly buckets ending at the selected end date.
     */
    private function monthly(array $rows, CarbonImmutable $to): array
    {
        $byMonth = collect($rows)->mapWithKeys(fn ($row) => [trim($row->month) => (int) $row->messages]);

        $labels = [];
        $values = [];

        foreach (CarbonPeriod::create($to->startOfMonth()->subMonths(11), '1 month', $to->startOfMonth()) as $month) {
            $labels[] = $month->format('M y');
            $values[] = $byMonth[$month->format('Y-m')] ?? 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function incoming(array $rows): array
    {
        $byOperator = collect($rows)->mapWithKeys(fn ($row) => [$row->operator => (int) $row->messages]);
        $total = (int) $byOperator->sum();

        $operators = collect(self::OPERATORS)
            ->map(fn ($color, $name) => [
                'name'     => $name,
                'color'    => $color,
                'messages' => $byOperator[$name] ?? 0,
                'share'    => $total ? round(($byOperator[$name] ?? 0) / $total * 100, 1) : 0,
            ])
            ->reject(fn ($operator) => $operator['name'] === 'Other' && $operator['messages'] === 0)
            ->values()
            ->all();

        return ['total' => $total, 'operators' => $operators];
    }

    private function ranking(array $rows, int $total): array
    {
        $max = max(1, (int) collect($rows)->max('messages'));

        return collect($rows)
            ->map(fn ($row) => [
                'name'     => $row->name,
                'messages' => (int) $row->messages,
                'share'    => $total ? round(((int) $row->messages) / $total * 100, 1) : 0,
                'width'    => round(((int) $row->messages) / $max * 100, 1),
            ])
            ->all();
    }
}