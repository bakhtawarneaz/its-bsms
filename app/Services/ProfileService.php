<?php

namespace App\Services;

use App\Mail\AccountChangedMail;
use App\Repositories\ProfileRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProfileService
{
    public function __construct(
        private readonly ProfileRepository $repository,
        private readonly ActivityLogger $activity,
    ) {}

    public function find(int $userId): ?object
    {
        return $this->repository->find($userId);
    }
    
    public function recentActivity(int $userId): array
    {
        return $this->repository->recentActivity($userId);
    }

    /**
     * Save contact details, refresh the session copy and notify the owner.
     */
    public function updateDetails(array $user, string $fullname, array $mobiles, array $emails, ?int $lowBalanceLimit): void
    {
        $mobileList = implode(',', $mobiles);
        $emailList = implode(',', $emails);

        $previous = $this->repository->update((int) $user['id'], $fullname, $mobileList, $emailList, $lowBalanceLimit);

        session()->put('auth_user.fullname', $fullname);
        session()->put('auth_user.user_mobile', $mobileList);
        session()->put('auth_user.email', $emailList);

        $this->activity->log('Profile updated');

        $changes = array_filter([
            'Business name' => [$previous->fullname ?? '', $fullname],
            'Mobile numbers' => [$previous->user_mobile ?? '', $mobileList],
            'Emails' => [$previous->email ?? '', $emailList],
        ], fn ($pair) => $pair[0] !== $pair[1]);

        if ($changes) {
            // Old and new addresses both hear about it, in case the change was not made by the owner
            $this->notify($user, $changes, false, [$previous->email ?? '', $emailList]);
        }
    }

    /**
     * Change the password after verifying the current one.
     *
     * @throws ValidationException
     */
    public function changePassword(array $user, string $current, string $new): void
    {
        $result = $this->repository->changePassword((int) $user['id'], $this->hash($current), $this->hash($new));

        match ($result) {
            'WRONG_PASSWORD' => throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']),
            'SAME_PASSWORD'  => throw ValidationException::withMessages(['password' => 'Choose a password you have not used for this account.']),
            default          => null,
        };

        $this->activity->log('Password changed');
        $this->notify($user, [], true, [$user['email']]);
    }

    /**
     * Legacy hashing kept for compatibility with existing password data.
     */
    private function hash(string $password): string
    {
        return md5(md5($password));
    }

    private function notify(array $user, array $changes, bool $passwordChanged, array $emailLists): void
    {
        $recipients = collect($emailLists)
            ->flatMap(fn ($list) => explode(',', (string) $list))
            ->map(fn ($email) => strtolower(trim($email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();

        if (! $recipients) {
            return;
        }

        try {
            Mail::to($recipients)->send(new AccountChangedMail(
                $user['username'],
                $changes,
                $passwordChanged,
                now()->format('j M Y, g:i A'),
            ));
        } catch (Throwable $e) {
            Log::error('Account change email failed', ['user_id' => $user['id'], 'error' => $e->getMessage()]);
        }
    }
}