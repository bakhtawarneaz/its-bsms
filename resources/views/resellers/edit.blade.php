@extends('layouts.app')

@section('title', 'Edit reseller')

@section('content')
    @php
        $colors = [
            'first'  => old('first_color', $branding['colors']['first']),
            'second' => old('second_color', $branding['colors']['second']),
            'third'  => old('third_color', $branding['colors']['third']),
            'fourth' => old('fourth_color', $branding['colors']['fourth']),
        ];
        $darkOn = old() ? (bool) old('dark_theme') : $branding['dark_theme'];
        $company = old('company_name', $branding['company_name']);
        $expiry = $reseller->user_expiry ? \Illuminate\Support\Carbon::parse($reseller->user_expiry) : null;
        $isPrepaid = strtolower(trim((string) $reseller->account_type)) === 'prepaid';
        $card = 'rounded-2xl border border-line bg-white';
    @endphp

    <div class="mx-auto max-w-6xl">
        <a href="{{ route('resellers.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted transition hover:text-ink">
            <x-heroicon-m-arrow-left class="size-4" />
            Back to resellers
        </a>

        {{-- Identity --}}
        <div class="mb-6 flex items-center gap-4">
            @if ($branding['logo_url'])
                <img src="{{ $branding['logo_url'] }}" alt="" class="size-14 shrink-0 rounded-2xl border border-line bg-white object-contain p-1.5">
            @else
                <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-navy-900 text-lg font-semibold uppercase text-white">
                    {{ mb_substr($company ?: $reseller->username, 0, 2) }}
                </span>
            @endif
            <div class="min-w-0">
                <h2 class="truncate font-display text-2xl font-semibold tracking-tight">{{ $company ?: $reseller->username }}</h2>
                <p class="mt-1 text-sm text-muted">#{{ $reseller->id }} · {{ $reseller->username }} · {{ number_format((int) $reseller->users_count) }} users</p>
            </div>
        </div>

        @if ($branding['missing'])
            <div class="mb-6 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <x-heroicon-o-exclamation-triangle class="size-5 shrink-0" />
                This reseller has no branding saved yet. Saving creates it with the values below.
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('resellers.update', $reseller->id) }}"
            enctype="multipart/form-data"
            autocomplete="off"
            class="grid items-start gap-6 lg:grid-cols-3"
            x-data="{
                loading: false,
                company: @js($company),
                slogan: @js(old('slogan', $branding['slogan'])),
                domain: @js($reseller->site_name),
                colors: @js($colors),
                dark: @js($darkOn),
                logo: @js($branding['logo_url']),
                logoName: '',
                pickLogo(event) {
                    const file = event.target.files[0];

                    if (file && file.size > 2 * 1024 * 1024) {
                        event.target.value = '';
                        alert('The logo must be 2 MB or smaller.');
                        return;
                    }

                    if (file) {
                        this.logo = URL.createObjectURL(file);
                        this.logoName = file.name;
                    }
                },
            }"
            @submit="loading = true"
            @pageshow.window="loading = false"
        >
            @csrf
            @method('PUT')

            <div class="space-y-6 lg:col-span-2">
                <section class="{{ $card }}">
                    <header class="border-b border-line px-6 py-4">
                        <h3 class="font-semibold">Branding</h3>
                        <p class="mt-0.5 text-sm text-muted">How the reseller's portal looks on its own domain</p>
                    </header>

                    @include('resellers.partials.branding-fields', ['domainEditable' => false, 'currentDomain' => $reseller->site_name, 'logoRequired' => false])
                </section>
            </div>

            <aside class="space-y-6 lg:sticky lg:top-24">
                @include('resellers.partials.preview')

                {{-- Account summary --}}
                <section class="{{ $card }} p-5">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-semibold">Account</h3>
                        <a href="{{ route('users.edit', $reseller->id) }}" class="inline-flex items-center gap-1 text-sm font-medium text-navy-900 hover:underline">
                            Edit account <x-heroicon-m-arrow-up-right class="size-3.5" />
                        </a>
                    </div>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">Billing</dt>
                            <dd class="font-medium capitalize">{{ $reseller->account_type ?: '—' }}</dd>
                        </div>
                        @if ($isPrepaid)
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted">Credits</dt>
                                <dd class="font-medium tabular-nums">{{ number_format((int) $reseller->balance) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">Users</dt>
                            <dd class="font-medium tabular-nums">{{ number_format((int) $reseller->users_count) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">Expires</dt>
                            <dd @class(['font-medium tabular-nums', 'text-danger' => $expiry?->endOfDay()->isPast()])>{{ $expiry?->format('d M Y') ?? '—' }}</dd>
                        </div>
                    </dl>
                </section>

                <div class="{{ $card }} flex flex-col gap-2 p-4">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-navy-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:opacity-70" :disabled="loading">
                        <svg x-show="loading" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                        <span x-text="loading ? 'Saving…' : 'Save branding'">Save branding</span>
                    </button>
                    <a href="{{ route('resellers.index') }}" class="rounded-lg px-5 py-2.5 text-center text-sm font-medium text-muted transition hover:bg-surface hover:text-ink">Cancel</a>
                </div>
            </aside>
        </form>
    </div>
@endsection