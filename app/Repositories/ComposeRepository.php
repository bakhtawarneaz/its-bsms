<?php

namespace App\Repositories;

use App\Support\Database\StoredProcedure;
use Illuminate\Support\Facades\DB;

class ComposeRepository
{
    /** @return array{0: ?object, 1: array} account row, active masks */
    public function context(int $userId): array
    {
        $sets = StoredProcedure::multiple('usp_compose_context', ['user_id' => $userId]);

        return [$sets[0][0] ?? null, $sets[1] ?? []];
    }

    public function blockedWords(): array
    {
        return StoredProcedure::select('usp_compose_blocked_words');
    }

    /** @param array<int, string> $receivers */
    public function quick(int $userId, string $sender, string $message, int $cost, array $receivers, string $ip): object
    {
        return StoredProcedure::first('usp_compose_quick', [
            'user_id'   => $userId,
            'sender'    => $sender,
            'message'   => $message,
            'cost'      => $cost,
            'receivers' => implode(',', $receivers),
            'remote_ip' => $ip,
        ]);
    }

        public function groups(int $userId): array
    {
        return StoredProcedure::select('usp_compose_groups', ['user_id' => $userId]);
    }

    /** @param array<int, int> $groupIds */
    public function groupNumbers(int $userId, array $groupIds, bool $all): \Generator
    {
        return DB::cursor('EXEC dbo.usp_compose_group_numbers @user_id = ?, @group_ids = ?, @all = ?', [
            $userId, implode(',', $groupIds), $all ? 1 : 0,
        ]);
    }

    public function campaignCheck(int $userId, string $name, string $sender): object
    {
        return StoredProcedure::first('usp_compose_campaign_check', [
            'user_id' => $userId, 'camp_name' => $name, 'sender' => $sender,
        ]);
    }

    public function maskRoutes(string $sender): array
    {
        return StoredProcedure::select('usp_compose_mask_routes', ['sender' => $sender]);
    }

    public function campaign(array $params): object
    {
        return StoredProcedure::first('usp_compose_campaign', $params);
    }

        public function personalize(array $params): object
    {
        return StoredProcedure::first('usp_compose_personalize', $params);
    }
}