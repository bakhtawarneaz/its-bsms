<?php

namespace App\Repositories;

use App\Support\Database\StoredProcedure;
use Carbon\CarbonInterface;

class DashboardRepository
{
    /**
     * Fetch every dashboard dataset in a single round trip.
     */
    public function summary(CarbonInterface $from, CarbonInterface $to, ?int $userId, ?int $resellerId): array
    {
        $sets = array_pad(StoredProcedure::multiple('usp_dashboard_summary', [
            'from'        => $from->toDateString(),
            'to'          => $to->toDateString(),
            'user_id'     => $userId,
            'reseller_id' => $resellerId,
        ]), 10, []);

        return [
            'totals'    => $sets[0][0] ?? null,
            'daily'     => $sets[1],
            'operators' => $sets[2],
            'statuses'  => $sets[3],
            'channels'  => $sets[4],
            'monthly'   => $sets[5],
            'incoming'  => $sets[6],
            'previous'  => $sets[7][0] ?? null,
            'senders'   => $sets[8],
            'accounts'  => $sets[9],
        ];
    }
}