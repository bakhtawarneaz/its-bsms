<?php

namespace App\Services;

use App\Repositories\UserRepository;
use DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserService
{
    public const TYPES_WITH_ADMIN = ['user', 'campreporting', 'lcreporting', 'reporting'];

    public const PER_PAGE = 20;

    public function __construct(
        private readonly UserRepository $repository,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Cached for five minutes: the customer list comes from the ERP and rarely changes.
     */
    public function formOptions(): array
    {
        return Cache::remember('users.form_options', 300, fn () => $this->repository->formOptions());
    }

    /**
     * Form options for editing: keeps the user's current admin / reporting user selectable
     * even when that account is no longer active.
     */
    public function editOptions(object $user): array
    {
        $options = $this->formOptions();

        $ensure = function (array $list, $id, ?string $label): array {
            if ($id && $label && ! in_array((int) $id, array_column($list, 'value'), true)) {
                $list[] = ['value' => (int) $id, 'label' => trim($label) . ' (inactive)'];
            }

            return $list;
        };

        $options['admins'] = $ensure($options['admins'], $user->reseller_id, $user->admin_username);
        $options['reportUsers'] = $ensure($options['reportUsers'], $user->reporting_user_id, $user->reporting_username);

        return $options;
    }

    /** Paginated user directory with tab counts. */
    public function list(array $filters): array
    {
        $page = $filters['page'];
        ['rows' => $rows, 'counts' => $counts] = $this->repository->list($filters, $page, self::PER_PAGE);

        $counts['all'] = array_sum($counts);
        $total = $counts[$filters['state'] ?? 'all'] ?? 0;

        $users = new LengthAwarePaginator($rows, $total, self::PER_PAGE, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);

        return ['users' => $users->withQueryString(), 'counts' => $counts];
    }

    /**
     * @return array{user: object, maskings: array<int, int>}
     */
    public function find(int $id): array
    {
        return $this->repository->find($id) ?? throw new NotFoundHttpException('User not found.');
    }

    /**
     * Create a user with maskings and opening balance in one transaction.
     *
     * @throws ValidationException
     */
    public function create(array $creator, array $input): int
    {
        $this->assertKnownOptions($input);

        $needsAdmin = in_array($input['user_type'], self::TYPES_WITH_ADMIN, true);
        $isPrepaid = $input['account_type'] === 'prepaid';

        $userId = DB::transaction(function () use ($creator, $input, $needsAdmin, $isPrepaid) {
            $result = $this->repository->create([
                'creator_id'        => (int) $creator['id'],
                'username'          => $input['username'],
                'password_hash'     => md5(md5($input['password'])), // legacy hashing for compatibility
                'fullname'          => $input['fullname'],
                'email'             => $input['emails'],
                'mobiles'           => $input['mobiles'],
                'expiry'            => $input['expiry'],
                'status'            => $input['status'] ? 1 : 0,
                'customer_id'       => (int) $input['customer_id'],
                'account_type'      => $input['account_type'],
                'balance'           => $isPrepaid ? (int) $input['balance'] : 0,
                'user_type'         => $input['user_type'],
                'reseller_id'       => $needsAdmin ? (int) $input['reseller_id'] : null,
                'reporting_user_id' => $input['user_type'] === 'reporting' ? (int) $input['reporting_user_id'] : null,
                'forward_status'    => $input['dlr_forward'] ? 1 : 0,
                'calling_api'       => $input['dlr_forward'] ? $input['calling_api'] : null,
            ]);

            match ($result->status ?? null) {
                'USERNAME_TAKEN'       => throw ValidationException::withMessages(['username' => 'This username is already taken.']),
                'INSUFFICIENT_BALANCE' => throw ValidationException::withMessages(['balance' => 'Your balance is not enough to give this user that many credits.']),
                'OK'                   => null,
                default                => throw new \RuntimeException('User could not be created.'),
            };

            foreach ($input['maskings'] as $maskingId) {
                $this->repository->addMasking((int) $result->id, (int) $maskingId, (int) $creator['id']);
            }

            return (int) $result->id;
        });

        $this->activity->log("Add New User - User Added Successfully. User Id = {$userId}");

        return $userId;
    }

    /**
     * Update profile, access and maskings. Billing fields are not changed here.
     *
     * @throws ValidationException
     */
    public function update(array $editor, int $id, array $input): void
    {
        $current = $this->find($id);

        $this->assertKnownOptions($input, $current);

        if ($input['user_type'] === 'reporting' && (int) $input['reporting_user_id'] === $id) {
            throw ValidationException::withMessages(['reporting_user_id' => 'A user cannot report on itself.']);
        }

        $needsAdmin = in_array($input['user_type'], self::TYPES_WITH_ADMIN, true);

        DB::transaction(function () use ($editor, $id, $input, $current, $needsAdmin) {
            $result = $this->repository->update([
                'user_id'           => $id,
                'fullname'          => $input['fullname'],
                'email'             => $input['emails'],
                'mobiles'           => $input['mobiles'],
                'expiry'            => $input['expiry'],
                'status'            => $input['status'] ? 1 : 0,
                'customer_id'       => (int) $input['customer_id'],
                'user_type'         => $input['user_type'],
                'reseller_id'       => $needsAdmin ? (int) $input['reseller_id'] : null,
                'reporting_user_id' => $input['user_type'] === 'reporting' ? (int) $input['reporting_user_id'] : null,
                'password_hash'     => filled($input['password'] ?? null) ? md5(md5($input['password'])) : null,
                'forward_status'    => $input['dlr_forward'] ? 1 : 0,
                'calling_api'       => $input['dlr_forward'] ? $input['calling_api'] : null,
            ]);

            match ($result->status ?? null) {
                'HAS_USERS' => throw ValidationException::withMessages(['user_type' => 'This admin still has users. Move them before changing the type.']),
                'NOT_FOUND' => throw new NotFoundHttpException('User not found.'),
                'OK'        => null,
                default     => throw new \RuntimeException('User could not be updated.'),
            };

            // Sync maskings: add the new ones, remove the deselected ones.
            $selected = array_map('intval', $input['maskings']);

            foreach (array_diff($selected, $current['maskings']) as $maskingId) {
                $this->repository->addMasking($id, $maskingId, (int) $editor['id']);
            }

            foreach (array_diff($current['maskings'], $selected) as $maskingId) {
                $this->repository->removeMasking($id, $maskingId);
            }
        });

        $passwordNote = filled($input['password'] ?? null) ? ' Password changed.' : '';
        $this->activity->log("Edit User - User Updated Successfully. User Id = {$id}.{$passwordNote}");
    }

    /**
     * Turn sign-in on or off. Returns the username.
     */
    public function setStatus(int $id, bool $active): string
    {
        $result = $this->repository->setStatus($id, $active);

        if ((int) ($result->affected ?? 0) === 0) {
            throw new NotFoundHttpException('User not found.');
        }

        $this->activity->log('User Status - User Id = ' . $id . ' set to ' . ($active ? 'Active' : 'Inactive'));

        return (string) $result->username;
    }

    /**
     * Clear the failed-login lock. Returns the username.
     */
    public function unblock(int $id): string
    {
        $result = $this->repository->unblock($id);

        if ((int) ($result->affected ?? 0) === 0) {
            throw new NotFoundHttpException('User not found.');
        }

        $this->activity->log("Unblock User - User Id = {$id} unblocked");

        return (string) $result->username;
    }

    /**
     * Hard delete when the account has no dependants or history. Returns the username.
     *
     * @throws DomainException with a user-facing reason when deletion is not allowed
     */
    public function delete(int $id): string
    {
        $result = DB::transaction(fn () => $this->repository->delete($id));
        $name = (string) ($result->username ?? '');

        switch ($result->status ?? null) {
            case 'OK':
                $this->activity->log("Delete User - User Id = {$id} ({$name}) deleted");

                return $name;
            case 'NOT_FOUND':
                throw new NotFoundHttpException('User not found.');
            case 'HAS_USERS':
                throw new DomainException("{$name} has users linked to it. Move or delete them first.");
            case 'HAS_CREDITS':
                throw new DomainException("{$name} still has " . number_format((int) $result->detail) . ' credits. Disable the account instead.');
            case 'HAS_HISTORY':
                Log::channel('system')->info('User delete blocked by history', ['user_id' => $id, 'table' => $result->detail]);
                throw new DomainException("{$name} has sending or billing history, so it can't be deleted. Disable it instead.");
            default:
                throw new \RuntimeException('User could not be deleted.');
        }
    }

    /**
     * Reject ids that were not offered in the form (tampered requests).
     * When editing, values that are unchanged from the current record are always allowed.
     */
    private function assertKnownOptions(array $input, ?array $current = null): void
    {
        $options = $this->formOptions();
        $ids = fn (string $key) => array_column($options[$key], 'value');
        $user = $current['user'] ?? null;
        $unchanged = fn (string $field, string $column) => $user && (int) $input[$field] === (int) $user->{$column};

        $errors = [];

        $allowedMaskings = array_merge($ids('maskings'), $current['maskings'] ?? []);
        if (array_diff(array_map('intval', $input['maskings']), $allowedMaskings)) {
            $errors['maskings'] = 'One or more selected maskings are not available.';
        }

        if (! $unchanged('customer_id', 'customer_id') && ! in_array((int) $input['customer_id'], $ids('customers'), true)) {
            $errors['customer_id'] = 'Select a master customer from the list.';
        }

        if (in_array($input['user_type'], self::TYPES_WITH_ADMIN, true)
            && ! $unchanged('reseller_id', 'reseller_id')
            && ! in_array((int) $input['reseller_id'], $ids('admins'), true)) {
            $errors['reseller_id'] = 'Select an admin from the list.';
        }

        if ($input['user_type'] === 'reporting'
            && ! $unchanged('reporting_user_id', 'reporting_user_id')
            && ! in_array((int) $input['reporting_user_id'], $ids('reportUsers'), true)) {
            $errors['reporting_user_id'] = 'Select a user from the list.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

        /**
     * Add or deduct prepaid credits. Returns the SP result (username, new balance).
     *
     * @throws ValidationException
     */
    public function adjustBalance(array $editor, int $id, string $action, int $amount): object
    {
        $result = DB::transaction(fn () => $this->repository->adjustBalance((int) $editor['id'], $id, $action, $amount));
        $name = (string) ($result->username ?? '');

        switch ($result->status ?? null) {
            case 'OK':
                $this->activity->log($action === 'add'
                    ? "Edit User - Balance ({$amount}) added successfully to user account ( {$name} )."
                    : "Edit User - Balance ({$amount}) deducted successfully from user account ( {$name} ).");

                return $result;
            case 'NOT_FOUND':
                throw new NotFoundHttpException('User not found.');
            case 'NOT_PREPAID':
                throw ValidationException::withMessages(['amount' => "{$name} is postpaid, so it has no credit balance."]);
            case 'INSUFFICIENT_BALANCE':
                throw ValidationException::withMessages(['amount' => "You don't have enough credits. Recharge your account first."]);
            case 'USER_INSUFFICIENT':
                throw ValidationException::withMessages(['amount' => "{$name} only has " . number_format((int) $result->balance) . ' credits to deduct.']);
            default:
                throw new \RuntimeException('Balance could not be updated.');
        }
    }

    /** Latest credit movements for one user. */
    public function balanceHistory(int $id, int $limit = 10): array
    {
        return $this->repository->balanceHistory($id, $limit);
    }
}