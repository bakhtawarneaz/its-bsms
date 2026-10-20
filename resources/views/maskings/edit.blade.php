@extends('layouts.app')

@section('title', 'Edit routes')

@section('content')
    @php
        $initialRoutes = [];
        foreach ($networks as $key => $network) {
            $initialRoutes[$key] = (string) old("routes.{$key}", isset($current[$key]) ? $current[$key]['route'] : '');
        }
        $promotionalOn = (bool) old('promotional', $promotional);
        $card = 'rounded-2xl border border-line bg-white';
    @endphp

    <div class="mx-auto max-w-6xl">
        <a href="{{ route('maskings.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted transition hover:text-ink">
            <x-heroicon-m-arrow-left class="size-4" />
            Back to maskings
        </a>

        {{-- Identity --}}
        <div class="mb-6 flex items-center gap-4">
            <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-navy-900 text-white">
                <x-heroicon-o-identification class="size-7" />
            </span>
            <div class="min-w-0">
                <h2 class="truncate font-display text-2xl font-semibold tracking-tight">{{ trim($masking->masking) }}</h2>
                <p class="mt-1 text-sm text-muted">
                    #{{ $masking->id }}
                    @if ($masking->crtime) · Added {{ \Illuminate\Support\Carbon::parse($masking->crtime)->format('d M Y') }} @endif
                    @unless ($current) · <span class="font-medium text-warning">No routes yet</span> @endunless
                </p>
            </div>
        </div>

        <form
            method="POST"
            action="{{ route('maskings.update', $masking->id) }}"
            autocomplete="off"
            class="grid items-start gap-6 lg:grid-cols-3"
            x-data="{
                routes: @js($initialRoutes),
                loading: false,
                setAll(value) { Object.keys(this.routes).forEach(key => this.routes[key] = String(value)) },
            }"
            @submit="loading = true"
            @pageshow.window="loading = false"
        >
            @csrf
            @method('PUT')

            <div class="space-y-6 lg:col-span-2">
                <section class="{{ $card }}">
                    <header class="border-b border-line px-6 py-4">
                        <h3 class="font-semibold">Type</h3>
                        <p class="mt-0.5 text-sm text-muted">Applied to every network route of this masking</p>
                    </header>
                    <div class="p-6">
                        <label class="inline-flex cursor-pointer items-center gap-3">
                            <input type="checkbox" name="promotional" value="1" class="peer sr-only" @checked($promotionalOn)>
                            <span class="relative h-6 w-11 rounded-full bg-line transition peer-checked:bg-success peer-focus-visible:ring-4 peer-focus-visible:ring-navy-700/20 after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
                            <span class="text-sm text-muted">Promotional (off = transactional)</span>
                        </label>
                    </div>
                </section>

                @include('maskings.partials.routes')
            </div>

            {{-- Current state and actions --}}
            <aside class="space-y-6 lg:sticky lg:top-24">
                <section class="{{ $card }} p-5">
                    <h3 class="font-semibold">Current status</h3>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        @foreach ($networks as $key => $network)
                            @php $route = $current[$key] ?? null; @endphp
                            <li class="flex items-center justify-between gap-3">
                                <span class="flex items-center gap-2">
                                    <span class="size-2.5 rounded-full {{ $network['dot'] }}"></span>
                                    {{ $network['label'] }}
                                </span>
                                @if (! $route)
                                    <span class="text-xs text-muted">No route</span>
                                @elseif ($route['route'] === 0)
                                    <span class="rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">Blocked</span>
                                @elseif ($route['status'] === 1)
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                                @else
                                    <span class="rounded-full bg-surface px-2 py-0.5 text-xs font-medium text-muted">Off</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-4 flex gap-2 rounded-lg bg-amber-50 px-3 py-2.5 text-xs leading-relaxed text-amber-800">
                        <x-heroicon-o-exclamation-triangle class="size-4 shrink-0" />
                        Saving routes turns every network off. Turn them back on from Change status on the maskings list.
                    </p>
                </section>

                <div class="{{ $card }} flex flex-col gap-2 p-4">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-navy-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:opacity-70" :disabled="loading">
                        <svg x-show="loading" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                        <span x-text="loading ? 'Saving…' : 'Save routes'">Save routes</span>
                    </button>
                    <a href="{{ route('maskings.index') }}" class="rounded-lg px-5 py-2.5 text-center text-sm font-medium text-muted transition hover:bg-surface hover:text-ink">Cancel</a>
                </div>
            </aside>
        </form>
    </div>
@endsection