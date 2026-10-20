@extends('layouts.app')

@section('title', 'Add masking')

@section('content')
    @php
        // Default each network to its first option, like the legacy form.
        $initialRoutes = [];
        foreach ($networks as $key => $network) {
           $initialRoutes[$key] = (string) old("routes.{$key}", '');
        }
        $promotionalOn = (bool) old('promotional', false);
        $card = 'rounded-2xl border border-line bg-white';
    @endphp

    <div class="mx-auto max-w-6xl">
        <a href="{{ route('maskings.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted transition hover:text-ink">
            <x-heroicon-m-arrow-left class="size-4" />
            Back to maskings
        </a>

        <form
            method="POST"
            action="{{ route('maskings.store') }}"
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

            <div class="space-y-6 lg:col-span-2">
                {{-- Maskings --}}
                <section class="{{ $card }}">
                    <header class="border-b border-line px-6 py-4">
                        <h3 class="font-semibold">Masking</h3>
                        <p class="mt-0.5 text-sm text-muted">Sender IDs to add with the same routes</p>
                    </header>

                    <div class="space-y-5 p-6">
                        <div>
                            <label for="masks" class="field-label">Maskings</label>
                            <textarea id="masks" name="masks" rows="3" class="field-input resize-y" placeholder="ITS-ALERT, ITS-OTP" required @error('masks') aria-invalid="true" @enderror>{{ old('masks', request('masks')) }}</textarea>
                            <p class="field-hint">Separate multiple maskings with commas or new lines. Existing ones are skipped.</p>
                        </div>

                        <div>
                            <span class="field-label">Type</span>
                            <label class="inline-flex cursor-pointer items-center gap-3">
                                <input type="checkbox" name="promotional" value="1" class="peer sr-only" @checked($promotionalOn)>
                                <span class="relative h-6 w-11 rounded-full bg-line transition peer-checked:bg-success peer-focus-visible:ring-4 peer-focus-visible:ring-navy-700/20 after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
                                <span class="text-sm text-muted">Promotional (off = transactional)</span>
                            </label>
                        </div>
                    </div>
                </section>

                @include('maskings.partials.routes')
            </div>

            {{-- Summary and actions --}}
            <aside class="space-y-6 lg:sticky lg:top-24">
                <section class="{{ $card }} p-5">
                    <h3 class="font-semibold">Before you add</h3>
                    <ul class="mt-3 space-y-2.5 text-sm text-muted">
                        <li class="flex gap-2"><x-heroicon-o-clock class="mt-0.5 size-4 shrink-0" /> New maskings start inactive on every network.</li>
                        <li class="flex gap-2"><x-heroicon-o-signal class="mt-0.5 size-4 shrink-0" /> Turn networks on from masking status once approved.</li>
                        <li class="flex gap-2"><x-heroicon-o-user-plus class="mt-0.5 size-4 shrink-0" /> Assign maskings to users from Add or Edit user.</li>
                    </ul>
                </section>

                <div class="{{ $card }} flex flex-col gap-2 p-4">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-navy-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:opacity-70" :disabled="loading">
                        <svg x-show="loading" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                        <span x-text="loading ? 'Adding…' : 'Add masking'">Add masking</span>
                    </button>
                    <a href="{{ route('maskings.index') }}" class="rounded-lg px-5 py-2.5 text-center text-sm font-medium text-muted transition hover:bg-surface hover:text-ink">Cancel</a>
                </div>
            </aside>
        </form>
    </div>
@endsection