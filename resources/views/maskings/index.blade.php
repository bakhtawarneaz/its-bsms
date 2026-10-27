@extends('layouts.app')

@section('title', 'Maskings')

@section('content')
    @inject('permissions', 'App\Support\Permissions')
    @php
        $tabs = ['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive', 'unrouted' => 'No routes'];
        $listUrl = fn (array $changes = []) => route('maskings.index', array_filter(
            array_merge(request()->except('page'), $changes),
            fn ($v) => $v !== null && $v !== ''
        ));
        $hasFilters = $filters['search'] || $filters['type'];
        $iconButton = 'grid size-8 place-items-center rounded-lg text-muted transition hover:bg-surface hover:text-ink';
        $pageIds = collect($maskings->items())->map(fn ($row) => (int) $row->id)->values();

        // Badge style and tooltip for one network of one masking.
        $networkPill = function (object $row, string $key): array {
            $route = $row->{$key . '_route'};
            $operator = trim((string) $row->{$key . '_operator'});

            return match (true) {
                $route === null => ['border border-dashed border-line text-muted/70', 'No route'],
                (int) $route === 0 => ['bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20', 'Blocked'],
                (int) $row->{$key . '_status'} === 1 => ['bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20', "({$route}) {$operator} · Active"],
                default => ['bg-surface text-muted ring-1 ring-inset ring-line', "({$route}) {$operator} · Off"],
            };
        };
        $rowsData = collect($maskings->items())->mapWithKeys(function ($row) use ($networks) {
            $data = ['label' => trim($row->masking), 'promotional' => (int) $row->promotional === 1];
            foreach (array_keys($networks) as $key) {
                $data[$key] = (int) $row->{$key . '_status'} === 1;
            }

            return [(int) $row->id => $data];
        });
        $canStatus = $permissions->has(\App\Support\Permissions::MASK_APPROVAL);
    @endphp

    <div x-data="{
        selected: [],
        pageIds: @js($pageIds),
        rows: @js($rowsData),
        fields: @js(array_merge(['promotional'], array_keys($networks))),
        status: null,
        get allChecked() { return this.pageIds.length > 0 && this.pageIds.every(id => this.selected.includes(id)) },
        toggleAll() { this.selected = this.allChecked ? [] : [...this.pageIds] },
        // A switch starts on only when it is on for every selected masking; differing ones are marked mixed.
        openBulk() {
            const picked = this.selected.map(id => this.rows[id]).filter(Boolean);
            const state = {
                ids: [...this.selected],
                label: picked.length === 1 ? picked[0].label : picked.length + ' selected maskings',
                mixed: {},
            };
            this.fields.forEach(key => {
                const on = picked.filter(row => row[key]).length;
                state[key] = on > 0 && on === picked.length;
                state.mixed[key] = on > 0 && on < picked.length;
            });
            this.status = state;
        },
    }">
        {{-- Page header --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-display text-2xl font-semibold tracking-tight text-ink">Maskings</h2>
                <p class="mt-1 text-sm text-muted">{{ number_format($counts['all']) }} sender IDs match the current filters</p>
            </div>
            <div class="flex gap-2 self-start sm:self-auto">
                <a href="{{ route('maskings.requests') }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-4 py-2.5 text-sm font-semibold text-ink transition hover:border-navy-700/40">
                    <x-heroicon-o-inbox class="size-4" />
                    Requests
                </a>
                @if ($canStatus)
                    <a href="{{ route('gateway.mask-routes') }}"
                       class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-4 py-2.5 text-sm font-semibold text-ink transition hover:border-navy-700/40">
                        <x-heroicon-o-signal class="size-4" />
                        Gateway routes
                    </a>
                @endif
                <a href="{{ route('maskings.create') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-navy-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">
                    <x-heroicon-m-plus class="size-4" />
                    Add masking
                </a>
            </div>
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

            {{-- Filters --}}
            <form method="GET" action="{{ route('maskings.index') }}" class="flex flex-col gap-3 border-b border-line p-4 lg:flex-row lg:items-center">
                @if ($filters['state'])
                    <input type="hidden" name="state" value="{{ $filters['state'] }}">
                @endif

                <label class="relative flex-1">
                    <span class="sr-only">Search maskings</span>
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted" />
                    <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search by masking or ID" class="field-input pl-10">
                </label>

                <select name="type" onchange="this.form.requestSubmit()" class="field-input lg:w-52">
                    <option value="">All types</option>
                    <option value="transactional" @selected($filters['type'] === 'transactional')>Transactional</option>
                    <option value="promotional" @selected($filters['type'] === 'promotional')>Promotional</option>
                </select>

                @if ($hasFilters)
                    <a href="{{ route('maskings.index', array_filter(['state' => $filters['state']])) }}"
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
                            <th class="w-10 py-3 pl-4">
                                @if ($canStatus)
                                    <input type="checkbox" class="size-4 cursor-pointer accent-navy-900" aria-label="Select all on this page"
                                           :checked="allChecked" @change="toggleAll()">
                                @endif
                            </th>
                            <th class="px-4 py-3">Masking</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Networks</th>
                            <th class="px-4 py-3 text-right">Users</th>
                            <th class="px-4 py-3">Created</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($maskings as $masking)
                            @php
                                $name = trim($masking->masking);
                                $hasRoutes = $masking->state !== 'unrouted';
                                $rowStatus = ['ids' => [(int) $masking->id], 'label' => $name, 'promotional' => (int) $masking->promotional === 1];
                                foreach (array_keys($networks) as $key) {
                                    $rowStatus[$key] = (int) $masking->{$key . '_status'} === 1;
                                }
                            @endphp
                            <tr class="transition hover:bg-surface/50" :class="selected.includes({{ (int) $masking->id }}) && 'bg-navy-900/[0.03]'">
                                <td class="py-3 pl-4">
                                    @if ($canStatus)
                                        <input type="checkbox" class="size-4 cursor-pointer accent-navy-900" aria-label="Select {{ $name }}"
                                               value="{{ (int) $masking->id }}" x-model.number="selected">
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-navy-900 text-white">
                                            <x-heroicon-o-identification class="size-5" />
                                        </span>
                                        <div class="min-w-0">
                                            <a href="{{ route('maskings.edit', $masking->id) }}" class="block truncate font-semibold text-ink hover:underline">{{ $name }}</a>
                                            <p class="text-xs text-muted">#{{ $masking->id }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    @if ($masking->promotional === null)
                                        <span class="text-muted">—</span>
                                    @elseif ((int) $masking->promotional === 1)
                                        <span class="rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">Promotional</span>
                                    @else
                                        <span class="rounded-md bg-sky-50 px-2 py-1 text-xs font-medium text-sky-700">Transactional</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($networks as $key => $network)
                                            @php [$style, $detail] = $networkPill($masking, $key); @endphp
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $style }}"
                                                  title="{{ $network['label'] }}: {{ $detail }}">
                                                <span class="size-1.5 rounded-full bg-current"></span>
                                                {{ $network['label'] }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-ink">{{ number_format((int) $masking->users_count) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-muted">
                                    {{ $masking->crtime ? \Illuminate\Support\Carbon::parse($masking->crtime)->format('d M Y') : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($hasRoutes && $canStatus)
                                            <button type="button" class="{{ $iconButton }}" title="Change status" @click="status = @js($rowStatus)">
                                                <x-heroicon-o-signal class="size-4.5" />
                                            </button>
                                        @else
                                            <span class="size-8 shrink-0" aria-hidden="true"></span>
                                        @endif
                                        <a href="{{ route('maskings.edit', $masking->id) }}" class="{{ $iconButton }}" title="{{ $hasRoutes ? 'Edit routes' : 'Add routes' }}">
                                            <x-heroicon-o-pencil-square class="size-4.5" />
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-16 text-center">
                                    <x-heroicon-o-identification class="mx-auto size-10 text-muted/50" />
                                    <p class="mt-3 font-medium text-ink">No maskings found</p>
                                    <p class="mt-1 text-sm text-muted">Try a different search or clear the filters.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Footer --}}
            @if ($maskings->total() > 0)
                <div class="flex flex-col gap-3 border-t border-line px-4 py-3 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
                    <p>Showing <span class="font-medium text-ink">{{ $maskings->firstItem() }}–{{ $maskings->lastItem() }}</span> of <span class="font-medium text-ink">{{ number_format($maskings->total()) }}</span></p>
                    {{ $maskings->onEachSide(1)->links() }}
                </div>
            @endif
        </div>

        {{-- Bulk action bar --}}
        @if ($canStatus)
        <div x-show="selected.length" x-cloak
             x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 translate-y-4"
             class="fixed inset-x-0 bottom-6 z-40 flex justify-center px-4 lg:pl-64">
            <div class="flex items-center gap-3 rounded-2xl bg-navy-900 py-2.5 pr-2.5 pl-5 text-sm text-white shadow-xl shadow-navy-950/30">
                <span><span class="font-semibold tabular-nums" x-text="selected.length"></span> selected</span>
                <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-white px-3.5 py-2 font-semibold text-navy-900 transition hover:bg-white/90" @click="openBulk()">
                    <x-heroicon-o-signal class="size-4" /> Change status
                </button>
                <button type="button" class="rounded-lg px-3 py-2 text-white/70 transition hover:bg-white/10 hover:text-white" @click="selected = []">Clear</button>
            </div>
        </div>
        @endif

        {{-- Status modal --}}
        <div x-show="status" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" aria-labelledby="status-title"
             @keydown.escape.window="status = null">
            <div x-show="status" x-transition.opacity.duration.200ms class="absolute inset-0 bg-navy-950/50 backdrop-blur-[2px]" @click="status = null"></div>

            <template x-if="status">
                <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-xl shadow-navy-950/20" x-data="{ busy: false }">
                    <div class="flex items-start gap-4 border-b border-line p-6">
                        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-navy-900 text-white">
                            <x-heroicon-o-signal class="size-6" />
                        </span>
                        <div class="min-w-0">
                            <h3 id="status-title" class="font-display text-lg font-semibold">Change status</h3>
                            <p class="mt-0.5 truncate text-sm text-muted" x-text="status.label"></p>
                            <p class="mt-2 text-xs leading-relaxed text-muted"
   x-text="status.ids.length > 1
       ? 'Switches show what is on for every selected masking. Mixed ones start off. Your choice is applied to all ' + status.ids.length + '.'
       : 'Current status is pre-filled. Change the switches and update.'"></p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('maskings.status') }}" class="space-y-6 p-6" @submit="busy = true">
                        @csrf
                        <template x-for="id in status.ids" :key="id">
                            <input type="hidden" name="maskings[]" :value="id">
                        </template>

                        <div>
                            <span class="field-label">Type</span>
                            <label class="inline-flex cursor-pointer items-center gap-3">
                                <input type="checkbox" name="promotional" value="1" class="peer sr-only" x-model="status.promotional">
                                <span class="relative h-6 w-11 rounded-full bg-line transition peer-checked:bg-success after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
                                <span class="text-sm text-muted">Promotional</span>
                            </label>
                        </div>

                        <div>
                            <span class="field-label">Networks</span>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($networks as $key => $network)
                                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-line px-4 py-3 transition hover:border-navy-700/30">
                                        <!-- <span class="flex items-center gap-2 text-sm font-medium">
                                            <span class="size-2.5 rounded-full {{ $network['dot'] }}"></span>
                                            {{ $network['label'] }}
                                        </span> -->
                                        <span class="flex items-center gap-2 text-sm font-medium">
                                            <span class="size-2.5 rounded-full {{ $network['dot'] }}"></span>
                                            {{ $network['label'] }}
                                            <span x-show="status.mixed?.{{ $key }}" x-cloak class="rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">Mixed</span>
                                        </span>
                                        <input type="checkbox" name="networks[{{ $key }}]" value="1" class="peer sr-only" x-model="status.{{ $key }}">
                                        <span class="relative h-5 w-9 shrink-0 rounded-full bg-line transition peer-checked:bg-success after:absolute after:top-0.5 after:left-0.5 after:size-4 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-4"></span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="field-hint">Jazz also applies to Warid. Blocked routes always stay off.</p>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink" @click="status = null">Cancel</button>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:opacity-60" :disabled="busy">
                                <svg x-show="busy" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                                <span x-text="busy ? 'Updating…' : 'Update status'">Update status</span>
                            </button>
                        </div>
                    </form>
                </div>
            </template>
        </div>
    </div>
@endsection