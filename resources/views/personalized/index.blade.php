@extends('layouts.app')

@section('title', 'Personalized SMS')

@section('content')
    @php
        $fields = \App\Services\PersonalizedService::FIELDS;
        $statuses = \App\Services\PersonalizedService::STATUSES;
        $time = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d M Y, H:i') : '—';
        $pendingTotal = collect($campaigns)->sum('pending');
        $selectable = $tab === 'pending' && $campId && $messages;
        $pageIds = $selectable ? collect($messages['rows'])->pluck('id')->map(fn ($id) => (int) $id)->values()->all() : [];
        $pageUrl = fn (int $page) => route('personalized.index', array_filter(
            array_merge(request()->query(), ['page' => $page]), fn ($v) => $v !== null && $v !== ''
        ));
        $statusStyle = fn (int $status) => match ($status) {
            0       => 'bg-violet-50 text-violet-700 ring-violet-600/20',
            1       => 'bg-sky-50 text-sky-700 ring-sky-600/20',
            7, 11   => 'bg-rose-50 text-rose-700 ring-rose-600/20',
            12      => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            default => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        };
    @endphp

    <div x-data="{ confirm: null, selected: [] }">
        <div class="mb-6">
            <h2 class="font-display text-2xl font-semibold tracking-tight text-ink">Personalized SMS</h2>
            <p class="mt-1 text-sm text-muted">{{ number_format($pendingTotal) }} messages in {{ number_format(count($campaigns)) }} campaigns are waiting for approval</p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-line bg-white">
            {{-- Tabs --}}
            <nav class="flex gap-1 overflow-x-auto border-b border-line px-4" aria-label="View">
                @foreach (['pending' => 'Waiting approval', 'all' => 'All messages'] as $key => $label)
                    @php $active = $tab === $key; @endphp
                    <a href="{{ route('personalized.index', $key === 'all' ? ['tab' => 'all'] : []) }}" @class([
                        'relative flex shrink-0 items-center gap-2 px-3 py-3.5 text-sm font-medium transition',
                        'text-ink after:absolute after:inset-x-2 after:-bottom-px after:h-0.5 after:rounded-full after:bg-navy-900' => $active,
                        'text-muted hover:text-ink' => ! $active,
                    ])>
                        {{ $label }}
                        @if ($key === 'pending')
                            <span @class(['rounded-full px-2 py-0.5 text-xs tabular-nums', 'bg-navy-900 text-white' => $active, 'bg-surface text-muted' => ! $active])>{{ number_format(count($campaigns)) }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            @if ($errors->has('messages'))
                <p class="border-b border-line bg-danger/5 px-4 py-2.5 text-sm text-danger">{{ $errors->first('messages') }}</p>
            @endif

            @if ($tab === 'pending' && ! $campId)
                {{-- Campaigns waiting for approval --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface/60 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                            <tr>
                                <th class="px-4 py-3">Campaign</th>
                                <th class="px-4 py-3">User</th>
                                <th class="px-4 py-3">Sender</th>
                                <th class="px-4 py-3">Scheduled</th>
                                <th class="px-4 py-3 text-right">Messages</th>
                                <th class="px-4 py-3 text-right">Credits</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($campaigns as $row)
                                @php $item = ['id' => (int) $row->camp_id, 'name' => (string) ($row->camp_name ?: 'Campaign'), 'count' => (int) $row->pending]; @endphp
                                <tr class="transition hover:bg-surface/50">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('personalized.index', ['camp' => $row->camp_id]) }}" class="block max-w-56 truncate font-semibold text-ink hover:underline">{{ $row->camp_name ?: 'Campaign' }}</a>
                                        <p class="text-xs text-muted">#{{ $row->camp_id }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <p class="font-medium text-ink">{{ $row->username }}</p>
                                        <p class="text-xs text-muted">#{{ $row->user_id }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-ink">{{ $row->sender }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 tabular-nums text-muted">{{ $time($row->camp_time) }}</td>
                                    <td class="px-4 py-3 text-right font-medium tabular-nums text-ink">{{ number_format((int) $row->pending) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-muted">{{ number_format((int) $row->credits) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('personalized.index', ['camp' => $row->camp_id]) }}" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-muted transition hover:bg-surface hover:text-ink">Review</a>
                                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-navy-900 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-navy-800"
                                                    @click="confirm = { ...@js($item), action: 'approve' }">
                                                <x-heroicon-o-check-badge class="size-4" /> Approve all
                                            </button>
                                            <button type="button" class="grid size-8 place-items-center rounded-lg text-muted transition hover:bg-surface hover:text-danger" title="Suspend all"
                                                    @click="confirm = { ...@js($item), action: 'suspend' }">
                                                <x-heroicon-o-no-symbol class="size-4.5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-16 text-center">
                                        <x-heroicon-o-check-badge class="mx-auto size-10 text-muted/50" />
                                        <p class="mt-3 font-medium text-ink">Nothing is waiting for approval</p>
                                        <p class="mt-1 text-sm text-muted">New personalized campaigns from approval accounts appear here.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                {{-- Campaign header (review one campaign) or search (all messages) --}}
                @if ($campId)
                    <div class="flex flex-col gap-3 border-b border-line p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-3">
                            <a href="{{ route('personalized.index') }}" class="grid size-8 place-items-center rounded-lg text-muted transition hover:bg-surface hover:text-ink" title="Back">
                                <x-heroicon-o-arrow-left class="size-4.5" />
                            </a>
                            <div>
                                <p class="font-semibold text-ink">{{ $campaign->camp_name ?? 'Campaign' }} <span class="font-normal text-muted">#{{ $campId }}</span></p>
                                @if ($campaign)
                                    <p class="text-xs text-muted">{{ $campaign->username }} · {{ $campaign->sender }} · {{ number_format((int) $campaign->pending) }} waiting · {{ $time($campaign->camp_time) }}</p>
                                @endif
                            </div>
                        </div>
                        @if ($campaign)
                            @php $item = ['id' => (int) $campId, 'name' => (string) ($campaign->camp_name ?: 'Campaign'), 'count' => (int) $campaign->pending]; @endphp
                            <div class="flex gap-2">
                                <button type="button" class="rounded-lg px-3 py-2 text-sm font-medium text-danger transition hover:bg-danger/5" @click="confirm = { ...@js($item), action: 'suspend' }">Suspend all</button>
                                <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-navy-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-navy-800" @click="confirm = { ...@js($item), action: 'approve' }">
                                    <x-heroicon-o-check-badge class="size-4" /> Approve all
                                </button>
                            </div>
                        @endif
                    </div>
                @else
                    <form method="GET" action="{{ route('personalized.index') }}" class="flex flex-col gap-3 border-b border-line p-4 lg:flex-row lg:items-center">
                        <input type="hidden" name="tab" value="all">
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
                            <a href="{{ route('personalized.index', ['tab' => 'all']) }}" class="inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink">
                                <x-heroicon-m-arrow-path class="size-4" /> Reset
                            </a>
                        @endif
                    </form>
                @endif

                {{-- Messages --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface/60 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                            <tr>
                                @if ($selectable)
                                    <th class="w-10 px-4 py-3">
                                        <input type="checkbox" class="size-4 accent-navy-900" aria-label="Select all"
                                               :checked="selected.length && selected.length === @js(count($pageIds))"
                                               @change="selected = $event.target.checked ? @js($pageIds) : []">
                                    </th>
                                @endif
                                <th class="px-4 py-3">ID</th>
                                @unless ($campId)
                                    <th class="px-4 py-3">Campaign</th>
                                    <th class="px-4 py-3">User</th>
                                @endunless
                                <th class="px-4 py-3">Sender</th>
                                <th class="px-4 py-3">Receiver</th>
                                <th class="px-4 py-3">Message</th>
                                <th class="px-4 py-3">Scheduled</th>
                                <th class="px-4 py-3 text-right">Cost</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($messages['rows'] ?? [] as $row)
                                <tr class="transition hover:bg-surface/50" @if ($selectable) :class="selected.includes({{ (int) $row->id }}) && 'bg-navy-900/[0.03]'" @endif>
                                    @if ($selectable)
                                        <td class="px-4 py-3">
                                            <input type="checkbox" value="{{ $row->id }}" x-model.number="selected" class="size-4 accent-navy-900" aria-label="Select message {{ $row->id }}">
                                        </td>
                                    @endif
                                    <td class="whitespace-nowrap px-4 py-3 tabular-nums text-muted">{{ $row->id }}</td>
                                    @unless ($campId)
                                        <td class="whitespace-nowrap px-4 py-3 tabular-nums text-muted">#{{ $row->camp_id }}</td>
                                        <td class="whitespace-nowrap px-4 py-3">
                                            <p class="font-medium text-ink">{{ $row->username }}</p>
                                            <p class="text-xs text-muted">#{{ $row->user_id }}</p>
                                        </td>
                                    @endunless
                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-ink">{{ $row->sender }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 tabular-nums text-ink">{{ $row->receiver }}</td>
                                    <td class="min-w-72 max-w-md px-4 py-3 text-ink" dir="auto">
                                        <p class="line-clamp-2" title="{{ $row->msgdata }}">{{ $row->msgdata }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 tabular-nums text-muted">{{ $time($row->senttime) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-ink">{{ (int) $row->cost }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $statusStyle((int) $row->status) }}">{{ $statuses[(int) $row->status] ?? 'Sent' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-16 text-center">
                                        <x-heroicon-o-chat-bubble-left-right class="mx-auto size-10 text-muted/50" />
                                        <p class="mt-3 font-medium text-ink">No messages found</p>
                                        <p class="mt-1 text-sm text-muted">{{ $campId ? 'This campaign has nothing waiting for approval.' : 'Try a different search.' }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($page > 1 || ($messages['hasNext'] ?? false))
                    <div class="flex items-center justify-between border-t border-line px-4 py-3 text-sm text-muted">
                        <p>Page <span class="font-medium text-ink">{{ $page }}</span></p>
                        <div class="flex gap-2">
                            @if ($page > 1)
                                <a href="{{ $pageUrl($page - 1) }}" class="rounded-lg border border-line px-3 py-1.5 font-medium text-ink transition hover:bg-surface">Previous</a>
                            @endif
                            @if ($messages['hasNext'] ?? false)
                                <a href="{{ $pageUrl($page + 1) }}" class="rounded-lg border border-line px-3 py-1.5 font-medium text-ink transition hover:bg-surface">Next</a>
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </div>

        {{-- Bulk bar for selected messages --}}
        @if ($selectable)
            <div x-show="selected.length" x-cloak x-transition class="fixed inset-x-0 bottom-6 z-40 flex justify-center px-4">
                <form method="POST" action="{{ route('personalized.action') }}" class="flex items-center gap-3 rounded-2xl bg-navy-950 px-4 py-3 text-sm text-white shadow-xl shadow-navy-950/30"
                      x-data="{ busy: false, action: 'approve' }" @submit="busy = true" :class="busy && 'pointer-events-none opacity-70'">
                    @csrf
                    <input type="hidden" name="action" :value="action">
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="ids[]" :value="id">
                    </template>
                    <span><span class="font-semibold tabular-nums" x-text="selected.length"></span> selected</span>
                    <button type="button" class="rounded-lg px-3 py-1.5 font-medium text-white/70 transition hover:text-white" @click="selected = []">Clear</button>
                    <button type="submit" class="rounded-lg px-3 py-1.5 font-medium text-rose-300 transition hover:bg-white/10" @click="action = 'suspend'">Suspend</button>
                    <button type="submit" class="rounded-lg bg-white px-3 py-1.5 font-semibold text-navy-950 transition hover:bg-white/90" @click="action = 'approve'">Approve</button>
                </form>
            </div>
        @endif

        {{-- Confirm modal for a whole campaign --}}
        <div x-show="confirm" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="confirm = null">
            <div x-show="confirm" x-transition.opacity.duration.200ms class="absolute inset-0 bg-navy-950/50 backdrop-blur-[2px]" @click="confirm = null"></div>

            <template x-if="confirm">
                <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl shadow-navy-950/20" x-data="{ busy: false }">
                    <h3 class="font-display text-lg font-semibold" x-text="confirm.action === 'approve' ? 'Approve campaign?' : 'Suspend campaign?'"></h3>
                    <p class="mt-1 truncate text-sm text-muted" x-text="'#' + confirm.id + ' · ' + confirm.name"></p>
                    <p class="mt-3 text-sm text-muted" x-show="confirm.action === 'approve'">
                        <span class="font-medium text-ink" x-text="confirm.count.toLocaleString()"></span> messages are released for sending.
                    </p>
                    <p class="mt-3 text-sm text-muted" x-show="confirm.action === 'suspend'">
                        <span class="font-medium text-ink" x-text="confirm.count.toLocaleString()"></span> messages will not be sent.
                    </p>

                    <form method="POST" action="{{ route('personalized.action') }}" class="mt-5 flex justify-end gap-2" @submit="busy = true">
                        @csrf
                        <input type="hidden" name="action" :value="confirm.action">
                        <input type="hidden" name="camp_id" :value="confirm.id">
                        <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink" @click="confirm = null">Cancel</button>
                        <button type="submit" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition disabled:opacity-60"
                                :class="confirm.action === 'suspend' ? 'bg-danger hover:opacity-90' : 'bg-navy-900 hover:bg-navy-800'"
                                :disabled="busy" x-text="confirm.action === 'approve' ? 'Approve all' : 'Suspend all'"></button>
                    </form>
                </div>
            </template>
        </div>
    </div>
@endsection