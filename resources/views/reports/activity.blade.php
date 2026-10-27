@extends('layouts.app')

@section('title', 'Activity logs')

@section('content')
    @php
        $perPages = \App\Http\Requests\Reports\ListActivityRequest::PER_PAGE;
        $hasFilters = $filters['user_id'] || $filters['ip'] || $filters['search']
            || $filters['from'] !== now()->subDay()->toDateString() || $filters['to'] !== now()->toDateString();
    @endphp

    {{-- Page header --}}
    <div class="mb-6">
        <h2 class="font-display text-2xl font-semibold tracking-tight text-ink">Activity logs</h2>
        <p class="mt-1 text-sm text-muted">{{ number_format($rows->total()) }} actions match the current filters</p>
    </div>

    <div class="overflow-hidden rounded-2xl border border-line bg-white">
        {{-- Filters --}}
        <form method="GET" action="{{ route('activity.index') }}" class="grid gap-3 border-b border-line p-4 sm:grid-cols-2 lg:grid-cols-3">
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
                <label class="field-label" for="f-ip">IP address</label>
                <input id="f-ip" type="text" name="ip" value="{{ $filters['ip'] }}" maxlength="45" class="field-input tabular-nums">
            </div>
            <div>
                <label class="field-label" for="f-search">Activity contains</label>
                <input id="f-search" type="search" name="search" value="{{ $filters['search'] }}" maxlength="200" class="field-input">
            </div>
            <div class="flex items-end gap-2">
                <select name="per_page" class="field-input flex-1" aria-label="Rows per page">
                    @foreach ($perPages as $size)
                        <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }} / page</option>
                    @endforeach
                </select>
                <button type="submit" class="inline-flex flex-1 items-center justify-center rounded-lg bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">Apply</button>
                @if ($hasFilters)
                    <a href="{{ route('activity.index') }}" class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-line px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink">
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
                        <th class="px-4 py-3">Time</th>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">IP address</th>
                        <th class="px-4 py-3">Activity</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $row)
                        <tr class="cursor-pointer align-top transition hover:bg-surface/50" x-data="{ open: false }" @click="open = ! open">
                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-muted">
                                {{ $row->activity_time ? \Illuminate\Support\Carbon::parse($row->activity_time)->format('d M Y, H:i:s') : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-ink">{{ $row->username ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-muted">{{ $row->ipaddress ?: '—' }}</td>
                            <td class="min-w-96 px-4 py-3 text-ink">
                                <p class="break-words" :class="open ? 'whitespace-pre-wrap' : 'line-clamp-2'">{{ $row->activity }}</p>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-16 text-center">
                                <x-heroicon-o-clock class="mx-auto size-10 text-muted/50" />
                                <p class="mt-3 font-medium text-ink">No activity found</p>
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