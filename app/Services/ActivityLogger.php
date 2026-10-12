<?php

namespace App\Services;

use App\Repositories\ActivityLogRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActivityLogger
{
    public function __construct(
        private readonly ActivityLogRepository $repository,
        private readonly Request $request,
    ) {}

    /**
     * Record a user action in the activity_log table.
     * Failures are logged but never interrupt the user's request.
     */
    public function log(string $activity, ?int $userId = null): void
    {
        $userId ??= (int) ($this->request->session()->get('auth_user.id') ?? 0);

        try {
            $this->repository->insert($userId, $activity, (string) $this->request->ip());
        } catch (Throwable $e) {
            Log::error('Activity log write failed', [
                'activity' => $activity,
                'user_id'  => $userId,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}