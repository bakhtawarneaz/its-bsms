<?php

namespace App\Services;

use App\Repositories\CampaignRepository;
use App\Support\CsvExport;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CampaignService
{
    public const PER_PAGE = 25;

    public const STATES = [
        'running'   => 'Running',
        'stopped'   => 'Stopped',
        'approval'  => 'Waiting approval',
        'suspended' => 'Suspended',
        'completed' => 'Completed',
    ];

    public const FIELDS = [
        'id'        => 'Camp ID',
        'username'  => 'Username',
        'user_id'   => 'User ID',
        'camp_name' => 'Camp name',
        'sender'    => 'Sender',
    ];

    public const ACTIONS = ['approve', 'stop', 'play', 'suspend', 'reschedule'];

    private const DONE = [
        'approve'    => 'Campaign approved and released for sending.',
        'stop'       => 'Campaign stopped. The balance has not been reverted.',
        'play'       => 'Campaign started again.',
        'suspend'    => 'Campaign suspended.',
        'reschedule' => 'Campaign rescheduled.',
    ];

    private const LABELS = [
        'approve'    => 'Execute Campaign',
        'stop'       => 'Stop Campaign',
        'play'       => 'Played Campaign',
        'suspend'    => 'Campaign Suspended',
        'reschedule' => 'Campaign Reschedule',
    ];

    public function __construct(
        private readonly CampaignRepository $repository,
        private readonly ActivityLogger $activity,
    ) {
    }

    /**
     * @param  int|null  $scopeId  campapprover id (only its users' campaigns), null for superadmin
     * @return array{campaigns: LengthAwarePaginator, counts: array<string, int>}
     */
    public function page(?string $field, ?string $value, ?string $state, int $page, ?int $scopeId = null): array
    {
        [$counts, $rows] = $this->repository->list($field, $value, $state, ($page - 1) * self::PER_PAGE, self::PER_PAGE, $scopeId);

        $total = $state ? ($counts[$state] ?? 0) : array_sum($counts);

        return [
            'campaigns' => new LengthAwarePaginator($rows, $total, self::PER_PAGE, $page, [
                'path' => request()->url(), 'query' => request()->query(),
            ]),
            'counts' => ['' => array_sum($counts)] + array_map(fn ($key) => $counts[$key] ?? 0, array_combine(array_keys(self::STATES), array_keys(self::STATES))),
        ];
    }

    /** @throws ValidationException */
    public function act(int $id, string $action, ?Carbon $at = null, ?int $scopeId = null): string
    {
        $result = $this->repository->action($id, $action, $at?->format('Y-m-d H:i:s'), $scopeId);

        match ($result->status ?? null) {
            'OK'          => null,
            'NOT_FOUND'   => throw new NotFoundHttpException('Campaign not found.'),
            'FINISHED'    => throw ValidationException::withMessages(['campaign' => 'Campaign already has been executed.']),
            'NOT_ALLOWED' => throw ValidationException::withMessages(['campaign' => match ($action) {
                'reschedule' => 'Reschedule time less than Current Time, or the campaign is not stopped.',
                'approve'    => "Campaign isn't on approval status.",
                default      => 'This action is not available for the campaign in its current state.',
            }]),
            default       => throw new \RuntimeException('Campaign action failed.'),
        };

        $by = session('auth_user.username') ?? session('auth_user.id');
        $this->activity->log(self::LABELS[$action] . " (CID = {$id}) by {$by} - user id {$result->user_id}" . ($at ? ', new time ' . $at->format('Y-m-d H:i') : '') . '.');

        return self::DONE[$action];
    }

    public function logs(int $id, ?int $scopeId = null): StreamedResponse
    {
        if ($scopeId !== null && ! $this->repository->list('id', (string) $id, null, 0, 1, $scopeId)[1]) {
            throw new NotFoundHttpException('Campaign not found.');
        }

        $this->activity->log("Campaign Logs Download - Campaign ID {$id}.");

        return CsvExport::stream(
            "campaign_{$id}_logs.csv",
            ['Sender', 'Receiver', 'Message', 'Sent time', 'Network time', 'Status'],
            $this->repository->logs($id),
        );
    }

    /** @throws ValidationException */
    public function revert(int $id, int $approverId): string
    {
        $result = $this->repository->revert($id, $approverId);

        match ($result->status ?? null) {
            'OK'           => null,
            'NOT_FOUND'    => throw ValidationException::withMessages(['campaign' => 'This campaign is not suspended or its balance is already reverted.']),
            'NOTHING_HELD' => throw ValidationException::withMessages(['campaign' => 'Suspended Counts of this Campaign not valid.']),
            default        => throw new \RuntimeException('Revert failed.'),
        };

        $this->activity->log("Campaign Balance Reverse - Campaign ID {$id}: {$result->credits} credits ({$result->held} messages) returned to user id {$result->user_id}.");

        return number_format((int) $result->credits) . ' credits returned for ' . number_format((int) $result->held) . ' held messages.';
    }
}