<?php

namespace App\Services;

use App\Repositories\GatewayUserRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GatewayUserService
{
    public const PER_PAGE = 20;

    /** SCOM route id meaning "blocked" in the gateway. */
    public const BLOCK_ROUTE = 99;

    /**
     * Gateway networks in legacy form order. "group" is RouteInfo.Network; "default" is the
     * legacy pre-selected route [route id, operator name].
     */
    public const NETWORKS = [
        'jazz'    => ['label' => 'Mobilink / Jazz', 'code' => 9230, 'group' => '1', 'default' => [1101, 'HTTPMobilink'], 'dot' => 'bg-rose-500'],
        'ufone'   => ['label' => 'Ufone',           'code' => 9233, 'group' => '3', 'default' => [3102, 'HTTPUFONEB'],   'dot' => 'bg-orange-500'],
        'zong'    => ['label' => 'Zong',            'code' => 9231, 'group' => '4', 'default' => [4102, 'HTTPZONGC'],    'dot' => 'bg-emerald-500'],
        'telenor' => ['label' => 'Telenor',         'code' => 9234, 'group' => '6', 'default' => [6101, 'HTTPTELENORA'], 'dot' => 'bg-sky-500'],
        'warid'   => ['label' => 'Warid',           'code' => 9232, 'group' => '7', 'default' => [1101, 'HTTPMOBILINK'], 'dot' => 'bg-pink-500'],
        'scom'    => ['label' => 'SCOM',            'code' => 9235, 'group' => '5', 'default' => [99, 'block'],          'dot' => 'bg-violet-500'],
    ];

        /** Pre-selected routes in the legacy "Add Masking" modal (Jazz uses upper-case HTTPMOBILINK there). */
    public const MASK_DEFAULTS = [
        'jazz'    => [1101, 'HTTPMOBILINK'],
        'ufone'   => [3102, 'HTTPUFONEB'],
        'zong'    => [4102, 'HTTPZONGC'],
        'telenor' => [6101, 'HTTPTELENORA'],
        'warid'   => [1101, 'HTTPMOBILINK'],
        'scom'    => [99, 'block'],
    ];

        /** Networks on the mask-routes page; "group" is RouteInfo.Network (Warid reuses the Jazz list, legacy). */
    private const MASK_ROUTE_NETWORKS = [
        'jazz'    => ['label' => 'Jazz',    'network' => 9230, 'group' => 1, 'extras' => true],
        'warid'   => ['label' => 'Warid',   'network' => 9232, 'group' => 1, 'extras' => true],
        'ufone'   => ['label' => 'Ufone',   'network' => 9233, 'group' => 3, 'extras' => true],
        'zong'    => ['label' => 'Zong',    'network' => 9231, 'group' => 4, 'extras' => true],
        'telenor' => ['label' => 'Telenor', 'network' => 9234, 'group' => 6, 'extras' => true],
        'scom'    => ['label' => 'SCOM',    'network' => 9235, 'group' => 5, 'extras' => false],
    ];

    public function __construct(
        private readonly GatewayUserRepository $repository,
        private readonly UserService $users,
        private readonly ActivityLogger $activity,
    ) {}

    /** Paginated gateway user directory with tab counts. */
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
     * Routes per network, resellers and customers for the gateway user form.
     * Each network list starts with the legacy default route, then Block, then RouteInfo routes.
     */
    public function formOptions(): array
    {
        return Cache::remember('gateway.form_options', 300, function () {
            ['routes' => $routes, 'resellers' => $resellers] = $this->repository->formOptions();

            $options = [];
            foreach (self::NETWORKS as $key => $network) {
                [$defaultRoute, $defaultOperator] = $network['default'];

                $list = [$defaultRoute => ['value' => $defaultRoute, 'operator' => $defaultOperator, 'label' => $defaultRoute === self::BLOCK_ROUTE ? 'Default route (Block)' : 'Default route']];
                $list += [self::BLOCK_ROUTE => ['value' => self::BLOCK_ROUTE, 'operator' => 'block', 'label' => 'Block']];

                foreach ($routes as $row) {
                    if (trim((string) $row->network) === $network['group'] && ! isset($list[(int) $row->route])) {
                        $name = trim((string) $row->route_name);
                        $list[(int) $row->route] = ['value' => (int) $row->route, 'operator' => $name, 'label' => $name];
                    }
                }

                $options[$key] = array_values($list);
            }

            // Legacy form always offered "Test" (1) besides the active gateway resellers.
            $resellerOptions = [['value' => 1, 'label' => 'Test']];
            foreach ($resellers as $row) {
                $resellerOptions[] = ['value' => (int) $row->value, 'label' => trim((string) $row->label)];
            }

            // Legacy form always offered "ITS" (1) before the ERP customers.
            $customers = array_merge([['value' => 1, 'label' => 'ITS']], $this->users->formOptions()['customers']);

            return ['routes' => $options, 'resellers' => $resellerOptions, 'customers' => $customers];
        });
    }

    /**
     * Create a gateway user with routes for its default mask.
     *
     * @throws ValidationException
     */
    public function create(array $input): int
    {
        $options = $this->formOptions();
        $routes = $this->resolveRoutes($input['routes'], $options['routes']);

        // Legacy rule: SCOM can only carry numeric (short code) masks.
        if ($routes['scom_route'] !== self::BLOCK_ROUTE && ! ctype_digit($input['default_mask'])) {
            throw ValidationException::withMessages(['routes.scom' => 'SCOM operator is not allowed against a brand name mask.']);
        }

        if (! in_array((int) $input['reseller_id'], array_column($options['resellers'], 'value'), true)) {
            throw ValidationException::withMessages(['reseller_id' => 'Select a reseller from the list.']);
        }

        if (! in_array((int) $input['customer_id'], array_column($options['customers'], 'value'), true)) {
            throw ValidationException::withMessages(['customer_id' => 'Select a master customer from the list.']);
        }

        $isPrepaid = $input['account_type'] === 'prepaid';

        $result = DB::transaction(fn () => $this->repository->create([
            'username'       => $input['username'],
            'password'       => $input['password'],
            'web_username'   => $input['web_username'],
            'web_password'   => $input['web_password'],
            'fullname'       => $input['fullname'],
            'email'          => $input['emails'],
            'mobile'         => $input['mobiles'],
            'expiry'         => $input['expiry'] . ' 23:59:59',
            'authorized_ips' => $input['ips'],
            'default_mask'   => $input['default_mask'],
            'account_type'   => $isPrepaid ? 'PrePaid' : 'Postpaid',
            'balance'        => $isPrepaid ? (int) ($input['balance'] ?? 0) : 0,
            'reseller_id'    => (int) $input['reseller_id'],
            'user_type'      => $input['user_type'],
            'customer_id'    => (int) $input['customer_id'],
            'status'         => $input['status'] ? 1 : 0,
        ] + $routes));

        match ($result->status ?? null) {
            'USERNAME_TAKEN'     => throw ValidationException::withMessages(['username' => 'This username already exists in the gateway.']),
            'WEB_USERNAME_TAKEN' => throw ValidationException::withMessages(['web_username' => 'This web username already exists in the gateway.']),
            'OK'                 => null,
            default              => throw new \RuntimeException('Gateway user could not be created.'),
        };

        $this->activity->log("Add New Gateway User - User Added Successfully. Username ( {$input['username']} )");

        return (int) $result->id;
    }

    /**
     * Map chosen route ids to SP parameters with operator names from the server-side options.
     *
     * @throws ValidationException
     */
    private function resolveRoutes(array $routes, array $options): array
    {
        $params = [];

        foreach (array_keys(self::NETWORKS) as $network) {
            $match = collect($options[$network])->firstWhere('value', (int) $routes[$network]);

            if (! $match) {
                throw ValidationException::withMessages(["routes.{$network}" => 'Choose a route from the list.']);
            }

            $params["{$network}_route"] = $match['value'];
            $params["{$network}_operator"] = $match['operator'];
        }

        return $params;
    }

        /**
     * Gateway user with its masks grouped as [mask => [network code => route row]].
     */
    public function editForm(int $id): array
    {
        $record = $this->repository->find($id) ?? throw new NotFoundHttpException('Gateway user not found.');

        $masks = [];
        foreach ($record['routes'] as $route) {
            $masks[trim((string) $route->mask)][(int) $route->network] = [
                'route'    => (int) $route->route,
                'operator' => trim((string) $route->operator),
                'status'   => (int) $route->status,
            ];
        }

        ksort($masks, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'user'      => $record['user'],
            'masks'     => $masks,
            'twoFactor' => $this->repository->twoFactorContacts($id),
        ];
    }

    /**
     * Update contact, access and passwords (legacy "Edit Gateway User").
     */
    public function update(int $id, array $input): void
    {
        $result = DB::transaction(fn () => $this->repository->update([
            'user_id'        => $id,
            'fullname'       => $input['fullname'],
            'email'          => $input['emails'],
            'mobile'         => $input['mobiles'],
            'expiry'         => $input['expiry'] . ' 23:59:59',
            'authorized_ips' => $input['ips'],
            'status'         => $input['status'] ? 1 : 0,
            'password'       => filled($input['password'] ?? null) ? $input['password'] : null,
            'web_password'   => filled($input['web_password'] ?? null) ? $input['web_password'] : null,
        ]));

        if (($result->status ?? null) !== 'OK') {
            throw new NotFoundHttpException('Gateway user not found.');
        }

        $this->activity->log("Edit Gateway User - User edited Successfully. User Id( {$id} )");
    }

    /**
     * Add or deduct prepaid credits.
     *
     * @throws ValidationException
     */
    public function adjustBalance(array $editor, int $id, string $action, int $amount): object
    {
        $result = DB::transaction(fn () => $this->repository->adjustBalance($id, $action, $amount));
        $name = (string) ($result->username ?? '');

        switch ($result->status ?? null) {
            case 'OK':
                $this->activity->log($action === 'add'
                    ? "Edit Gateway User - Added balance amount {$amount} to ITS Gateway user id {$id} by key account user {$editor['username']}. "
                    : "Edit Gateway User - Deducted balance amount {$amount} from ITS Gateway user id {$id} by key account user {$editor['username']}. ");

                return $result;
            case 'NOT_FOUND':
                throw new NotFoundHttpException('Gateway user not found.');
            case 'NOT_PREPAID':
                throw ValidationException::withMessages(['amount' => "{$name} is postpaid, so it has no credit balance."]);
            case 'USER_INSUFFICIENT':
                throw ValidationException::withMessages(['amount' => "{$name} only has " . number_format((int) $result->balance) . ' credits to deduct.']);
            default:
                throw new \RuntimeException('Balance could not be updated.');
        }
    }
    
        /**
     * Route choices for adding masks: the modal's default first, then Block, the network's
     * RouteInfo routes, and the other networks' default routes as optional extras (legacy modal).
     */
    public function maskRouteOptions(): array
    {
        $create = $this->formOptions()['routes'];
        $crossDefaults = [[1101, 'HTTPMOBILINK'], [3102, 'HTTPUFONEB'], [4102, 'HTTPZONGC'], [6101, 'HTTPTELENORA']];
        $options = [];

        foreach (self::MASK_DEFAULTS as $key => [$route, $operator]) {
            $list = [$route => ['value' => $route, 'operator' => $operator, 'label' => $route === self::BLOCK_ROUTE ? 'Default route (Block)' : 'Default route']];
            $list += [self::BLOCK_ROUTE => ['value' => self::BLOCK_ROUTE, 'operator' => 'block', 'label' => 'Block']];

            foreach ($create[$key] as $option) {
                $list += [$option['value'] => $option];
            }

            foreach ($crossDefaults as [$extraRoute, $extraOperator]) {
                $list += [$extraRoute => ['value' => $extraRoute, 'operator' => $extraOperator, 'label' => "{$extraOperator} (optional)"]];
            }

            $options[$key] = array_values($list);
        }

        return $options;
    }

    /**
     * Add masks with the same routes; masks the user already has are skipped.
     *
     * @return array{added: array<int, string>, skipped: array<int, string>}
     *
     * @throws ValidationException
     */
    public function addMasks(int $id, array $input): array
    {
        $routes = $this->resolveRoutes($input['routes'], $this->maskRouteOptions());
        $added = [];
        $skipped = [];

        DB::transaction(function () use ($id, $input, $routes, &$added, &$skipped) {
            foreach ($input['masks'] as $mask) {
                $result = $this->repository->addMask(['user_id' => $id, 'mask' => $mask] + $routes);

                match ($result->status ?? null) {
                    'OK'        => $added[] = $mask,
                    'EXISTS'    => $skipped[] = $mask,
                    'NOT_FOUND' => throw new NotFoundHttpException('Gateway user not found.'),
                    default     => throw new \RuntimeException('Mask could not be added.'),
                };
            }
        });

        if (! $added) {
            throw ValidationException::withMessages(['masks' => 'Mask(s) already exist: ' . implode(', ', $skipped) . '.']);
        }

        $this->activity->log("Edit Gateway User -  User Masking(s) Added Successfully in Gateway User Id = .{$id}");

        return ['added' => $added, 'skipped' => $skipped];
    }

    /**
     * Remove masks (all network routes) from a gateway user.
     *
     * @throws ValidationException
     */
    public function deleteMasks(int $id, array $masks): int
    {
        $deleted = DB::transaction(function () use ($id, $masks) {
            $count = 0;
            foreach (array_unique($masks) as $mask) {
                $count += $this->repository->deleteMask($id, trim($mask)) > 0 ? 1 : 0;
            }

            return $count;
        });

        if ($deleted === 0) {
            throw ValidationException::withMessages(['masks' => 'User maskings not deleted.']);
        }

        $this->activity->log("Delete Gateway User Maskings -  User Maskings Deleted Successfully. User Id = {$id} (" . implode(', ', $masks) . ')');

        return $deleted;
    }
    
        /**
     * Register two-factor contacts; a mobile already registered for this user is rejected.
     *
     * @return array{added: int, rejected: array<int, string>}
     *
     * @throws ValidationException
     */
    public function addTwoFactor(array $editor, int $id, array $contacts): array
    {
        $added = 0;
        $rejected = [];

        DB::transaction(function () use ($editor, $id, $contacts, &$added, &$rejected) {
            foreach ($contacts as $contact) {
                $result = $this->repository->addTwoFactor($id, $contact, (int) $editor['id']);

                match ($result->status ?? null) {
                    'OK'        => $added++,
                    'EXISTS'    => $rejected[] = $contact['mobile'],
                    'NOT_FOUND' => throw new NotFoundHttpException('Gateway user not found.'),
                    default     => throw new \RuntimeException('Two-factor contact could not be added.'),
                };
            }
        });

        if ($added === 0) {
            throw ValidationException::withMessages(['contacts' => 'User already exists: ' . implode(', ', $rejected) . '.']);
        }

        $this->activity->log("Add 2FA User - {$added} Two FA Added. Gateway User Id = {$id}" . ($rejected ? '. Rejected: ' . implode(', ', $rejected) : ''));

        return ['added' => $added, 'rejected' => $rejected];
    }

        /**
     * Data for the mask-routes page: every mask, the users of the chosen mask,
     * and the per-network routes of the chosen user.
     */
    public function maskRoutesPage(?string $mask, ?int $userId): array
    {
        $users = $mask ? $this->repository->maskRouteUsers($mask) : [];
        $selected = $userId ? collect($users)->firstWhere('user_id', $userId) : null;

        if (! $selected && count($users) === 1) {
            $selected = $users[0];
        }

        $networks = [];
        $promotional = false;

        if ($selected) {
            [$rows, $catalog] = $this->repository->maskRoutes($mask, (int) $selected->user_id);

            $promotional = isset($rows[0]) && (int) $rows[0]->Promotional === 1;
            $networks = $this->buildMaskRouteNetworks($rows, $catalog);
        }

        return [
            'masks'       => $this->repository->maskRouteMasks(),
            'mask'        => $mask,
            'maskKey'     => $mask !== null ? bin2hex($mask) : null,
            'users'       => $users,
            'selected'    => $selected,
            'networks'    => $networks,
            'promotional' => $promotional,
        ];
    }

    /**
     * @throws ValidationException
     */
    public function updateMaskRoutes(string $mask, int $userId, bool $promotional, array $routes): void
    {
        DB::transaction(function () use ($mask, $userId, $promotional, $routes) {
            foreach ($routes as $row) {
                $result = $this->repository->updateMaskRoute($userId, $mask, $promotional, $row);

                if (($result->status ?? null) !== 'OK') {
                    throw ValidationException::withMessages(['routes' => 'These routes changed in the meantime. Reload the page and try again.']);
                }
            }
        });

        $this->activity->log("Edit Gateway Masking Routes - Gateway Masking ({$mask}) Routes Edited Successfully against UserId ({$userId}).");
    }

    private function buildMaskRouteNetworks(array $rows, array $catalog): array
    {
        $byNetwork = [];

        foreach ($rows as $row) {
            $byNetwork[(int) $row->Network] = $row; // the latest row wins, as in the legacy screen
        }

        $catalog = collect($catalog)->groupBy(fn ($route) => (int) $route->Network);
        $networks = [];

        foreach (self::MASK_ROUTE_NETWORKS as $key => $network) {
            $options = [];

            foreach ($catalog->get($network['group'], []) as $route) {
                $options[trim($route->Route) . ',' . trim($route->Routename)] = '(' . trim($route->Route) . ') ' . trim($route->Routename);
            }

            if ($network['extras']) {
                $options['100,ITS Gateway'] = '(100) ITS Gateway';
                $options['0,block'] = '(0) Block';
            }

            $row = $byNetwork[$network['network']] ?? null;
            $current = null;

            if ($row) {
                $blocked = (int) $row->Status === 0;
                $current = $blocked ? '0,block' : trim($row->Route) . ',' . trim($row->Operator);

                if (! isset($options[$current])) {
                    $options = [$current => $blocked ? '(0) Block' : '(' . trim($row->Route) . ') ' . trim($row->Operator)] + $options;
                }
            }

            $networks[$key] = [
                'label'   => $network['label'],
                'id'      => $row ? (int) $row->Id : null,
                'current' => $current,
                'rate'    => $row ? number_format((float) ($row->Rate ?? 1), 2, '.', '') : null,
                'options' => $options,
            ];
        }

        return $networks;
    }
}