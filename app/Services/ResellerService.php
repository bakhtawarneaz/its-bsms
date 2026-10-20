<?php

namespace App\Services;

use App\Repositories\ResellerRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResellerService
{
    public const PER_PAGE = 20;

    /** Public folder for reseller logos; the DB keeps the legacy "../../../usersimg/<file>" form. */
    public const LOGO_DIR = 'usersimg';

    public function __construct(
        private readonly ResellerRepository $repository,
        private readonly UserRepository $users,
        private readonly UserService $userService,
        private readonly ActivityLogger $activity,
    ) {}

    /** Paginated reseller directory with tab counts. */
    public function list(array $filters): array
    {
        $page = $filters['page'];
        ['rows' => $rows, 'counts' => $counts] = $this->repository->list($filters, $page, self::PER_PAGE);

        foreach ($rows as $row) {
            $row->logo_url = $this->logoUrl($row->favicon ?? null);
        }

        $counts['all'] = array_sum($counts);
        $total = $counts[$filters['state'] ?? 'all'] ?? 0;

        $resellers = new LengthAwarePaginator($rows, $total, self::PER_PAGE, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);

        return ['resellers' => $resellers->withQueryString(), 'counts' => $counts];
    }

    /** Maskings and customers, shared with the Add User form. */
    public function formOptions(): array
    {
        return $this->userService->formOptions();
    }

    /**
     * Create a reseller with maskings, opening credits and branding.
     *
     * @throws ValidationException
     */
    public function create(array $creator, array $input): int
    {
        $this->assertKnownOptions($input);

        $logoPath = $this->storeLogo($input['logo'], $input['username']);
        $isPrepaid = $input['account_type'] === 'prepaid';

        try {
            $resellerId = DB::transaction(function () use ($creator, $input, $logoPath, $isPrepaid) {
                $result = $this->repository->create([
                    'creator_id'    => (int) $creator['id'],
                    'username'      => $input['username'],
                    'password_hash' => md5(md5($input['password'])), // legacy hashing for compatibility
                    'fullname'      => $input['fullname'],
                    'email'         => $input['emails'],
                    'mobiles'       => $input['mobiles'],
                    'expiry'        => $input['expiry'],
                    'status'        => $input['status'] ? 1 : 0,
                    'customer_id'   => (int) $input['customer_id'],
                    'account_type'  => $input['account_type'],
                    'balance'       => $isPrepaid ? (int) $input['balance'] : 0,
                    'company_name'  => trim($input['company_name']),
                    'domain'        => $input['domain'],
                    'slogan'        => trim($input['slogan']),
                    'logo_path'     => $logoPath,
                    'first_color'   => strtolower($input['first_color']),
                    'second_color'  => strtolower($input['second_color']),
                    'third_color'   => strtolower($input['third_color']),
                    'fourth_color'  => strtolower($input['fourth_color']),
                    'dark_theme'    => $input['dark_theme'] ? 1 : 0,
                ]);

                match ($result->status ?? null) {
                    'USERNAME_TAKEN'       => throw ValidationException::withMessages(['username' => 'This username is already taken.']),
                    'DOMAIN_TAKEN'         => throw ValidationException::withMessages(['domain' => 'This domain already belongs to another reseller.']),
                    'INSUFFICIENT_BALANCE' => throw ValidationException::withMessages(['balance' => "You don't have sufficient balance. Please recharge your account."]),
                    'OK'                   => null,
                    default                => throw new \RuntimeException('Reseller could not be created.'),
                };

                foreach ($input['maskings'] as $maskingId) {
                    $this->users->addMasking((int) $result->id, (int) $maskingId, (int) $creator['id']);
                }

                return (int) $result->id;
            });
        } catch (\Throwable $e) {
            // Nothing was saved, so the uploaded logo is not needed.
            File::delete(public_path(self::LOGO_DIR . '/' . basename($logoPath)));

            throw $e;
        }

        // Resellers appear in the "admin for this user" list of the Add User form.
        Cache::forget('users.form_options');

        $this->activity->log('Add New Reseller -  Reseller Add Successfully.');

        return $resellerId;
    }

    /**
     * Public URL of a logo stored as "../../../usersimg/<file>" (legacy) or a bare file name.
     */
    public function logoUrl(?string $favicon): ?string
    {
        $file = basename(trim((string) $favicon));

        if ($file === '' || ! File::exists(public_path(self::LOGO_DIR . '/' . $file))) {
            return null;
        }

        return asset(self::LOGO_DIR . '/' . rawurlencode($file));
    }

    /** Save the PNG logo as "<username>_<time>.png" (legacy naming) and return the DB path. */
    private function storeLogo(UploadedFile $logo, string $username): string
    {
        File::ensureDirectoryExists(public_path(self::LOGO_DIR));

        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $username) . '_' . time() . '.png';
        $logo->move(public_path(self::LOGO_DIR), $name);

        return '../../../' . self::LOGO_DIR . '/' . $name;
    }

    /**
     * Reject ids that were not offered in the form (tampered requests).
     */
    private function assertKnownOptions(array $input): void
    {
        $options = $this->formOptions();
        $ids = fn (string $key) => array_column($options[$key], 'value');
        $errors = [];

        if (array_diff(array_map('intval', $input['maskings']), $ids('maskings'))) {
            $errors['maskings'] = 'One or more selected maskings are not available.';
        }

        if (! in_array((int) $input['customer_id'], $ids('customers'), true)) {
            $errors['customer_id'] = 'Select a master customer from the list.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

        /**
     * Reseller with branding values ready for the form (legacy default colors when blank).
     */
    public function editForm(int $id): array
    {
        $record = $this->repository->find($id) ?? throw new NotFoundHttpException('Reseller not found.');
        $template = $record['template'];
        $color = fn (?string $value, string $default) => preg_match('/^#[0-9a-fA-F]{6}$/', trim((string) $value)) ? strtolower(trim($value)) : $default;

        return [
            'reseller' => $record['reseller'],
            'branding' => [
                'company_name' => trim((string) ($template->name ?? '')),
                'slogan'       => trim((string) ($template->slogan ?? '')),
                'logo_url'     => $this->logoUrl($template->favicon ?? null),
                'dark_theme'   => (int) ($template->dark_theme ?? 0) === 1,
                'colors'       => [
                    'first'  => $color($template->first_color ?? null, '#47aee0'),
                    'second' => $color($template->second_color ?? null, '#ffffff'),
                    'third'  => $color($template->third_color ?? null, '#47aee0'),
                    'fourth' => $color($template->fourth_color ?? null, '#ffffff'),
                ],
                'missing'      => $template === null,
            ],
        ];
    }

    /**
     * Save branding. A new logo replaces the old one only when uploaded.
     *
     * @throws ValidationException
     */
    public function updateBranding(int $id, array $input): string
    {
        $reseller = $this->editForm($id)['reseller'];
        $logoPath = isset($input['logo']) && $input['logo'] instanceof UploadedFile
            ? $this->storeLogo($input['logo'], $reseller->username)
            : null;

        try {
            $result = DB::transaction(fn () => $this->repository->updateBranding([
                'user_id'      => $id,
                'company_name' => trim($input['company_name']),
                'slogan'       => trim($input['slogan']),
                'logo_path'    => $logoPath,
                'first_color'  => strtolower($input['first_color']),
                'second_color' => strtolower($input['second_color']),
                'third_color'  => strtolower($input['third_color']),
                'fourth_color' => strtolower($input['fourth_color']),
                'dark_theme'   => $input['dark_theme'] ? 1 : 0,
            ]));

            match ($result->status ?? null) {
                'OK'        => null,
                'NOT_FOUND' => throw new NotFoundHttpException('Reseller not found.'),
                'NO_DOMAIN' => throw ValidationException::withMessages(['company_name' => 'This reseller has no domain, so branding cannot be saved.']),
                default     => throw new \RuntimeException('Branding could not be saved.'),
            };
        } catch (\Throwable $e) {
            if ($logoPath) {
                File::delete(public_path(self::LOGO_DIR . '/' . basename($logoPath)));
            }

            throw $e;
        }

        $this->activity->log("Edit Key User -  Reseller Edit Successfully. Reseller = {$result->username}");

        return (string) $result->username;
    }
}