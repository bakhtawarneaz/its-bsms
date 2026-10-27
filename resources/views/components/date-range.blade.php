{{--
    Dashboard-style date range field for forms.
    Usage: <x-date-range :from="$from" :to="$to" />  (posts "from" and "to" as Y-m-d)
--}}
@props([
    'from',
    'to',
    'nameFrom' => 'from',
    'nameTo' => 'to',
    'max' => null,
    'label' => 'Date range',
])

@php
    $today = now()->startOfDay();
    $presets = [
        ['Today', $today->toDateString(), $today->toDateString()],
        ['Yesterday', $today->copy()->subDay()->toDateString(), $today->copy()->subDay()->toDateString()],
        ['Last 7 days', $today->copy()->subDays(6)->toDateString(), $today->toDateString()],
        ['Last 30 days', $today->copy()->subDays(29)->toDateString(), $today->toDateString()],
        ['This month', $today->copy()->startOfMonth()->toDateString(), $today->toDateString()],
        ['Last month', $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
    ];
@endphp

<div {{ $attributes->class('relative') }}
     x-data="dateRangeField(@js(['from' => $from, 'to' => $to, 'maxDate' => $max ?? $today->toDateString()]))"
     @keydown.escape.window="open = false"
     @resize.window="open && place()"
     @scroll.window="open && place()">
    @if ($label)
        <span class="field-label">{{ $label }}</span>
    @endif

    <input type="hidden" name="{{ $nameFrom }}" value="{{ $from }}" :value="from">
    <input type="hidden" name="{{ $nameTo }}" value="{{ $to }}" :value="to">

    <button type="button" x-ref="trigger" @click="toggle()" :aria-expanded="open"
            class="field-input flex w-full items-center gap-2.5 text-left">
        <x-heroicon-o-calendar-days class="size-5 shrink-0 text-muted" />
        <span class="flex-1 truncate" x-text="label">{{ $from }} – {{ $to }}</span>
        <x-heroicon-m-chevron-down class="size-4 shrink-0 text-muted" />
    </button>

    <div x-ref="panel" x-show="open" x-cloak :style="panelStyle"
         @click.outside="if (! $refs.trigger.contains($event.target)) open = false"
         x-transition:enter="transition duration-150 ease-out"
         x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition duration-100 ease-in"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed z-[60] flex max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-line bg-white shadow-xl shadow-navy-950/10 md:flex-row">
        <ul class="flex gap-1 overflow-x-auto border-b border-line p-2 md:w-44 md:flex-col md:border-r md:border-b-0">
            @foreach ($presets as [$presetLabel, $presetFrom, $presetTo])
                <li>
                    <button type="button" @click="choose(@js($presetFrom), @js($presetTo))"
                            class="flex w-full items-center rounded-lg px-3 py-2 text-left text-sm whitespace-nowrap transition"
                            :class="from === @js($presetFrom) && to === @js($presetTo) ? 'bg-navy-900 font-medium text-white' : 'text-ink hover:bg-surface'">
                        {{ $presetLabel }}
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="p-3">
            <div x-ref="calendar"></div>
            <div class="mt-2 flex items-center justify-between gap-4 border-t border-line px-1 pt-3">
                <p class="text-sm text-muted" x-text="selectionLabel"></p>
                <div class="flex items-center gap-2">
                    <button type="button" class="rounded-lg px-3 py-2 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink" @click="open = false">Cancel</button>
                    <button type="button" class="rounded-lg bg-navy-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="! canApply" @click="apply()">Apply</button>
                </div>
            </div>
        </div>
    </div>
</div>