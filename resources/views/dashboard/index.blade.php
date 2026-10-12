@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $firstName = \Illuminate\Support\Str::of($user['fullname'] ?: $user['username'])->before(' ');
        $isPostpaid = str_contains(strtolower((string) ($user['account_type'] ?? '')), 'post');
        $expiry = $user['user_expiry'] ? \Carbon\Carbon::parse($user['user_expiry']) : null;
        $panel = 'rounded-2xl border border-line bg-white p-5';
        $spinner = '<svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>';
    @endphp

    {{-- Greeting and period --}}
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="font-display text-2xl font-semibold tracking-tight">{{ $greeting }}, {{ $firstName }}</h2>
            <div class="mt-1.5 flex flex-wrap items-center gap-2 text-sm text-muted">
                <span>{{ $scopeLabel }}</span>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white px-2.5 py-0.5 text-xs font-medium text-ink">
                    <span class="size-1.5 rounded-full bg-success"></span>
                    {{ $isPostpaid ? 'Postpaid' : number_format((int) $user['balance']) . ' credits' }}@if ($expiry), expires {{ $expiry->format('j M Y') }}@endif
                </span>
            </div>
        </div>

        {{-- Date range picker --}}
        <div
            class="relative flex items-center gap-2"
            x-data="dateRange(@js([
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'maxDate' => now()->toDateString(),
                'maxDays' => \App\Http\Controllers\DashboardController::MAX_RANGE_DAYS,
                'baseUrl' => route('dashboard'),
            ]))"
            @keydown.escape.window="open = false"
            @pageshow.window="loading = false; loadingTarget = null"
        >
            @if ($range !== '30d')
                <button
                    type="button"
                    class="grid size-10 place-items-center rounded-xl border border-line bg-white text-muted shadow-xs transition hover:border-navy-700/40 hover:text-ink disabled:opacity-50"
                    @click="reset()"
                    :disabled="loading"
                    aria-label="Reset to last 30 days"
                    title="Reset to last 30 days"
                >
                    <span x-show="loading && loadingTarget === '30d'" x-cloak>{!! $spinner !!}</span>
                    <span x-show="!(loading && loadingTarget === '30d')"><x-heroicon-m-x-mark class="size-4" /></span>
                </button>
            @endif

            <button
                type="button"
                class="inline-flex items-center gap-2.5 rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm shadow-xs transition hover:border-navy-700/40"
                @click="open = !open"
                :aria-expanded="open"
            >
                <x-heroicon-o-calendar-days class="size-5 text-muted" />
                <span class="font-semibold">{{ $rangeLabel }}</span>
                <span class="hidden text-muted sm:inline">{{ $from->format('j M') }} – {{ $to->format('j M Y') }}</span>
                <x-heroicon-m-chevron-down class="size-4 text-muted" />
            </button>

            <div
                x-show="open" x-cloak
                @click.outside="open = false"
                x-transition:enter="transition duration-150 ease-out"
                x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition duration-100 ease-in"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute top-full right-0 z-30 mt-2 flex max-w-[calc(100vw-2rem)] origin-top-right flex-col overflow-hidden rounded-2xl border border-line bg-white shadow-xl shadow-navy-950/10 md:flex-row"
            >
                <ul class="flex gap-1 overflow-x-auto border-b border-line p-2 md:w-44 md:flex-col md:border-r md:border-b-0">
                    @foreach ($presets as $key => $label)
                        <li>
                            <button
                                type="button"
                                @click="choose(@js($key))"
                                :disabled="loading"
                                @class([
                                    'flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm whitespace-nowrap transition disabled:cursor-wait',
                                    'bg-navy-900 font-medium text-white' => $range === $key,
                                    'text-ink hover:bg-surface' => $range !== $key,
                                ])
                            >
                                {{ $label }}
                                <span x-show="loading && loadingTarget === @js($key)" x-cloak>{!! $spinner !!}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>

                <div class="p-3">
                    <div x-ref="calendar"></div>
                    <div class="mt-2 flex items-center justify-between gap-4 border-t border-line px-1 pt-3">
                        <p class="text-sm text-muted" x-text="selectionLabel"></p>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-lg px-3 py-2 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink disabled:opacity-50"
                                @click="reset()"
                                :disabled="loading"
                            >Reset</button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg bg-navy-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="!canApply || loading"
                                @click="apply()"
                            >
                                <span x-show="loading && loadingTarget === 'custom'" x-cloak>{!! $spinner !!}</span>
                                <span x-text="loading && loadingTarget === 'custom' ? 'Applying…' : 'Apply'">Apply</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (! $summary)
        <div class="{{ $panel }} grid place-items-center px-6 py-16 text-center">
            <x-heroicon-o-server-stack class="size-10 text-muted" />
            <h2 class="mt-4 font-display text-lg font-semibold">Gateway dashboard is on its way</h2>
            <p class="mt-1 max-w-md text-sm text-muted">Gateway accounts use a separate reporting database. Their dashboard arrives with the gateway module.</p>
        </div>
    @else
        @php
            $totals = $summary['totals'];
            $changes = $summary['changes'];
            $daily = $summary['daily'];
        @endphp

        {{-- KPI cards --}}
        <section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Key figures">
            <x-dashboard.kpi
                label="Messages sent" :value="$totals['messages']"
                :change="$changes['messages']" :spark="$daily['messages']"
                color="#F5C400" hero
            />
            <x-dashboard.kpi
                label="Delivery rate" :value="$totals['delivery_rate']" :decimals="1" suffix="%"
                :change="$changes['delivery_rate']" change-suffix=" pts" :spark="$daily['rate']"
                color="#0ca30c"
            />
            <x-dashboard.kpi
                label="Failed" :value="$totals['failed']"
                :change="$changes['failed']" :good-when-up="false" :spark="$daily['failed']"
                color="#d03b3b" :hint="$totals['failure_rate'] . '% of messages sent'"
            />
            <x-dashboard.kpi
                label="Credits used" :value="$totals['credits']"
                :change="$changes['credits']" :spark="$daily['credits']"
                color="#2a78d6" hint="SMS parts billed"
            />
        </section>

        @if ($totals['messages'] === 0)
            <div class="mb-6 flex items-center gap-3 rounded-2xl border border-line bg-white px-5 py-4 text-sm text-muted">
                <x-heroicon-o-information-circle class="size-5 shrink-0" />
                No messages were sent in this period. Try a wider date range.
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Daily trend --}}
            <section class="{{ $panel }} lg:col-span-2" x-data="trendChart(@js($daily))">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold">Daily activity</h3>
                        <p class="mt-0.5 text-sm text-muted">Messages per day in the selected period</p>
                    </div>
                    <div class="inline-flex rounded-lg bg-surface p-1" role="tablist" aria-label="Chart view">
                        <button type="button" role="tab" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="view === 'volume' ? 'bg-white text-ink shadow-xs' : 'text-muted hover:text-ink'" :aria-selected="view === 'volume'" @click="view = 'volume'">Volume</button>
                        <button type="button" role="tab" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="view === 'credits' ? 'bg-white text-ink shadow-xs' : 'text-muted hover:text-ink'" :aria-selected="view === 'credits'" @click="view = 'credits'">Credits</button>
                    </div>
                </div>
                <div class="mt-2 -ml-2" x-ref="canvas"></div>
            </section>

            {{-- Delivery gauge --}}
            <section class="{{ $panel }}">
                <h3 class="font-semibold">Delivery status</h3>
                <p class="mt-0.5 text-sm text-muted">Share of messages by final state</p>

                @if ($summary['statuses'])
                    <div class="-mt-2" x-data="gaugeChart(@js(['value' => $totals['delivery_rate'], 'label' => 'Delivered', 'color' => '#0ca30c']))"></div>

                    <ul class="-mt-6 space-y-2.5">
                        @foreach ($summary['statuses'] as $status)
                            <li class="flex items-center gap-3 text-sm">
                                <x-dynamic-component :component="'heroicon-s-' . $status['icon']" class="size-5 shrink-0" style="color: {{ $status['color'] }}" />
                                <span class="flex-1">{{ $status['label'] }}</span>
                                <span class="font-medium tabular-nums">{{ number_format($status['messages']) }}</span>
                                <span class="w-14 text-right text-muted tabular-nums">{{ $status['share'] }}%</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-6 text-sm text-muted">No messages in this period.</p>
                @endif
            </section>

            {{-- Networks --}}
            <section class="{{ $panel }} lg:col-span-2">
                <h3 class="font-semibold">Messages by network</h3>
                <p class="mt-0.5 text-sm text-muted">Volume, share of traffic and delivery rate per operator</p>

                <ul class="mt-6 space-y-5">
                    @foreach ($summary['operators'] as $operator)
                        <li>
                            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-sm">
                                <span class="flex items-center gap-2.5 font-semibold">
                                    <span class="size-3 rounded-[4px]" style="background: {{ $operator['color'] }}"></span>
                                    {{ $operator['name'] }}
                                    <span class="font-normal text-muted">{{ $operator['share'] }}% of traffic</span>
                                </span>
                                <span class="flex items-center gap-3 tabular-nums">
                                    <span class="font-semibold">{{ number_format($operator['messages']) }}</span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2 py-0.5 text-xs font-semibold text-success">
                                        <x-heroicon-s-check-circle class="size-3.5" />
                                        {{ $operator['delivery_rate'] }}%
                                    </span>
                                </span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-surface" x-data="meter({{ $operator['width'] }})">
                                <div class="h-full rounded-full transition-[width] duration-1000 ease-out" :style="`width: ${width}%; background: {{ $operator['color'] }}`"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- Channels donut --}}
            <section class="{{ $panel }}">
                <h3 class="font-semibold">Messages by channel</h3>
                <p class="mt-0.5 text-sm text-muted">How messages were submitted</p>

                @if ($summary['channels'])
                    <div class="mt-2" x-data="donutChart(@js([
                        'labels' => array_column($summary['channels'], 'name'),
                        'values' => array_column($summary['channels'], 'messages'),
                        'colors' => array_column($summary['channels'], 'color'),
                    ]))"></div>

                    <ul class="mt-3 space-y-2.5">
                        @foreach ($summary['channels'] as $channel)
                            <li class="flex items-center gap-3 text-sm">
                                <span class="size-3 shrink-0 rounded-[4px]" style="background: {{ $channel['color'] }}"></span>
                                <span class="flex-1 truncate">{{ $channel['name'] }}</span>
                                <span class="font-medium tabular-nums">{{ number_format($channel['messages']) }}</span>
                                <span class="w-14 text-right text-muted tabular-nums">{{ $channel['share'] }}%</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-6 text-sm text-muted">No messages in this period.</p>
                @endif
            </section>

            {{-- Monthly --}}
            <section class="{{ $panel }} lg:col-span-2">
                <h3 class="font-semibold">Last 12 months</h3>
                <p class="mt-0.5 text-sm text-muted">Messages sent per month, up to {{ $to->format('M Y') }}</p>
                <div class="mt-4 -ml-2" x-data="monthlyChart(@js($summary['monthly']))"></div>
            </section>

            {{-- Incoming --}}
            <section class="{{ $panel }}">
                <h3 class="font-semibold">Incoming SMS</h3>
                <p class="mt-0.5 text-sm text-muted">Replies received in this period</p>

                <p class="mt-5 font-display text-[32px] leading-none font-semibold tracking-tight tabular-nums" x-data="countUp({{ $summary['incoming']['total'] }})" x-text="display">{{ number_format($summary['incoming']['total']) }}</p>

                <ul class="mt-5 space-y-3.5">
                    @foreach ($summary['incoming']['operators'] as $operator)
                        <li>
                            <div class="mb-1.5 flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2">
                                    <span class="size-2.5 rounded-full" style="background: {{ $operator['color'] }}"></span>
                                    {{ $operator['name'] }}
                                </span>
                                <span class="font-medium tabular-nums">{{ number_format($operator['messages']) }}</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-surface" x-data="meter({{ $operator['share'] }})">
                                <div class="h-full rounded-full transition-[width] duration-1000 ease-out" :style="`width: ${width}%; background: {{ $operator['color'] }}`"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- Top sender IDs --}}
            <section @class([$panel, 'lg:col-span-3' => ! $summary['accounts'], 'lg:col-span-2' => $summary['accounts']])>
                <h3 class="font-semibold">Top sender IDs</h3>
                <p class="mt-0.5 text-sm text-muted">Masks and short codes with the most traffic</p>
                @include('dashboard.partials.ranking', ['items' => $summary['senders'], 'color' => '#1A2D52'])
            </section>

            {{-- Top accounts --}}
            @if ($summary['accounts'])
                <section class="{{ $panel }}">
                    <h3 class="font-semibold">Top accounts</h3>
                    <p class="mt-0.5 text-sm text-muted">Accounts that sent the most messages</p>
                    @include('dashboard.partials.ranking', ['items' => $summary['accounts'], 'color' => '#2a78d6'])
                </section>
            @endif
        </div>
    @endif
@endsection