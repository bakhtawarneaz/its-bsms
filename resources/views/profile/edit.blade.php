@extends('layouts.app')

@section('title', 'My profile')

@section('content')
    @php
        $name = $profile->fullname ?: $profile->username;
        $initials = collect(preg_split('/\s+/', trim($name)))->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
        $userType = \App\Enums\UserType::fromDatabase($profile->user_type);
        $isPostpaid = str_contains(strtolower((string) $profile->account_type), 'post');
        $expiry = $profile->user_expiry ? \Carbon\Carbon::parse($profile->user_expiry) : null;
        $joined = $profile->user_creation ? \Carbon\Carbon::parse($profile->user_creation) : null;
        $isActive = (int) $profile->status === 1;

        $listFrom = fn ($value) => array_values(array_filter(array_map('trim', explode(',', (string) $value))));
        $mobiles = $listFrom(old('mobiles', $profile->user_mobile));
        $emails = $listFrom(old('emails', $profile->email));

        $activityIcon = function (string $activity) {
            $text = strtolower($activity);

            return match (true) {
                str_contains($text, 'log in')   => 'arrow-right-end-on-rectangle',
                str_contains($text, 'logout')   => 'arrow-left-start-on-rectangle',
                str_contains($text, 'password') => 'key',
                str_contains($text, 'profile')  => 'pencil-square',
                default                         => 'clock',
            };
        };

        $tagBox = 'field-input flex min-h-11 cursor-text flex-wrap items-center gap-1.5 py-1.5 focus-within:border-navy-700 focus-within:ring-4 focus-within:ring-navy-700/10';
        $xIcon = '<svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5.3 5.3a1 1 0 0 1 1.4 0L10 8.6l3.3-3.3a1 1 0 1 1 1.4 1.4L11.4 10l3.3 3.3a1 1 0 0 1-1.4 1.4L10 11.4l-3.3 3.3a1 1 0 0 1-1.4-1.4L8.6 10 5.3 6.7a1 1 0 0 1 0-1.4Z"/></svg>';
    @endphp

    <div
        x-data="{
            tab: @js($activeTab ?? 'details'),
            init() {
                const hash = window.location.hash.slice(1);
                if (! @js((bool) $activeTab) && ['details', 'security'].includes(hash)) this.tab = hash;
                this.$watch('tab', (value) => history.replaceState(null, '', '#' + value));
            },
        }"
        class="mx-auto max-w-5xl"
    >
        {{-- Hero --}}
        <section class="relative overflow-hidden rounded-2xl bg-navy-900 px-6 pt-8 pb-6 text-white sm:px-8">
            <div
                class="pointer-events-none absolute inset-y-0 right-0 w-2/3"
                style="background-image: radial-gradient(rgb(255 255 255 / 0.09) 1px, transparent 1px); background-size: 18px 18px; mask-image: linear-gradient(to left, black, transparent);"
                aria-hidden="true"
            ></div>

            <div class="relative flex flex-wrap items-center gap-5">
                <span class="grid size-20 shrink-0 place-items-center rounded-2xl bg-brand font-display text-2xl font-semibold text-navy-900 ring-4 ring-white/10">{{ strtoupper($initials) }}</span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h2 class="truncate font-display text-2xl font-semibold tracking-tight">{{ $name }}</h2>
                        <span @class([
                            'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
                            'bg-emerald-400/15 text-emerald-300' => $isActive,
                            'bg-red-400/15 text-red-300' => ! $isActive,
                        ])>
                            <span class="size-1.5 rounded-full bg-current"></span>
                            {{ $isActive ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-white/60">
                        {{ '@' . $profile->username }}
                        <span class="ml-2 rounded-md bg-white/10 px-2 py-0.5 text-xs font-medium text-white/80">{{ $userType->label() }}</span>
                    </p>
                </div>
            </div>

            <dl class="relative mt-8 grid grid-cols-2 gap-x-6 gap-y-4 border-t border-white/10 pt-5 sm:grid-cols-4">
                <div>
                    <dt class="text-xs text-white/50">Account type</dt>
                    <dd class="mt-1 font-medium">{{ $profile->account_type ?: 'Not set' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-white/50">Credits</dt>
                    <dd class="mt-1 font-medium tabular-nums">{{ $isPostpaid ? 'Billed monthly' : number_format((int) $profile->balance) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-white/50">Expires on</dt>
                    <dd class="mt-1 font-medium">{{ $expiry?->format('j M Y') ?? 'No expiry' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-white/50">Member since</dt>
                    <dd class="mt-1 font-medium">{{ $joined?->format('M Y') ?? 'Unknown' }}</dd>
                </div>
            </dl>
        </section>

        {{-- Tabs --}}
        <div class="mt-6 flex gap-6 border-b border-line" role="tablist" aria-label="Profile sections">
            @foreach (['details' => ['Profile details', 'user'], 'security' => ['Security', 'shield-check']] as $key => [$label, $icon])
                <button
                    type="button"
                    role="tab"
                    class="-mb-px inline-flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-medium transition"
                    :class="tab === @js($key) ? 'border-navy-900 text-ink' : 'border-transparent text-muted hover:text-ink'"
                    :aria-selected="tab === @js($key)"
                    @click="tab = @js($key)"
                >
                    <x-dynamic-component :component="'heroicon-o-' . $icon" class="size-5" />
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Profile details --}}
        <form
            x-show="tab === 'details'"
            method="POST"
            action="{{ route('profile.update') }}"
            class="mt-6 overflow-hidden rounded-2xl border border-line bg-white"
            x-data="{ loading: false, dirty: false }"
            @input="dirty = true"
            @change="dirty = true"
            @submit="loading = true"
            @pageshow.window="loading = false"
        >
            @csrf
            @method('PUT')

            <div class="divide-y divide-line">
                <div class="grid gap-4 px-6 py-6 lg:grid-cols-3 lg:gap-8">
                    <div>
                        <label for="fullname" class="font-medium">Business name</label>
                        <p class="mt-1 text-sm text-muted">Shown on your invoices and account emails.</p>
                    </div>
                    <div class="lg:col-span-2">
                        <input id="fullname" name="fullname" type="text" value="{{ old('fullname', $profile->fullname) }}" class="field-input" required @error('fullname') aria-invalid="true" @enderror>
                    </div>
                </div>

                <div class="grid gap-4 px-6 py-6 lg:grid-cols-3 lg:gap-8">
                    <div>
                        <label for="mobiles-entry" class="font-medium">Mobile numbers</label>
                        <p class="mt-1 text-sm text-muted">Sign-in codes and alerts are sent by SMS to these numbers.</p>
                    </div>
                    <div class="lg:col-span-2" x-data="tagInput(@js(['values' => $mobiles, 'type' => 'mobile', 'max' => 5, 'hint' => 'Type a number and press Enter. Up to 5.', 'placeholder' => '0300 1234567']))">
                        <input type="hidden" name="mobiles" :value="value">
                        <div class="{{ $tagBox }}" @click="$refs.entry.focus()" @error('mobiles') aria-invalid="true" @enderror>
                            <template x-for="(item, index) in items" :key="item">
                                <span class="inline-flex items-center gap-1 rounded-md bg-surface py-1 pr-1 pl-2.5 text-sm font-medium tabular-nums">
                                    <span x-text="display(item)"></span>
                                    <button type="button" class="rounded p-0.5 text-muted transition hover:bg-white hover:text-ink" @click.stop="remove(index)" :aria-label="`Remove ${display(item)}`">{!! $xIcon !!}</button>
                                </span>
                            </template>
                            <input
                                id="mobiles-entry" x-ref="entry" x-model="draft" type="text" inputmode="tel"
                                class="min-w-36 flex-1 border-0 bg-transparent p-1 text-[15px] outline-none"
                                :placeholder="items.length ? '' : placeholder"
                                @keydown="onKeydown($event)"
                                @blur="add()"
                                @paste.prevent="draft = $event.clipboardData.getData('text'); add()"
                            >
                        </div>
                        <p class="field-hint" :class="error ? 'text-danger' : ''" x-text="error || hint"></p>
                    </div>
                </div>

                <div class="grid gap-4 px-6 py-6 lg:grid-cols-3 lg:gap-8">
                    <div>
                        <label for="emails-entry" class="font-medium">Emails</label>
                        <p class="mt-1 text-sm text-muted">Sign-in codes, account changes and low balance alerts.</p>
                    </div>
                    <div class="lg:col-span-2" x-data="tagInput(@js(['values' => $emails, 'type' => 'email', 'max' => 5, 'hint' => 'Type an email and press Enter. Up to 5.', 'placeholder' => 'name@company.com']))">
                        <input type="hidden" name="emails" :value="value">
                        <div class="{{ $tagBox }}" @click="$refs.entry.focus()" @error('emails') aria-invalid="true" @enderror>
                            <template x-for="(item, index) in items" :key="item">
                                <span class="inline-flex items-center gap-1 rounded-md bg-surface py-1 pr-1 pl-2.5 text-sm font-medium">
                                    <span x-text="display(item)"></span>
                                    <button type="button" class="rounded p-0.5 text-muted transition hover:bg-white hover:text-ink" @click.stop="remove(index)" :aria-label="`Remove ${item}`">{!! $xIcon !!}</button>
                                </span>
                            </template>
                            <input
                                id="emails-entry" x-ref="entry" x-model="draft" type="email"
                                class="min-w-48 flex-1 border-0 bg-transparent p-1 text-[15px] outline-none"
                                :placeholder="items.length ? '' : placeholder"
                                @keydown="onKeydown($event)"
                                @blur="add()"
                                @paste.prevent="draft = $event.clipboardData.getData('text'); add()"
                            >
                        </div>
                        <p class="field-hint" :class="error ? 'text-danger' : ''" x-text="error || hint"></p>
                    </div>
                </div>

                <div class="grid gap-4 px-6 py-6 lg:grid-cols-3 lg:gap-8">
                    <div>
                        <label for="low_balance_limit" class="font-medium">Low balance alert</label>
                        <p class="mt-1 text-sm text-muted">We email you when your credits drop below this number.</p>
                    </div>
                    <div class="lg:col-span-2">
                        <div class="relative sm:max-w-xs">
                            <input id="low_balance_limit" name="low_balance_limit" type="number" min="0" value="{{ old('low_balance_limit', $profile->low_balance_limit) }}" class="field-input pr-20 tabular-nums" @error('low_balance_limit') aria-invalid="true" @enderror>
                            <span class="pointer-events-none absolute inset-y-0 right-3.5 grid place-items-center text-sm text-muted">credits</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Floating save bar --}}
            <div
                x-show="dirty" x-cloak
                x-transition:enter="transition duration-300 ease-out"
                x-transition:enter-start="opacity-0 translate-y-6"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition duration-200 ease-in"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-6"
                class="fixed inset-x-0 bottom-6 z-30 flex justify-center px-4 lg:pl-64"
            >
                <div class="flex items-center gap-3 rounded-2xl bg-navy-900 py-2.5 pr-2.5 pl-5 text-white shadow-2xl shadow-navy-950/30">
                    <x-heroicon-o-exclamation-circle class="size-5 text-brand" />
                    <span class="text-sm">You have unsaved changes</span>
                    <a href="{{ route('profile.edit') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-white/70 transition hover:text-white">Discard</a>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-navy-900 transition hover:bg-brand-dark disabled:opacity-70" :disabled="loading">
                        <svg x-show="loading" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                        <span x-text="loading ? 'Saving…' : 'Save changes'">Save changes</span>
                    </button>
                </div>
            </div>
        </form>

        {{-- Security --}}
        <div x-show="tab === 'security'" x-cloak class="mt-6 grid gap-6 lg:grid-cols-5">
            <form
                method="POST"
                action="{{ route('profile.password') }}"
                class="overflow-hidden rounded-2xl border border-line bg-white lg:col-span-3"
                x-data="passwordForm"
                @submit="loading = true"
                @pageshow.window="loading = false"
            >
                @csrf
                @method('PUT')

                <div class="flex items-start justify-between gap-4 border-b border-line px-6 py-5">
                    <div>
                        <h3 class="font-semibold">Change password</h3>
                        <p class="mt-0.5 text-sm text-muted">You will get an email whenever your password changes.</p>
                    </div>
                    <button type="button" class="inline-flex items-center gap-1.5 text-sm font-medium text-muted transition hover:text-ink" @click="reveal = !reveal">
                        <span x-show="!reveal"><x-heroicon-o-eye class="size-5" /></span>
                        <span x-show="reveal" x-cloak><x-heroicon-o-eye-slash class="size-5" /></span>
                        <span x-text="reveal ? 'Hide' : 'Show'">Show</span>
                    </button>
                </div>

                <div class="space-y-5 px-6 py-6">
                    <div>
                        <label for="current_password" class="field-label">Current password</label>
                        <input id="current_password" name="current_password" type="password" :type="reveal ? 'text' : 'password'" class="field-input" autocomplete="current-password" required @error('current_password') aria-invalid="true" @enderror>
                    </div>

                    <div>
                        <label for="password" class="field-label">New password</label>
                        <input id="password" name="password" type="password" :type="reveal ? 'text' : 'password'" x-model="password" class="field-input" autocomplete="new-password" required @error('password') aria-invalid="true" @enderror>

                        <div class="mt-3 flex items-center gap-3" x-show="password.length" x-cloak>
                            <div class="grid flex-1 grid-cols-4 gap-1.5">
                                <template x-for="index in 4" :key="index">
                                    <span class="h-1.5 rounded-full transition-colors duration-300" :style="`background: ${index <= score ? color : '#EDF0F4'}`"></span>
                                </template>
                            </div>
                            <span class="w-16 text-right text-xs font-semibold" :style="`color: ${color}`" x-text="strength"></span>
                        </div>

                        <ul class="mt-3 grid gap-1.5 sm:grid-cols-2">
                            <template x-for="check in checks" :key="check.label">
                                <li class="flex items-center gap-2 text-xs transition-colors" :class="check.ok ? 'text-success' : 'text-muted'">
                                    <span x-show="check.ok"><x-heroicon-s-check-circle class="size-4" /></span>
                                    <span x-show="!check.ok" class="grid size-4 place-items-center"><span class="size-1.5 rounded-full bg-current"></span></span>
                                    <span x-text="check.label"></span>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div>
                        <label for="password_confirmation" class="field-label">Confirm new password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" :type="reveal ? 'text' : 'password'" class="field-input" autocomplete="new-password" required>
                    </div>
                </div>

                <div class="flex justify-end border-t border-line bg-surface/50 px-6 py-4">
                    <div class="w-full sm:w-auto">
                        @include('partials.submit-button', ['label' => 'Change password', 'loadingLabel' => 'Changing…'])
                    </div>
                </div>
            </form>

            <section class="rounded-2xl border border-line bg-white lg:col-span-2 lg:self-start">
                <div class="border-b border-line px-6 py-5">
                    <h3 class="font-semibold">Recent activity</h3>
                    <p class="mt-0.5 text-sm text-muted">Sign-ins and account changes</p>
                </div>

                @if ($activity)
                    <ol class="px-6 py-4">
                        @foreach ($activity as $item)
                            @php $time = \Carbon\Carbon::parse($item->act_time); @endphp
                            <li class="relative flex gap-3 pb-5 last:pb-0">
                                @unless ($loop->last)
                                    <span class="absolute top-9 bottom-0 left-4 w-px bg-line" aria-hidden="true"></span>
                                @endunless
                                <span class="relative grid size-8 shrink-0 place-items-center rounded-full bg-surface text-muted">
                                    <x-dynamic-component :component="'heroicon-o-' . $activityIcon($item->activity)" class="size-4" />
                                </span>
                                <div class="min-w-0 pt-1">
                                    <p class="text-sm font-medium">{{ $item->activity }}</p>
                                    <p class="mt-0.5 text-xs text-muted">
                                        <time datetime="{{ $time->toIso8601String() }}" title="{{ $time->format('j M Y, g:i A') }}">{{ $time->diffForHumans() }}</time>
                                        <span class="mx-1">from</span><span class="tabular-nums">{{ $item->ip }}</span>
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="px-6 py-6 text-sm text-muted">No activity yet.</p>
                @endif
            </section>
        </div>
    </div>
@endsection