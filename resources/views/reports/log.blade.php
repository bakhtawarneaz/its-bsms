@extends('layouts.app')

@section('title', $report['title'])

@section('content')
    @php
        $fields = $report['fields'];
        $columns = $report['columns'];
        $perPages = \App\Http\Requests\Reports\ListLogRequest::PER_PAGE;
        $yesterday = now()->subDay()->toDateString();
        $exportErrors = $errors->hasAny(['from', 'to', 'sender', 'receiver']);
        $currentField = $filters['field'] ?? request('field', 'id');
        $formatTime = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d M Y, H:i:s') : '—';
    @endphp

    <div x-data="{ exporting: @js($exportErrors) }">
        {{-- Page header --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-display text-2xl font-semibold tracking-tight text-ink">{{ $report['title'] }}</h2>
                <p class="mt-1 text-sm text-muted">{{ $report['subtitle'] }}</p>
            </div>
            <button type="button" @click="exporting = true"
                    class="inline-flex items-center gap-2 self-start rounded-lg bg-navy-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 sm:self-auto">
                <x-heroicon-o-arrow-down-tray class="size-4" />
                Export
            </button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-line bg-white">
            {{-- Filters --}}
            <form method="GET" action="{{ route($report['route']) }}" class="flex flex-col gap-3 border-b border-line p-4 lg:flex-row lg:items-center"
                  x-data="{ field: @js($currentField) }">
                <select name="field" x-model="field" class="field-input lg:w-40">
                    @foreach ($fields as $key => [$label])
                        <option value="{{ $key }}" @selected($currentField === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                <label class="relative flex-1">
                    <span class="sr-only">Search value</span>
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted" />
                    <input type="search" name="value" value="{{ request('value') }}" placeholder="Search value" class="field-input pl-10"
                           :list="field === 'status' ? 'log-statuses' : null">
                </label>

                @if (! empty($report['statuses']))
                    <datalist id="log-statuses">
                        @foreach (array_keys($report['statuses']) as $status)
                            <option value="{{ ucwords($status) }}"></option>
                        @endforeach
                    </datalist>
                @endif

                <select name="per_page" onchange="this.form.requestSubmit()" class="field-input lg:w-32">
                    @foreach ($perPages as $size)
                        <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }} / page</option>
                    @endforeach
                </select>

                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">Search</button>

                @if ($filters['field'])
                    <a href="{{ route($report['route']) }}" class="inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink">
                        <x-heroicon-m-arrow-path class="size-4" /> Reset
                    </a>
                @endif
            </form>

            @if ($errors->has('value'))
                <p class="border-b border-line bg-danger/5 px-4 py-2.5 text-sm text-danger">{{ $errors->first('value') }}</p>
            @endif

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-surface/60 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                        <tr>
                            @foreach ($columns as [$label, $kind])
                                <th @class(['px-4 py-3', 'text-right' => $kind === 'cost'])>{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($rows as $row)
                            <tr class="align-top transition hover:bg-surface/50">
                                @foreach ($columns as $key => [$label, $kind])
                                    @php $value = $row->{$key} ?? null; @endphp
                                    @switch ($kind)
                                        @case('message')
                                            <td class="min-w-72 max-w-md px-4 py-3 text-ink">
                                                <p class="line-clamp-2 break-words" title="{{ $value }}">{{ $value }}</p>
                                            </td>
                                            @break
                                        @case('time')
                                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-muted">{{ $formatTime($value) }}</td>
                                            @break
                                        @case('status')
                                            <td class="whitespace-nowrap px-4 py-3">@include('reports.partials.status', ['status' => $value])</td>
                                            @break
                                        @case('cost')
                                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-ink">{{ $value !== null ? number_format((float) $value, 2) : '—' }}</td>
                                            @break
                                        @case('id')
                                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-muted">{{ $value }}</td>
                                            @break
                                        @case('strong')
                                            <td class="whitespace-nowrap px-4 py-3 font-medium text-ink">{{ $value }}</td>
                                            @break
                                        @case('number')
                                        @case('phone')
                                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-ink">{{ $value ?? '—' }}</td>
                                            @break
                                        @default
                                            <td class="whitespace-nowrap px-4 py-3 text-muted">{{ ($value ?? '') !== '' ? $value : '—' }}</td>
                                    @endswitch
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columns) }}" class="px-4 py-16 text-center">
                                    <x-dynamic-component :component="'heroicon-o-' . $report['icon']" class="mx-auto size-10 text-muted/50" />
                                    <p class="mt-3 font-medium text-ink">No messages found</p>
                                    <p class="mt-1 text-sm text-muted">Try a different search or clear the filter.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($rows || $filters['page'] > 1)
                @include('reports.partials.pager')
            @endif
        </div>

        {{-- Export modal --}}
        <div x-show="exporting" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="exporting = false">
            <div x-show="exporting" x-transition.opacity.duration.200ms class="absolute inset-0 bg-navy-950/50 backdrop-blur-[2px]" @click="exporting = false"></div>

            <div x-show="exporting" x-transition class="relative w-full max-w-lg rounded-2xl bg-white shadow-xl shadow-navy-950/20">
                <div class="flex items-start gap-4 border-b border-line p-6">
                    <span class="grid size-11 shrink-0 place-items-center rounded-full bg-navy-900 text-white">
                        <x-heroicon-o-arrow-down-tray class="size-6" />
                    </span>
                    <div>
                        <h3 class="font-display text-lg font-semibold">Export {{ strtolower($report['title']) }}</h3>
                        <p class="mt-0.5 text-sm text-muted">CSV of messages in the range, up to {{ number_format($report['max_rows']) }} rows.</p>
                    </div>
                </div>

                <form method="GET" action="{{ route($report['export_route']) }}" class="space-y-4 p-6" @submit="setTimeout(() => exporting = false, 300)">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <!-- <label class="field-label" for="export-from">From</label> -->
                            <!-- <input id="export-from" type="date" name="from" value="{{ old('from', $yesterday) }}" max="{{ now()->toDateString() }}" required class="field-input"> -->
                             <x-date-field name="from" label="From" :value="old('from', $yesterday)" />
                        </div>
                        <div>
                            <!-- <label class="field-label" for="export-to">To</label> -->
                            <!-- <input id="export-to" type="date" name="to" value="{{ old('to', $yesterday) }}" max="{{ now()->toDateString() }}" required class="field-input"> -->
                            <x-date-field name="to" label="To" :value="old('to', $yesterday)" />
                        </div>
                        <div>
                            <label class="field-label" for="export-sender">Sender <span class="font-normal text-muted">(optional)</span></label>
                            <input id="export-sender" type="text" name="sender" value="{{ old('sender') }}" maxlength="100" class="field-input">
                        </div>
                        <div>
                            <label class="field-label" for="export-receiver">Receiver <span class="font-normal text-muted">(optional)</span></label>
                            <input id="export-receiver" type="text" name="receiver" value="{{ old('receiver') }}" maxlength="100" class="field-input">
                        </div>
                    </div>

                    @if ($exportErrors)
                        <p class="rounded-lg border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">
                            {{ $errors->first('from') ?: $errors->first('to') ?: $errors->first('sender') ?: $errors->first('receiver') }}
                        </p>
                    @endif

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink" @click="exporting = false">Cancel</button>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">
                            <x-heroicon-o-arrow-down-tray class="size-4" /> Download CSV
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection