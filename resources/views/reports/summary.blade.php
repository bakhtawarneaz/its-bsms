@extends('layouts.app')

@section('title', $report['title'])

@section('content')
    @php
        $columns = $report['columns'];
        $perPages = \App\Http\Requests\Reports\ListSummaryRequest::PER_PAGE;
        $operators = \App\Support\SummaryReports::OPERATORS;
        $hasFilters = $filters['user_id'] || $filters['sender'] || $filters['operator'] || $filters['sms_type'] || $filters['status']
            || $filters['from'] !== now()->subMonth()->toDateString() || $filters['to'] !== now()->toDateString();
        $stat = 'rounded-2xl border border-line bg-white px-5 py-4';
    @endphp

    {{-- Page header --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="font-display text-2xl font-semibold tracking-tight text-ink">{{ $report['title'] }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $report['subtitle'] }}</p>
        </div>
        <a href="{{ route($report['export_route'], request()->except('page')) }}"
           class="inline-flex items-center gap-2 self-start rounded-lg bg-navy-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 sm:self-auto">
            <x-heroicon-o-arrow-down-tray class="size-4" />
            Export CSV
        </a>
    </div>

    {{-- Totals --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="{{ $stat }}">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted">Rows</p>
            <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-ink">{{ number_format((int) ($totals->total_rows ?? 0)) }}</p>
        </div>
        <div class="{{ $stat }}">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted">Total SMS</p>
            <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-ink">{{ number_format((int) ($totals->total_sms ?? 0)) }}</p>
        </div>
        <div class="{{ $stat }}">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted">Total cost</p>
            <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-ink">{{ number_format((float) ($totals->total_cost ?? 0), 2) }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-line bg-white">
        {{-- Filters --}}
        <form method="GET" action="{{ route($report['route']) }}" class="grid gap-3 border-b border-line p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <!-- <label class="field-label" for="f-from">From</label> -->
                <x-date-field name="from" label="From" :value="$filters['from']" />
            </div>
            <div>
                <!-- <label class="field-label" for="f-to">To</label> -->
                <x-date-field name="to" label="To" :value="$filters['to']" />
            </div>
            <div>
                <label class="field-label" for="f-user">User account</label>
                <select id="f-user" name="user_id" class="field-input">
                    <option value="">All users</option>
                    @foreach ($users as $id => $username)
                        <option value="{{ $id }}" @selected($filters['user_id'] === $id)>{{ $username }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="field-label" for="f-sender">Sender</label>
                <input id="f-sender" type="text" name="sender" value="{{ $filters['sender'] }}" maxlength="100" placeholder="Exact mask" class="field-input">
            </div>
            <div>
                <label class="field-label" for="f-operator">Network</label>
                <select id="f-operator" name="operator" class="field-input">
                    <option value="">All networks</option>
                    @foreach ($operators as $value => $label)
                        <option value="{{ $value }}" @selected($filters['operator'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if ($report['sms_types'])
                <div>
                    <label class="field-label" for="f-type">SMS type</label>
                    <select id="f-type" name="sms_type" class="field-input">
                        <option value="">All types</option>
                        @foreach ($report['sms_types'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['sms_type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label class="field-label" for="f-status">Status</label>
                <select id="f-status" name="status" class="field-input">
                    <option value="">All statuses</option>
                    @foreach ($report['statuses'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) $filters['status'] === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            {{-- Actions: span the rest of the row when there is no SMS type filter (gateway) --}}
            <div @class(['flex items-end gap-2', 'lg:col-span-2' => ! $report['sms_types']])>
                <select name="per_page" class="field-input flex-1" aria-label="Rows per page">
                    @foreach ($perPages as $size)
                        <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }} / page</option>
                    @endforeach
                </select>
                <button type="submit" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">Apply</button>
                @if ($hasFilters)
                    <a href="{{ route($report['route']) }}" class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-line px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink">
                        <x-heroicon-m-arrow-path class="size-4" /> Reset
                    </a>
                @endif
            </div>
        </form>

        @if ($errors->any())
            <p class="border-b border-line bg-danger/5 px-4 py-2.5 text-sm text-danger">{{ $errors->first() }}</p>
        @endif

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-surface/60 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                    <tr>
                        @foreach ($columns as [$label, $kind])
                            <th @class(['px-4 py-3', 'text-right' => in_array($kind, ['count', 'cost'], true)])>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $row)
                        <tr class="transition hover:bg-surface/50">
                            @foreach ($columns as $key => [$label, $kind])
                                @php $value = $row->{$key} ?? null; @endphp
                                @switch ($kind)
                                    @case('date')
                                        <td class="whitespace-nowrap px-4 py-3 tabular-nums text-muted">{{ $value ? \Illuminate\Support\Carbon::parse($value)->format('d M Y') : '—' }}</td>
                                        @break
                                    @case('status')
                                        <td class="whitespace-nowrap px-4 py-3">@include('reports.partials.status', ['status' => $value])</td>
                                        @break
                                    @case('count')
                                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-ink">{{ number_format((int) $value) }}</td>
                                        @break
                                    @case('cost')
                                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-ink">{{ $value !== null ? number_format((float) $value, 2) : '—' }}</td>
                                        @break
                                    @case('id')
                                        <td class="whitespace-nowrap px-4 py-3 tabular-nums text-muted">{{ $value }}</td>
                                        @break
                                    @case('strong')
                                        <td class="whitespace-nowrap px-4 py-3 font-medium text-ink">{{ $value ?: '—' }}</td>
                                        @break
                                    @default
                                        <td class="whitespace-nowrap px-4 py-3 text-muted">{{ ($value ?? '') !== '' ? $value : '—' }}</td>
                                @endswitch
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="px-4 py-16 text-center">
                                <x-heroicon-o-chart-bar class="mx-auto size-10 text-muted/50" />
                                <p class="mt-3 font-medium text-ink">No summary rows</p>
                                <p class="mt-1 text-sm text-muted">Try a wider date range or clear the filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Footer --}}
        @if ($rows->total() > 0)
            <div class="flex flex-col gap-3 border-t border-line px-4 py-3 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
                <p>Showing <span class="font-medium text-ink">{{ $rows->firstItem() }}–{{ $rows->lastItem() }}</span> of <span class="font-medium text-ink">{{ number_format($rows->total()) }}</span></p>
                {{ $rows->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection