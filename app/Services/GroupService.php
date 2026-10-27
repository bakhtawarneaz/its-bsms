<?php

namespace App\Services;

use App\Repositories\GroupRepository;
use App\Support\PhoneNumber;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GroupService
{
    public const PER_PAGE = 25;

    public function __construct(
        private readonly GroupRepository $repository,
        private readonly ActivityLogger $activity,
    ) {
    }

    public function page(int $userId, ?string $search, int $page): LengthAwarePaginator
    {
        [$total, $rows] = $this->repository->list($userId, $search, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        return $this->paginate($rows, $total, $page);
    }

    public function find(int $userId, int $id): object
    {
        return $this->repository->find($userId, $id) ?? throw new NotFoundHttpException('Group not found.');
    }

    /** @throws ValidationException */
    public function save(int $userId, ?int $id, string $name, ?string $description): void
    {
        $result = $this->repository->save($userId, $id, $name, $description);

        match ($result->status ?? null) {
            'OK'         => null,
            'NAME_TAKEN' => throw ValidationException::withMessages(['groupname' => 'Group name already exists.']),
            default      => throw new NotFoundHttpException('Group not found.'),
        };

        $this->activity->log($id
            ? "Edit Group - Group ({$name}) Id {$id} Edited Successfully."
            : "Add New Group - Group ({$name}) Added Successfully.");
    }

    /** @return array{name: string, contacts: int} */
    public function delete(int $userId, int $id): array
    {
        $result = $this->repository->delete($userId, $id);

        if (($result->status ?? null) !== 'OK') {
            throw new NotFoundHttpException('Group not found.');
        }

        $this->activity->log("Delete Group - Group ({$result->groupname}) Id {$id} Deleted Successfully with {$result->contacts_deleted} contact(s).");

        return ['name' => (string) $result->groupname, 'contacts' => (int) $result->contacts_deleted];
    }

    public function members(int $userId, int $groupId, ?string $search, int $page): LengthAwarePaginator
    {
        // A valid mobile is matched exactly on the number; anything else searches name / email.
        $number = $search && PhoneNumber::isValid($search) ? PhoneNumber::normalize($search) : null;

        [$total, $rows] = $this->repository->members(
            $userId, $groupId, $number ? null : $search, $number, ($page - 1) * self::PER_PAGE, self::PER_PAGE,
        );

        return $this->paginate($rows, $total, $page);
    }

    private function paginate(array $rows, int $total, int $page): LengthAwarePaginator
    {
        return new LengthAwarePaginator($rows, $total, self::PER_PAGE, $page, [
            'path' => request()->url(), 'query' => request()->query(),
        ]);
    }
}