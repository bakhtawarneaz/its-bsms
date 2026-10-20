<?php

namespace App\Repositories;

use App\Support\Database\StoredProcedure;

class ProfileRepository
{
    public function find(int $userId): ?object
    {
        return StoredProcedure::first('usp_profile_get', ['user_id' => $userId]);
    }

    public function recentActivity(int $userId, int $limit = 6): array
    {
        return StoredProcedure::select('usp_profile_recent_activity', [
            'user_id' => $userId,
            'limit'   => $limit,
        ]);
    }

    /**
     * Update contact details and return the previous values.
     */
    public function update(int $userId, string $fullname, string $mobiles, string $emails, ?int $lowBalanceLimit): ?object
    {
        return StoredProcedure::first('usp_profile_update', [
            'user_id'           => $userId,
            'fullname'          => $fullname,
            'mobiles'           => $mobiles,
            'emails'            => $emails,
            'low_balance_limit' => $lowBalanceLimit,
        ]);
    }

    /**
     * Returns OK, WRONG_PASSWORD or SAME_PASSWORD.
     */
    public function changePassword(int $userId, string $currentHash, string $newHash): string
    {
        $result = StoredProcedure::first('usp_profile_change_password', [
            'user_id'      => $userId,
            'current_hash' => $currentHash,
            'new_hash'     => $newHash,
        ]);

        return $result->result ?? 'WRONG_PASSWORD';
    }
}