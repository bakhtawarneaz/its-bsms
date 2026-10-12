<?php

namespace App\Repositories;

use App\Support\Database\StoredProcedure;

class ActivityLogRepository
{
    /**
     * Insert a row into activity_log and return its id.
     */
    public function insert(int $userId, string $activity, string $ip, ?string $loginLocation = null): ?int
    {
        $result = StoredProcedure::first('usp_activity_log_insert', [
            'user_id'        => $userId,
            'activity'       => $activity,
            'ip'             => $ip,
            'login_location' => $loginLocation,
        ]);

        return isset($result->id) ? (int) $result->id : null;
    }
}