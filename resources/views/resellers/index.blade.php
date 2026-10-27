@extends('layouts.app')

@section('title', 'Resellers')

@section('content')
    @inject('permissions', 'App\Support\Permissions')
    @php
        $tabs = ['' => 'All', 'active' => 'Active', 'expired' => 'Expired', 'disabled' => 'Disabled', 'blocked' => 'Blocked'];
        $pill = [
            'active'   => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            'expired'  => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            'disabled' => 'bg-surface text-muted ring-line',
            'blocked'  => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        ];
        $listUrl = fn (array $changes = []) => route('resellers.index', array_filter(
            array_merge(request()->except('page'), $changes),
            fn ($v) => $v !== null && $v !== ''
        ));
        $iconButton = 'grid size-8 place-items-center rounded-lg text-muted transition hover:bg-surface hover:text-ink';
        $canEdit = $permissions->has(\App\Support\Permissions::EDIT_USERS);
    @endphp

    {{-- Page header --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="font-display text-2xl font-semibold tracking-tight text-ink">Resellers</h2>
            <p class="mt-1 text-sm text-muted">{{ number_format($counts['all']) }} white-label partners match the current filters</p>
        </div>
        <a href="{{ route('resellers.create') }}"
           class="inline-flex items-center gap-2 self-start rounded-lg bg-navy-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 sm:self-auto">
            <x-heroicon-m-plus class="size-4" />
            Add reseller
        </a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-line bg-white">

        {{-- State tabs --}}
        <nav class="flex gap-1 overflow-x-auto border-b border-line px-4" aria-label="Status">
            @foreach ($tabs as $key => $label)
                @php $active = ($filters['state'] ?? '') === $key; @endphp
                <a href="{{ $listUrl(['state' => $key]) }}"
                   @class([
                       'relative flex shrink-0 items-center gap-2 px-3 py-3.5 text-sm font-medium transition',
                       'text-ink after:absolute after:inset-x-2 after:-bottom-px after:h-0.5 after:rounded-full after:bg-navy-900' => $active,
                       'text-muted hover:text-ink' => ! $active,
                   ])>
                    {{ $label }}
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs tabular-nums',
                        'bg-navy-900 text-white' => $active,
                        'bg-surface text-muted' => ! $active,
                    ])>{{ number_format($counts[$key ?: 'all'] ?? 0) }}</span>
                </a>
            @endforeach
        </nav>

        {{-- Search --}}
        <form method="GET" action="{{ route('resellers.index') }}" class="flex flex-col gap-3 border-b border-line p-4 lg:flex-row lg:items-center">
            @if ($filters['state'])
                <input type="hidden" name="state" value="{{ $filters['state'] }}">
            @endif

            <label class="relative flex-1">
                <span class="sr-only">Search resellers</span>
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted" />
                <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search by ID, username, company or domain" class="field-input pl-10">
            </label>

            @if ($filters['search'])
                <a href="{{ route('resellers.index', array_filter(['state' => $filters['state']])) }}"
                   class="inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink">
                    <x-heroicon-m-arrow-path class="size-4" />
                    Reset
                </a>
            @endif
        </form>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-surface/60 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                    <tr>
                        <th class="px-4 py-3">Reseller</th>
                        <th class="px-4 py-3">Domain</th>
                        <th class="px-4 py-3 text-right">Users</th>
                        <th class="px-4 py-3 text-right">Account</th>
                        <th class="px-4 py-3">Expiry</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($resellers as $reseller)
                        @php
                            $expiry = $reseller->user_expiry ? \Illuminate\Support\Carbon::parse($reseller->user_expiry) : null;
                            $isExpired = $expiry?->endOfDay()->isPast() ?? false;
                            $isPrepaid = strtolower(trim((string) $reseller->account_type)) === 'prepaid';
                            $company = trim((string) $reseller->company_name) ?: ($reseller->fullname ?: $reseller->username);
                        @endphp
                        <tr class="transition hover:bg-surface/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    @if ($reseller->logo_url)
                                        <img src="{{ $reseller->logo_url }}" alt="" class="size-10 shrink-0 rounded-lg border border-line bg-white object-contain p-1" loading="lazy">
                                    @else
                                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-navy-900 text-xs font-semibold uppercase text-white">
                                            {{ mb_substr($company, 0, 2) }}
                                        </span>
                                    @endif
                                    <div class="min-w-0">
                                        @if ($canEdit)
                                            <a href="{{ route('resellers.edit', $reseller->id) }}" class="block truncate font-semibold text-ink hover:underline">{{ $company }}</a>
                                        @else
                                            <p class="truncate font-semibold text-ink">{{ $company }}</p>
                                        @endif
                                        <p class="truncate text-xs text-muted">#{{ $reseller->id }} · {{ $reseller->username }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($reseller->site_name)
                                    <a href="https://{{ $reseller->site_name }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-ink hover:underline">
                                        {{ $reseller->site_name }}
                                        <x-heroicon-m-arrow-top-right-on-square class="size-3.5 text-muted" />
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-ink">{{ number_format((int) $reseller->users_count) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <p class="font-medium capitalize text-ink">{{ $reseller->account_type ?: '—' }}</p>
                                @if ($isPrepaid)
                                    <p class="text-xs tabular-nums text-muted">{{ number_format((int) $reseller->balance) }} credits</p>
                                @endif
                            </td>
                            <td @class(['whitespace-nowrap px-4 py-3 tabular-nums', 'text-danger' => $isExpired, 'text-muted' => ! $isExpired])>
                                {{ $expiry?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium capitalize ring-1 ring-inset {{ $pill[$reseller->state] ?? $pill['disabled'] }}">
                                    <span class="size-1.5 rounded-full bg-current"></span>
                                    {{ $reseller->state }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    @if ($canEdit)
                                        <a href="{{ route('resellers.edit', $reseller->id) }}" class="{{ $iconButton }}" title="Edit branding">
                                            <x-heroicon-o-swatch class="size-4.5" />
                                        </a>
                                        <a href="{{ route('users.edit', $reseller->id) }}" class="{{ $iconButton }}" title="Edit account">
                                            <x-heroicon-o-pencil-square class="size-4.5" />
                                        </a>
                                    @else
                                        <span class="text-xs text-muted">View only</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                <x-heroicon-o-building-storefront class="mx-auto size-10 text-muted/50" />
                                <p class="mt-3 font-medium text-ink">No resellers found</p>
                                <p class="mt-1 text-sm text-muted">Try a different search or add a new reseller.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($resellers->total() > 0)
            <div class="flex flex-col gap-3 border-t border-line px-4 py-3 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
                <p>Showing <span class="font-medium text-ink">{{ $resellers->firstItem() }}–{{ $resellers->lastItem() }}</span> of <span class="font-medium text-ink">{{ number_format($resellers->total()) }}</span></p>
                {{ $resellers->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection