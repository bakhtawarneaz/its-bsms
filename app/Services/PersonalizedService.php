<?php

namespace App\Services;

use App\Repositories\PersonalizedRepository;
use Illuminate\Validation\ValidationException;

/** Approval of personalized SMS (legacy campapprover "Approve Personalise SMS" / "All Personalized SMS"). */
class PersonalizedService
{
    public const PER_PAGE = 50;

    public const FIELDS = [
        'id'       => 'Message ID',
        'camp_id'  => 'Camp ID',
        'user_id'  => 'User ID',
        'receiver' => 'Receiver',
        'sender'   => 'Sender',
    ];

    /** Outbox status labels shown on the "All messages" tab. */
    public const STATUSES = [
        0  => 'Waiting approval',
        1  => 'Queued',
        7  => 'Blocked',
        11 => 'Suspended',
        12 => 'Held',
    ];

    public function __construct(
        private readonly PersonalizedRepository $repository,
        private readonly ActivityLogger $activity,
    ) {
    }

    public function campaigns(?int $scopeId): array
    {
        return $this->repository->campaigns($scopeId);
    }

    /** @return array{rows: array, hasNext: bool} */
    public function messages(?int $scopeId, bool $pending, ?int $campId, ?string $field, ?string $value, int $page): array
    {
        $rows = $this->repository->messages($scopeId, $pending, $campId, $field, $value, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        return ['rows' => array_slice($rows, 0, self::PER_PAGE), 'hasNext' => count($rows) > self::PER_PAGE];
    }

    /**
     * @param  array<int, int>  $ids
     *
     * @throws ValidationException
     */
    public function act(?int $scopeId, string $action, ?int $campId, array $ids): string
    {
        $result = $this->repository->action($scopeId, $action, $campId, $ids);

        if (($result->status ?? null) !== 'OK') {
            throw ValidationException::withMessages(['messages' => 'No messages waiting for approval were found for this selection.']);
        }

        $count = (int) $result->updated;
        $target = $campId ? "Campaign ID {$campId}" : 'MsgID ' . implode(',', array_slice($ids, 0, 20)) . (count($ids) > 20 ? '…' : '');
        $by = session('auth_user.username') ?? session('auth_user.id');
        $this->activity->log(($action === 'approve' ? 'Execute Message' : 'Suspended Message') . " ({$target}) - {$count} message(s) by {$by}.");

        return number_format($count) . ' message(s) ' . ($action === 'approve' ? 'approved and queued for sending.' : 'suspended.');
    }
}