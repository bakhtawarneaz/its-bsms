@extends('layouts.app')

@section('title', 'Gateway mask routes')

@section('content')
    @php
        $panel = 'rounded-2xl border border-line bg-white shadow-sm';
        $hasExtraSpace = $mask !== null && $mask !== trim($mask);
    @endphp

    <div>
        <a href="{{ route('gateway.users') }}" class="inline-flex items-center gap-1 text-sm text-muted transition hover:text-ink">
            <x-heroicon-m-arrow-left class="size-4" /> Gateway users
        </a>
        <h2 class="mt-2 font-display text-2xl font-semibold tracking-tight text-ink">Gateway mask routes</h2>
        <p class="mt-1 text-sm text-muted">Pick a mask, then a user, to change its route and rate on each network.</p>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Mask picker --}}
        <section class="{{ $panel }} flex max-h-[42rem] flex-col"
                 x-data="{
                     q: '',
                     masks: @js(collect($masks)->map(fn ($m) => ['mask' => $m->mask, 'key' => bin2hex($m->mask), 'users' => (int) $m->users])->values()),
                     current: @js($maskKey),
                     base: @js(route('gateway.mask-routes')),
                     get filtered() {
                         const q = this.q.trim().toLowerCase();
                         return (q ? this.masks.filter(m => m.mask.toLowerCase().includes(q)) : this.masks).slice(0, 300);
                     },
                 }">
            <header class="border-b border-line px-5 py-4">
                <h3 class="font-semibold">Masks</h3>
                <div class="relative mt-3">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
                    <input type="search" x-model="q" placeholder="Search {{ number_format(count($masks)) }} masks" class="field-input pl-9">
                </div>
            </header>

            <ul class="flex-1 divide-y divide-line overflow-y-auto">
                <template x-for="item in filtered" :key="item.key">
                    <li>
                        <a :href="base + '?m=' + item.key"
                           class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm transition hover:bg-surface/60"
                           :class="item.key === current ? 'bg-surface font-semibold text-ink' : 'text-ink'">
                            <span class="flex min-w-0 items-center gap-2">
                                <span class="truncate" x-text="item.mask"></span>
                                <span x-show="item.mask !== item.mask.trim()" class="shrink-0 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">extra space</span>
                            </span>
                            <span class="shrink-0 rounded-full bg-surface px-2 py-0.5 text-xs tabular-nums text-muted" x-text="item.users + (item.users === 1 ? ' user' : ' users')"></span>
                        </a>
                    </li>
                </template>
                <li x-show="filtered.length === 0" class="px-5 py-10 text-center text-sm text-muted">No mask matches.</li>
            </ul>
        </section>

        <div class="space-y-6 lg:col-span-2">
            @if (! $mask)
                <section class="{{ $panel }} grid place-items-center px-6 py-20 text-center">
                    <x-heroicon-o-signal class="size-10 text-muted" />
                    <p class="mt-3 font-medium text-ink">Select a mask</p>
                    <p class="mt-1 text-sm text-muted">Its gateway users and routes will show here.</p>
                </section>
            @else
                {{-- Users of the mask --}}
                <section class="{{ $panel }}">
                    <header class="border-b border-line px-6 py-4">
                        <h3 class="flex flex-wrap items-center gap-2 font-semibold">
                            Users of <span class="whitespace-pre text-navy-900">"{{ $mask }}"</span>
                            @if ($hasExtraSpace)
                                <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">extra space</span>
                            @endif
                        </h3>
                        <p class="mt-0.5 text-sm text-muted">{{ count($users) }} gateway {{ \Illuminate\Support\Str::plural('user', count($users)) }} {{ count($users) === 1 ? 'uses' : 'use' }} this mask</p>
                    </header>

                    @if ($users)
                        <div class="flex flex-wrap gap-2 p-5">
                            @foreach ($users as $user)
                                @php($active = $selected && (int) $selected->user_id === (int) $user->user_id)
                                <a href="{{ route('gateway.mask-routes', ['m' => $maskKey, 'user' => $user->user_id]) }}"
                                   class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition {{ $active ? 'border-navy-900 bg-navy-900 text-white' : 'border-line text-ink hover:border-navy-900/40' }}">
                                    <x-heroicon-o-user class="size-4" />
                                    {{ $user->username }}
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="px-6 py-10 text-center text-sm text-muted">No gateway users use this mask.</p>
                    @endif
                </section>

                {{-- Routes of the selected user --}}
                @if ($selected)
                    <form method="POST" action="{{ route('gateway.mask-routes.update') }}" class="{{ $panel }}"
                          x-data="{ busy: false }" @submit="busy = true">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="mask" value="{{ $maskKey }}">
                        <input type="hidden" name="user_id" value="{{ $selected->user_id }}">

                        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4">
                            <div>
                                <h3 class="font-semibold">Routes for {{ $selected->username }}</h3>
                                <p class="mt-0.5 text-sm text-muted">Choosing Block switches that network off.</p>
                            </div>

                            <label class="inline-flex cursor-pointer items-center gap-3">
                                <input type="checkbox" name="promotional" value="1" class="peer sr-only" @checked(old('promotional', $promotional))>
                                <span class="relative h-6 w-11 rounded-full bg-line transition after:absolute after:left-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:bg-success peer-checked:after:translate-x-5"></span>
                                <span class="text-sm font-medium text-ink">Promotional</span>
                            </label>
                        </header>

                        @if ($errors->any())
                            <div class="mx-6 mt-5 rounded-lg border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <div class="grid gap-4 p-6 sm:grid-cols-2">
                            @foreach ($networks as $key => $network)
                                @if ($network['id'])
                                    @php($value = old("routes.$key.route", $network['current']))
                                    <div class="rounded-xl border border-line p-4" x-data="{ value: @js($value), initial: @js($network['current']) }">
                                        <input type="hidden" name="routes[{{ $key }}][id]" value="{{ $network['id'] }}">

                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-semibold text-ink">{{ $network['label'] }}</span>
                                            <span x-show="value === '0,block'" x-cloak class="rounded bg-danger/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-danger">Blocked</span>
                                            <span x-show="value !== initial && value !== '0,block'" x-cloak class="rounded bg-brand/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-brand">Changed</span>
                                        </div>

                                        <div class="mt-3 grid grid-cols-[1fr_6.5rem] gap-3">
                                            <div>
                                                <label class="field-label" for="route-{{ $key }}">Route</label>
                                                <select id="route-{{ $key }}" name="routes[{{ $key }}][route]" x-model="value" class="field-input">
                                                    @foreach ($network['options'] as $optionValue => $optionLabel)
                                                        <option value="{{ $optionValue }}" @selected($optionValue === $value)>{{ $optionLabel }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="field-label" for="rate-{{ $key }}">Rate</label>
                                                <input id="rate-{{ $key }}" type="number" name="routes[{{ $key }}][rate]" min="1" max="9999.99" step="0.01"
                                                       value="{{ old("routes.$key.rate", $network['rate']) }}" class="field-input tabular-nums">
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="grid place-items-center rounded-xl border border-dashed border-line p-4 text-center">
                                        <span class="text-sm font-semibold text-ink">{{ $network['label'] }}</span>
                                        <span class="mt-1 text-xs text-muted">Not set for this user</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        <footer class="flex justify-end gap-2 border-t border-line px-6 py-4">
                            <a href="{{ route('gateway.mask-routes', ['m' => $maskKey, 'user' => $selected->user_id]) }}"
                               class="rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink">Reset</a>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:opacity-60" :disabled="busy">
                                <svg x-show="busy" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                                <span x-text="busy ? 'Saving…' : 'Save routes'">Save routes</span>
                            </button>
                        </footer>
                    </form>
                @elseif ($users)
                    <section class="{{ $panel }} px-6 py-12 text-center text-sm text-muted">Select a user above to edit their routes.</section>
                @endif
            @endif
        </div>
    </div>
@endsection