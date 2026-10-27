<?php

namespace App\Http\Controllers;

use App\Enums\UserType;
use App\Services\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    public const PRESETS = [
        'today'      => 'Today',
        'yesterday'  => 'Yesterday',
        '7d'         => 'Last 7 days',
        '30d'        => 'Last 30 days',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        '90d'        => 'Last 90 days',
    ];

    public const MAX_RANGE_DAYS = 92;

    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->session()->get('auth_user');
        $userType = UserType::fromDatabase($user['user_type']);

          if ($userType->value === 'campapprover') {
            return redirect()->route('campaigns.index', ['state' => 'approval']);
        }

        [$from, $to, $range] = $this->resolveRange($request);

        return view('dashboard.index', [
            'user'       => $user,
            'from'       => $from,
            'to'         => $to,
            'range'      => $range,
            'rangeLabel' => self::PRESETS[$range] ?? 'Custom range',
            'presets'    => self::PRESETS,
            'scopeLabel' => $this->dashboard->scopeLabel($userType),
            'summary'    => $this->dashboard->supports($userType)
                ? $this->dashboard->summary($user, $from, $to)
                : null,
        ]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function resolveRange(Request $request): array
    {
        $range = $request->query('range', '30d');
        $today = CarbonImmutable::today();

        if ($range === 'custom') {
            return $this->customRange($request);
        }

        return match ($range) {
            'today'      => [$today, $today, $range],
            'yesterday'  => [$today->subDay(), $today->subDay(), $range],
            '7d'         => [$today->subDays(6), $today, $range],
            'this_month' => [$today->startOfMonth(), $today, $range],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()->startOfDay(), $range],
            '90d'        => [$today->subDays(89), $today, $range],
            default      => [$today->subDays(29), $today, '30d'],
        };
    }

    private function customRange(Request $request): array
    {
        $data = $request->validate([
            'from' => ['required', 'date', 'before_or_equal:to'],
            'to'   => ['required', 'date', 'before_or_equal:today'],
        ]);

        $from = CarbonImmutable::parse($data['from']);
        $to = CarbonImmutable::parse($data['to']);

        if ($from->diffInDays($to) >= self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'from' => 'Choose a date range of ' . self::MAX_RANGE_DAYS . ' days or less.',
            ]);
        }

        return [$from, $to, 'custom'];
    }
}