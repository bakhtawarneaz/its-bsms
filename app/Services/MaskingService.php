<?php

namespace App\Services;

use App\Repositories\MaskingRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MaskingService
{
    public const PER_PAGE = 25;

    /**
     * Networks in display order. "group" is routes_info.network_name, "code" is masking_routes.network.
     * Jazz is also written for Warid (9232) by the SPs.
     */
    public const NETWORKS = [
        'jazz'    => ['label' => 'Jazz',    'hint' => 'Mobilink 9230, also applied to Warid 9232', 'group' => '1', 'code' => '9230', 'dot' => 'bg-rose-500'],
        'zong'    => ['label' => 'Zong',    'hint' => 'Network 9231', 'group' => '4', 'code' => '9231', 'dot' => 'bg-emerald-500'],
        'ufone'   => ['label' => 'Ufone',   'hint' => 'Network 9233', 'group' => '3', 'code' => '9233', 'dot' => 'bg-orange-500'],
        'telenor' => ['label' => 'Telenor', 'hint' => 'Network 9234', 'group' => '6', 'code' => '9234', 'dot' => 'bg-sky-500'],
        'scom'    => ['label' => 'SCOM',    'hint' => 'Network 9235', 'group' => '5', 'code' => '9235', 'dot' => 'bg-violet-500'],
    ];

    /** Network switches that are on by default in the legacy status form. */
    public const DEFAULT_STATUS = ['jazz' => true, 'zong' => true, 'ufone' => true, 'telenor' => true, 'scom' => false];

    /** Choices every network offers in addition to its routes_info routes (legacy form). */
    private const FIXED_ROUTES = [100 => 'ITS Gateway', 0 => 'block'];

    public function __construct(
        private readonly MaskingRepository $repository,
        private readonly ActivityLogger $activity,
    ) {}

    /** Paginated masking directory with tab counts. */
    public function list(array $filters): array
    {
        $promotional = match ($filters['type']) {
            'promotional'   => 1,
            'transactional' => 0,
            default         => null,
        };

        $page = $filters['page'];
        ['rows' => $rows, 'counts' => $counts] = $this->repository->list($filters, $promotional, $page, self::PER_PAGE);

        $counts['all'] = array_sum($counts);
        $total = $counts[$filters['state'] ?? 'all'] ?? 0;

        $maskings = new LengthAwarePaginator($rows, $total, self::PER_PAGE, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);

        return ['maskings' => $maskings->withQueryString(), 'counts' => $counts];
    }

    /**
     * Route choices per network: [network => [['value' => id, 'operator' => name], ...]].
     */
    public function routeOptions(): array
    {
        return Cache::remember('maskings.route_options', 300, function () {
            $rows = $this->repository->routeOptions();
            $options = [];

            foreach (self::NETWORKS as $key => $network) {
                $list = [];

                foreach ($rows as $row) {
                    if (trim((string) $row->network_name) === $network['group']) {
                        $list[(int) $row->id] = ['value' => (int) $row->id, 'operator' => trim((string) $row->route_name)];
                    }
                }

                foreach (self::FIXED_ROUTES as $id => $name) {
                    $list[$id] = ['value' => $id, 'operator' => $name];
                }

                $options[$key] = array_values($list);
            }

            return $options;
        });
    }

    /**
     * Masking with its current routes keyed by network, and route options that always
     * include the current route (even if it is no longer active in routes_info).
     */
    public function editForm(int $id): array
    {
        $record = $this->repository->find($id) ?? throw new NotFoundHttpException('Masking not found.');

        $current = [];
        $promotional = false;

        foreach ($record['routes'] as $route) {
            $key = $this->networkKey((string) $route->network);

            if ($key) {
                $current[$key] = [
                    'route'    => (int) $route->route,
                    'operator' => trim((string) $route->operator),
                    'status'   => (int) $route->status,
                ];
            }

            $promotional = $promotional || (int) $route->promotional === 1;
        }

        $options = $this->routeOptions();

        foreach ($current as $key => $route) {
            if (! collect($options[$key])->contains('value', $route['route'])) {
                array_unshift($options[$key], ['value' => $route['route'], 'operator' => $route['operator']]);
            }
        }

        return [
            'masking'     => $record['masking'],
            'current'     => $current,
            'promotional' => $promotional,
            'options'     => $options,
        ];
    }

    /**
     * Add one or more maskings with the same routes. Existing maskings are skipped.
     *
     * @return array{added: array<int, string>, duplicates: array<int, string>}
     *
     * @throws ValidationException
     */
    public function create(array $input): array
    {
        $params = ['promotional' => $input['promotional'] ? 1 : 0] + $this->resolveRoutes($input['routes'], $this->routeOptions());

        $added = [];
        $duplicates = [];

        DB::transaction(function () use ($input, $params, &$added, &$duplicates) {
            foreach ($input['masks'] as $mask) {
                $result = $this->repository->create(['mask' => $mask] + $params);

                match ($result->status ?? null) {
                    'DUPLICATE' => $duplicates[] = $mask,
                    'OK'        => $added[] = $mask,
                    default     => throw new \RuntimeException('Masking could not be created.'),
                };
            }
        });

        if (! $added) {
            throw ValidationException::withMessages(['masks' => 'Masking(s) already exist: ' . implode(', ', $duplicates) . '.']);
        }

        // The Add User form lists maskings from a cached query.
        Cache::forget('users.form_options');

        $this->activity->log('Add New Masking and Routes - ' . count($added) . ' Masking(s) Added Successfuly. Duplicate Masking(s) (' . implode(', ', $duplicates) . ')');

        return ['added' => $added, 'duplicates' => $duplicates];
    }

    /**
     * Replace the routes of one masking. All networks are switched off afterwards (legacy behaviour).
     */
    public function saveRoutes(array $editor, int $id, array $input): string
    {
        $form = $this->editForm($id);
        $params = ['masking_id' => $id, 'promotional' => $input['promotional'] ? 1 : 0]
            + $this->resolveRoutes($input['routes'], $form['options']);

        $result = DB::transaction(fn () => $this->repository->saveRoutes($params));

        if (($result->status ?? null) !== 'OK') {
            throw new NotFoundHttpException('Masking not found.');
        }

        $this->activity->log("Edit Masking Routes -  ({$result->masking}) Mask Routes Edited Successfully. By Account Manager {$editor['username']}");

        return (string) $result->masking;
    }

    /**
     * Set network status and type for one or more maskings.
     *
     * @param  array<int, int>  $ids
     * @param  array<string, bool>  $networks
     * @return array{updated: array<int, string>, skipped: array<int, string>}
     *
     * @throws ValidationException
     */
    public function setStatus(array $ids, bool $promotional, array $networks): array
    {
        $updated = [];
        $skipped = [];

        DB::transaction(function () use ($ids, $promotional, $networks, &$updated, &$skipped) {
            foreach (array_unique(array_map('intval', $ids)) as $id) {
                $params = ['masking_id' => $id, 'promotional' => $promotional ? 1 : 0];

                foreach (array_keys(self::NETWORKS) as $network) {
                    $params[$network] = ! empty($networks[$network]) ? 1 : 0;
                }

                $result = $this->repository->setStatus($params);

                match ($result->status ?? null) {
                    'OK'        => $updated[] = (string) $result->masking,
                    'NO_ROUTES' => $skipped[] = (string) $result->masking,
                    default     => null, // Deleted meanwhile; nothing to update.
                };
            }
        });

        if (! $updated) {
            throw ValidationException::withMessages(['maskings' => 'The selected maskings have no routes yet. Add routes first.']);
        }

        $this->activity->log('Edit Maskings Status - (' . implode(',', $updated) . ' ) Masks Status Updated');

        return ['updated' => $updated, 'skipped' => $skipped];
    }

        /**
     * Map chosen route ids to SP parameters, taking operator names from the server-side options.
     * A network without a route gets NULL and is skipped by the SP (legacy behaviour).
     *
     * @throws ValidationException
     */
    private function resolveRoutes(array $routes, array $options): array
    {
        $params = [];

        foreach (array_keys(self::NETWORKS) as $network) {
            $chosen = $routes[$network] ?? null;

            if ($chosen === null) {
                $params["{$network}_route"] = null;
                $params["{$network}_operator"] = null;

                continue;
            }

            $match = collect($options[$network])->firstWhere('value', (int) $chosen);

            if (! $match) {
                throw ValidationException::withMessages(["routes.{$network}" => 'Choose a route from the list.']);
            }

            $params["{$network}_route"] = (int) $chosen;
            $params["{$network}_operator"] = $match['operator'];
        }

        return $params;
    }

    /** Network key for a masking_routes.network code (Warid 9232 is folded into Jazz). */
    private function networkKey(string $code): ?string
    {
        foreach (self::NETWORKS as $key => $network) {
            if ($network['code'] === trim($code)) {
                return $key;
            }
        }

        return null;
    }

        /** Pending masking requests, paginated. */
    public function requests(int $page): LengthAwarePaginator
    {
        $page = max(1, $page);
        ['rows' => $rows, 'total' => $total] = $this->repository->requests($page, self::PER_PAGE);

        return (new LengthAwarePaginator($rows, $total, self::PER_PAGE, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]))->withQueryString();
    }
    
}