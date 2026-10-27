@extends('layouts.app')

@section('title', 'Campaigns')

@section('content')
    @php
        $tabs = ['' => 'All'] + \App\Services\CampaignService::STATES;
        $fields = \App\Services\CampaignService::FIELDS;
        $iconButton = 'grid size-8 place-items-center rounded-lg text-muted transition hover:bg-surface hover:text-ink';
        $listUrl = fn (array $changes = []) => route('campaigns.index', array_filter(
            array_merge(request()->except('page'), $changes), fn ($v) => $v !== null && $v !== ''
        ));
        $badges = [
            'running'   => ['bg-sky-50 text-sky-700 ring-sky-600/20', 'Running'],
            'stopped'   => ['bg-amber-50 text-amber-700 ring-amber-600/20', 'Stopped'],
            'approval'  => ['bg-violet-50 text-violet-700 ring-violet-600/20', 'Waiting approval'],
            'suspended' => ['bg-rose-50 text-rose-700 ring-rose-600/20', 'Suspended'],
            'completed' => ['bg-emerald-50 text-emerald-700 ring-emerald-600/20', 'Completed'],
        ];
        $time = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d M Y, H:i') : '—';
    @endphp

    <div x-data="{ confirm: null }">
        <div class="mb-6">
            <h2 class="font-display text-2xl font-semibold tracking-tight text-ink">Campaigns</h2>
            <p class="mt-1 text-sm text-muted">{{ number_format($counts['']) }} campaigns match the current filters</p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-line bg-white">
            {{-- State tabs --}}
            <nav class="flex gap-1 overflow-x-auto border-b border-line px-4" aria-label="Status">
                @foreach ($tabs as $key => $label)
                    @php $active = ($state ?? '') === $key; @endphp
                    <a href="{{ $listUrl(['state' => $key]) }}" @class([
                        'relative flex shrink-0 items-center gap-2 px-3 py-3.5 text-sm font-medium transition',
                        'text-ink after:absolute after:inset-x-2 after:-bottom-px after:h-0.5 after:rounded-full after:bg-navy-900' => $active,
                        'text-muted hover:text-ink' => ! $active,
                    ])>
                        {{ $label }}
                        <span @class(['rounded-full px-2 py-0.5 text-xs tabular-nums', 'bg-navy-900 text-white' => $active, 'bg-surface text-muted' => ! $active])>{{ number_format($counts[$key] ?? 0) }}</span>
                    </a>
                @endforeach
            </nav>

            {{-- Search --}}
            <form method="GET" action="{{ route('campaigns.index') }}" class="flex flex-col gap-3 border-b border-line p-4 lg:flex-row lg:items-center">
                @if ($state)
                    <input type="hidden" name="state" value="{{ $state }}">
                @endif
                <select name="field" class="field-input lg:w-44">
                    @foreach ($fields as $key => $label)
                        <option value="{{ $key }}" @selected($field === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="relative flex-1">
                    <span class="sr-only">Search value</span>
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted" />
                    <input type="search" name="value" value="{{ $value }}" placeholder="Search value" class="field-input pl-10">
                </label>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">Search</button>
                @if ($value)
                    <a href="{{ route('campaigns.index', array_filter(['state' => $state])) }}" class="inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink">
                        <x-heroicon-m-arrow-path class="size-4" /> Reset
                    </a>
                @endif
            </form>

            @if ($errors->has('campaign'))
                <p class="border-b border-line bg-danger/5 px-4 py-2.5 text-sm text-danger">{{ $errors->first('campaign') }}</p>
            @endif

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-surface/60 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                        <tr>
                            <th class="px-4 py-3">Campaign</th>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Sender</th>
                            <th class="px-4 py-3">Scheduled</th>
                            <th class="px-4 py-3">Progress</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($campaigns as $campaign)
                            @php
                                $total = (int) $campaign->total_sms;
                                $sent = (int) $campaign->total_sent;
                                $percent = $total > 0 ? min(100, round($sent / $total * 100)) : 100;
                                [$badgeStyle, $badgeLabel] = $badges[$campaign->state] ?? $badges['suspended'];
                                $row = ['id' => (int) $campaign->id, 'name' => (string) $campaign->camp_name];
                            @endphp
                            <tr class="transition hover:bg-surface/50">
                                <td class="px-4 py-3">
                                    <p class="max-w-56 truncate font-semibold text-ink" title="{{ $campaign->camp_name }}">{{ $campaign->camp_name }}</p>
                                    <p class="text-xs text-muted">#{{ $campaign->id }} · {{ $campaign->camp_smstypes ?: '—' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <p class="font-medium text-ink">{{ $campaign->username }}</p>
                                    <p class="text-xs text-muted">#{{ $campaign->user_id }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-medium text-ink">{{ $campaign->sender }}</td>
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-muted">
                                    <p>{{ $time($campaign->camp_time) }}</p>
                                    @if ($campaign->camp_end_time)
                                        <p class="text-xs">Ended {{ $time($campaign->camp_end_time) }}</p>
                                    @endif
                                </td>
                                <td class="min-w-48 px-4 py-3">
                                    <div class="flex items-center justify-between text-xs tabular-nums">
                                        <span class="font-medium text-ink">{{ number_format($sent) }} / {{ number_format($total) }}</span>
                                        <span class="text-muted">{{ number_format((int) $campaign->remaining) }} left</span>
                                    </div>
                                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface">
                                        <div class="h-full rounded-full bg-navy-900" style="width: {{ $percent }}%"></div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $badgeStyle }}">{{ $badgeLabel }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @switch ($campaign->state)
                                            @case('approval')
                                                <button type="button" class="{{ $iconButton }} hover:text-emerald-600" title="Approve" @click="confirm = { ...@js($row), action: 'approve' }">
                                                    <x-heroicon-o-check-badge class="size-4.5" />
                                                </button>
                                                <button type="button" class="{{ $iconButton }} hover:text-amber-600" title="Stop" @click="confirm = { ...@js($row), action: 'stop' }">
                                                    <x-heroicon-s-stop class="size-4.5" />
                                                </button>
                                                <button type="button" class="{{ $iconButton }} hover:text-danger" title="Suspend" @click="confirm = { ...@js($row), action: 'suspend' }">
                                                    <x-heroicon-o-no-symbol class="size-4.5" />
                                                </button>
                                                @break
                                            @case('running')
                                                <button type="button" class="{{ $iconButton }} hover:text-amber-600" title="Stop" @click="confirm = { ...@js($row), action: 'stop' }">
                                                    <x-heroicon-s-stop class="size-4.5" />
                                                </button>
                                                @break
                                            @case('stopped')
                                                <button type="button" class="{{ $iconButton }} hover:text-emerald-600" title="Start" @click="confirm = { ...@js($row), action: 'play' }">
                                                    <x-heroicon-s-play class="size-4.5" />
                                                </button>
                                                <button type="button" class="{{ $iconButton }}" title="Reschedule" @click="confirm = { ...@js($row), action: 'reschedule' }">
                                                    <x-heroicon-o-calendar-days class="size-4.5" />
                                                </button>
                                                <button type="button" class="{{ $iconButton }} hover:text-danger" title="Suspend" @click="confirm = { ...@js($row), action: 'suspend' }">
                                                    <x-heroicon-o-no-symbol class="size-4.5" />
                                                </button>
                                                @break
                                            @case('completed')
                                                <a href="{{ route('campaigns.logs', $campaign->id) }}" class="{{ $iconButton }}" title="Download logs">
                                                    <x-heroicon-o-arrow-down-tray class="size-4.5" />
                                                </a>
                                                @break
                                            @default
                                                @if ($canRevert && (int) ($campaign->stop_camp ?? 0) === 0)
                                                    <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-line px-2.5 py-1.5 text-xs font-medium text-ink transition hover:border-navy-700/40"
                                                            title="Revert balance" @click="confirm = { ...@js($row), action: 'revert' }">
                                                        <x-heroicon-o-arrow-uturn-left class="size-4" /> Revert balance
                                                    </button>
                                                 @elseif ((int) ($campaign->stop_camp ?? 0) !== 0)
                                                    <span class="rounded-md bg-surface px-2 py-1 text-xs font-medium text-muted">Reverted</span>
                                                @endif
                                        @endswitch
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-16 text-center">
                                    <x-heroicon-o-megaphone class="mx-auto size-10 text-muted/50" />
                                    <p class="mt-3 font-medium text-ink">No campaigns found</p>
                                    <p class="mt-1 text-sm text-muted">Try a different search or tab.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($campaigns->total() > 0)
                <div class="flex flex-col gap-3 border-t border-line px-4 py-3 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
                    <p>Showing <span class="font-medium text-ink">{{ $campaigns->firstItem() }}–{{ $campaigns->lastItem() }}</span> of <span class="font-medium text-ink">{{ number_format($campaigns->total()) }}</span></p>
                    {{ $campaigns->onEachSide(1)->links() }}
                </div>
            @endif
        </div>

        {{-- Action modal --}}
        <div x-show="confirm" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="confirm = null">
            <div x-show="confirm" x-transition.opacity.duration.200ms class="absolute inset-0 bg-navy-950/50 backdrop-blur-[2px]" @click="confirm = null"></div>

            <template x-if="confirm">
                <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl shadow-navy-950/20" x-data="{ busy: false }">
                                        <h3 class="font-display text-lg font-semibold"
                        x-text="{ approve: 'Approve campaign?', stop: 'Stop campaign?', play: 'Start campaign again?', suspend: 'Suspend campaign?', reschedule: 'Reschedule campaign', revert: 'Revert campaign balance?' }[confirm.action]"></h3>
                    <p class="mt-1 truncate text-sm text-muted" x-text="'#' + confirm.id + ' · ' + confirm.name"></p>
                    <p class="mt-3 text-sm text-muted" x-show="confirm.action === 'approve'">Held messages are released and the campaign starts sending.</p>
                    <p class="mt-3 text-sm text-muted" x-show="confirm.action === 'stop'">Remaining messages are held. The balance is <span class="font-medium text-ink">not</span> reverted.</p>
                    <p class="mt-3 text-sm text-muted" x-show="confirm.action === 'suspend'">The campaign ends for good; held messages will not be sent.</p>
                    <p class="mt-3 text-sm text-muted" x-show="confirm.action === 'revert'">Credits for the held messages go back to the user's balance. This can be done only once.</p>

                    <form method="POST" :action="@js(url('/campaigns')) + '/' + confirm.id + (confirm.action === 'revert' ? '/revert' : '/action')" class="mt-5 space-y-4" @submit="busy = true">
                        @csrf
                        <input type="hidden" name="action" :value="confirm.action">

                        <template x-if="confirm.action === 'reschedule'">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-date-field name="send_date" label="Date" :value="now()->toDateString()" :min="now()->toDateString()" max="{{ now()->addMonths(3)->toDateString() }}" />
                                <div>
                                    <label class="field-label" for="resched-time">Time</label>
                                    <input id="resched-time" type="time" name="send_time" value="{{ now()->addHour()->format('H:00') }}" required class="field-input">
                                </div>
                            </div>
                        </template>

                        <div class="flex justify-end gap-2">
                            <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink" @click="confirm = null">Cancel</button>
                            <button type="submit" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition disabled:opacity-60"
                                    :class="confirm.action === 'suspend' ? 'bg-danger hover:opacity-90' : 'bg-navy-900 hover:bg-navy-800'"
                                    :disabled="busy"
                                    x-text="{ approve: 'Approve', stop: 'Stop', play: 'Start', suspend: 'Suspend', reschedule: 'Reschedule', revert: 'Revert balance' }[confirm.action]"></button>
                        </div>
                    </form>
                </div>
            </template>
        </div>
    </div>
@endsection