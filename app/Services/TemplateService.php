<?php

namespace App\Services;

use App\Repositories\TemplateRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TemplateService
{
    public const PER_PAGE = 25;

    public function __construct(
        private readonly TemplateRepository $repository,
        private readonly ActivityLogger $activity,
    ) {
    }

    public function page(int $userId, ?string $search, int $page): LengthAwarePaginator
    {
        [$total, $rows] = $this->repository->list($userId, $search, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        return new LengthAwarePaginator($rows, $total, self::PER_PAGE, $page, [
            'path' => request()->url(), 'query' => request()->query(),
        ]);
    }

    /** @throws ValidationException */
    public function create(int $userId, string $name, string $message): void
    {
        $result = $this->repository->save($userId, null, trim($name), $message);

        if (($result->status ?? null) === 'NAME_TAKEN') {
            throw ValidationException::withMessages(['name' => 'Template name already exists.']);
        }

        $this->activity->log("Add New Template - Template ({$name}) Added Successfully.");
    }

    public function update(int $userId, int $id, string $message): void
    {
        $result = $this->repository->save($userId, $id, null, $message);

        if (($result->status ?? null) !== 'OK') {
            throw new NotFoundHttpException('Template not found.');
        }

        $this->activity->log("Edit Template - Template Id {$id} Updated Successfully.");
    }

    public function delete(int $userId, int $id): string
    {
        $result = $this->repository->delete($userId, $id);

        if (($result->status ?? null) !== 'OK') {
            throw new NotFoundHttpException('Template not found.');
        }

        $this->activity->log("Delete Template - Template ({$result->template_name}) Id {$id} Deleted Successfully.");

        return (string) $result->template_name;
    }

    public function otpPage(?int $userId, int $page): LengthAwarePaginator
    {
        [$total, $rows] = $this->repository->otpList($userId, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        return new LengthAwarePaginator($rows, $total, self::PER_PAGE, $page, [
            'path' => request()->url(), 'query' => request()->query(),
        ]);
    }

    /** @throws ValidationException */
    public function addOtp(array $payload): int
    {
        $result = $this->repository->addOtp($payload);

        match ($result->status ?? null) {
            'OK'             => null,
            'MASK_NOT_FOUND' => throw ValidationException::withMessages(['sender' => "Masking not exist. ({$payload['sender']})"]),
            'USER_NOT_FOUND' => throw ValidationException::withMessages(['user_id' => 'User account not found.']),
            default          => throw new \RuntimeException('OTP template could not be added.'),
        };

        $this->activity->log("Add OTP Template - OTP Template Added Successfully Temp ID {$result->id} Against User ID {$payload['user_id']}.");

        return (int) $result->temp_id;
    }

    /** @return array<int, string> id => username */
    public function users(): array
    {
        return Cache::remember('templates.users', now()->addMinutes(10), fn () => collect($this->repository->users())
            ->mapWithKeys(fn ($user) => [(int) $user->id => trim((string) $user->username)])
            ->all());
    }
}