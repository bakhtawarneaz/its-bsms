@props([
    'label',
    'value',
    'decimals' => 0,
    'suffix' => '',
    'change' => null,
    'changeSuffix' => '%',
    'goodWhenUp' => true,
    'spark' => [],
    'color' => '#2a78d6',
    'hint' => null,
    'hero' => false,
])

@php
    $hasChange = ! is_null($change);
    $up = $hasChange && $change >= 0;
    $good = $hasChange && ($goodWhenUp ? $up : ! $up);
@endphp

<div @class([
    'relative flex flex-col overflow-hidden rounded-2xl p-5',
    'bg-navy-900 text-white shadow-lg shadow-navy-900/20' => $hero,
    'border border-line bg-white' => ! $hero,
])>
    <p @class(['text-sm font-medium', 'text-white/70' => $hero, 'text-muted' => ! $hero])>{{ $label }}</p>

    <p
        class="mt-2 font-display text-[32px] leading-none font-semibold tracking-tight tabular-nums"
        x-data="countUp({{ $value }}, {{ $decimals }}, @js($suffix))"
        x-text="display"
    >{{ number_format($value, $decimals) }}{{ $suffix }}</p>

    <div class="mt-3 flex min-h-6 items-center gap-2 text-xs">
        @if ($hasChange)
            <span @class([
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold tabular-nums',
                'bg-white/10 text-emerald-300' => $hero && $good,
                'bg-white/10 text-red-300' => $hero && ! $good,
                'bg-success/10 text-success' => ! $hero && $good,
                'bg-danger/10 text-danger' => ! $hero && ! $good,
            ])>
                @if ($up)
                    <x-heroicon-m-arrow-trending-up class="size-3.5" />
                @else
                    <x-heroicon-m-arrow-trending-down class="size-3.5" />
                @endif
                {{ abs($change) }}{{ $changeSuffix }}
            </span>
            <span @class(['text-white/60' => $hero, 'text-muted' => ! $hero])>vs previous period</span>
        @elseif ($hint)
            <span @class(['text-white/60' => $hero, 'text-muted' => ! $hero])>{{ $hint }}</span>
        @endif
    </div>

    @if ($spark)
        <div class="-mx-5 -mb-5 mt-4" x-data="sparkline(@js(['values' => $spark, 'color' => $color]))"></div>
    @endif
</div>