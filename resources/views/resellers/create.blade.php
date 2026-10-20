@extends('layouts.app')

@section('title', 'Add reseller')

@section('content')
    @php
        $listFrom = fn ($value) => array_values(array_filter(array_map('trim', explode(',', (string) $value))));
        $statusOn = old() ? (bool) old('status') : true;
        $darkOn = old() ? (bool) old('dark_theme') : true;
        $colors = [
            'first'  => old('first_color', $defaultColors['first_color']),
            'second' => old('second_color', $defaultColors['second_color']),
            'third'  => old('third_color', $defaultColors['third_color']),
            'fourth' => old('fourth_color', $defaultColors['fourth_color']),
        ];

        $xIcon = '<svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5.3 5.3a1 1 0 0 1 1.4 0L10 8.6l3.3-3.3a1 1 0 1 1 1.4 1.4L11.4 10l3.3 3.3a1 1 0 0 1-1.4 1.4L10 11.4l-3.3 3.3a1 1 0 0 1-1.4-1.4L8.6 10 5.3 6.7a1 1 0 0 1 0-1.4Z"/></svg>';
        $tagBox = 'field-input flex min-h-11 cursor-text flex-wrap items-center gap-1.5 py-1.5 focus-within:border-navy-700 focus-within:ring-4 focus-within:ring-navy-700/10';
        $card = 'rounded-2xl border border-line bg-white';
    @endphp

    <div class="mx-auto max-w-6xl">
        <a href="{{ route('resellers.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted transition hover:text-ink">
            <x-heroicon-m-arrow-left class="size-4" />
            Back to resellers
        </a>

        <form
            method="POST"
            action="{{ route('resellers.store') }}"
            enctype="multipart/form-data"
            autocomplete="off"
            class="grid items-start gap-6 lg:grid-cols-3"
            x-data="{
                loading: false,
                accountType: @js(old('account_type', '')),
                company: @js(old('company_name', '')),
                slogan: @js(old('slogan', '')),
                domain: @js(old('domain', '')),
                colors: @js($colors),
                dark: @js($darkOn),
                logo: null,
                logoName: '',
                pickLogo(event) {
                    const file = event.target.files[0];

                    // Reject large files before upload; the server limit is 2 MB.
                    if (file && file.size > 2 * 1024 * 1024) {
                        event.target.value = '';
                        this.logo = null;
                        this.logoName = '';
                        this.$store.toasts.error?.('The logo must be 2 MB or smaller.') ?? alert('The logo must be 2 MB or smaller.');
                        return;
                    }

                    this.logo = file ? URL.createObjectURL(file) : null;
                    this.logoName = file ? file.name : '';
                },
            }"
            @submit="loading = true"
            @pageshow.window="loading = false"
        >
            @csrf

            <div class="space-y-6 lg:col-span-2">
                {{-- Branding --}}
                <section class="{{ $card }}">
                    <header class="border-b border-line px-6 py-4">
                        <h3 class="font-semibold">Branding</h3>
                        <p class="mt-0.5 text-sm text-muted">How the reseller's portal looks on its own domain</p>
                    </header>

                    @include('resellers.partials.branding-fields', ['domainEditable' => true, 'currentDomain' => null, 'logoRequired' => true])
                </section>

                {{-- Account --}}
                <section class="{{ $card }}">
                    <header class="border-b border-line px-6 py-4">
                        <h3 class="font-semibold">Account</h3>
                        <p class="mt-0.5 text-sm text-muted">Sign-in, contacts and maskings</p>
                    </header>

                    <div class="grid gap-5 p-6 sm:grid-cols-2">
                        <div>
                            <label for="username" class="field-label">Username</label>
                            <input id="username" name="username" type="text" value="{{ old('username') }}" class="field-input" required maxlength="50" pattern="[A-Za-z0-9._\-]+" title="Letters, numbers, dots, dashes and underscores" @error('username') aria-invalid="true" @enderror>
                        </div>

                        <div x-data="{ reveal: false }">
                            <label for="password" class="field-label">Password</label>
                            <div class="relative">
                                <input id="password" name="password" type="password" :type="reveal ? 'text' : 'password'" class="field-input pr-16" required minlength="8" autocomplete="new-password" @error('password') aria-invalid="true" @enderror>
                                <button type="button" class="absolute inset-y-0 right-0 px-3.5 text-sm font-medium text-muted hover:text-ink" @click="reveal = !reveal" x-text="reveal ? 'Hide' : 'Show'">Show</button>
                            </div>
                        </div>

                        <div class="sm:col-span-2">
                            <label for="fullname" class="field-label">Full name</label>
                            <input id="fullname" name="fullname" type="text" value="{{ old('fullname') }}" class="field-input" required maxlength="50" @error('fullname') aria-invalid="true" @enderror>
                        </div>

                        <div x-data="tagInput(@js(['values' => $listFrom(old('mobiles')), 'type' => 'mobile', 'max' => 5, 'hint' => 'Press Enter after each number.', 'placeholder' => '0300 1234567']))">
                            <label for="mobiles-entry" class="field-label">Mobile</label>
                            <input type="hidden" name="mobiles" :value="value">
                            <div class="{{ $tagBox }}" @click="$refs.entry.focus()" @error('mobiles') aria-invalid="true" @enderror>
                                <template x-for="(item, index) in items" :key="item">
                                    <span class="inline-flex items-center gap-1 rounded-md bg-surface py-1 pr-1 pl-2.5 text-sm font-medium tabular-nums">
                                        <span x-text="display(item)"></span>
                                        <button type="button" class="rounded p-0.5 text-muted hover:bg-white hover:text-ink" @click.stop="remove(index)">{!! $xIcon !!}</button>
                                    </span>
                                </template>
                                <input id="mobiles-entry" x-ref="entry" x-model="draft" type="text" inputmode="tel" class="min-w-32 flex-1 border-0 bg-transparent p-1 text-[15px] outline-none" :placeholder="items.length ? '' : placeholder" @keydown="onKeydown($event)" @blur="add()" @paste.prevent="draft = $event.clipboardData.getData('text'); add()">
                            </div>
                            <p class="field-hint" :class="error ? 'text-danger' : ''" x-text="error || hint"></p>
                        </div>

                        <div x-data="tagInput(@js(['values' => $listFrom(old('emails')), 'type' => 'email', 'max' => 5, 'hint' => 'Press Enter after each email.', 'placeholder' => 'name@company.com']))">
                            <label for="emails-entry" class="field-label">Email</label>
                            <input type="hidden" name="emails" :value="value">
                            <div class="{{ $tagBox }}" @click="$refs.entry.focus()" @error('emails') aria-invalid="true" @enderror>
                                <template x-for="(item, index) in items" :key="item">
                                    <span class="inline-flex items-center gap-1 rounded-md bg-surface py-1 pr-1 pl-2.5 text-sm font-medium">
                                        <span x-text="item"></span>
                                        <button type="button" class="rounded p-0.5 text-muted hover:bg-white hover:text-ink" @click.stop="remove(index)">{!! $xIcon !!}</button>
                                    </span>
                                </template>
                                <input id="emails-entry" x-ref="entry" x-model="draft" type="email" class="min-w-40 flex-1 border-0 bg-transparent p-1 text-[15px] outline-none" :placeholder="items.length ? '' : placeholder" @keydown="onKeydown($event)" @blur="add()" @paste.prevent="draft = $event.clipboardData.getData('text'); add()">
                            </div>
                            <p class="field-hint" :class="error ? 'text-danger' : ''" x-text="error || hint"></p>
                        </div>

                        {{-- Maskings --}}
                        <div class="sm:col-span-2" x-data="multiSelect(@js(['options' => $options['maskings'], 'selected' => old('maskings', [])]))" @click.outside="open = false" @keydown.escape="open = false">
                            <label class="field-label">Maskings</label>
                            <template x-for="value in selected" :key="value">
                                <input type="hidden" name="maskings[]" :value="value">
                            </template>
                            <div class="relative">
                                <div class="{{ $tagBox }}" @click="show()" @error('maskings') aria-invalid="true" @enderror>
                                    <template x-for="value in selected" :key="value">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-navy-900 py-1 pr-1 pl-2.5 text-sm font-medium text-white">
                                            <span x-text="labelFor(value)"></span>
                                            <button type="button" class="rounded p-0.5 text-white/70 hover:bg-white/10 hover:text-white" @click.stop="remove(value)">{!! $xIcon !!}</button>
                                        </span>
                                    </template>
                                    <span x-show="!selected.length" class="p-1 text-[15px] text-muted/70">Select maskings</span>
                                </div>
                                <div x-show="open" x-cloak x-transition.opacity.duration.100ms class="absolute z-30 mt-1.5 w-full overflow-hidden rounded-xl border border-line bg-white shadow-xl shadow-navy-950/10">
                                    <div class="border-b border-line p-2">
                                        <input x-ref="search" x-model="query" type="text" placeholder="Search maskings…" class="w-full rounded-lg bg-surface px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-navy-700/20">
                                    </div>
                                    <ul class="max-h-60 overflow-y-auto p-1">
                                        <template x-for="option in filtered" :key="option.value">
                                            <li>
                                                <label class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-surface">
                                                    <input type="checkbox" class="size-4 accent-navy-900" :checked="isSelected(option.value)" @change="toggleOption(option)">
                                                    <span x-text="option.label"></span>
                                                </label>
                                            </li>
                                        </template>
                                        <li x-show="!filtered.length" class="px-3 py-6 text-center text-sm text-muted">No matches</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div x-data="datePicker(@js(['value' => old('expiry'), 'minDate' => 'today']))">
                            <label for="expiry" class="field-label">Expiry date</label>
                            <input x-ref="input" id="expiry" name="expiry" type="text" class="field-input" placeholder="Select a date" @error('expiry') aria-invalid="true" @enderror>
                        </div>

                        <div>
                            <label for="customer" class="field-label">Master customer</label>
                            @include('partials.combobox', [
                                'id' => 'customer',
                                'name' => 'customer_id',
                                'options' => $options['customers'],
                                'selected' => old('customer_id'),
                                'placeholder' => 'Search customers',
                            ])
                        </div>

                        <div class="sm:col-span-2">
                            <span class="field-label">Status</span>
                            <label class="inline-flex cursor-pointer items-center gap-3">
                                <input type="checkbox" name="status" value="1" class="peer sr-only" @checked($statusOn)>
                                <span class="relative h-6 w-11 rounded-full bg-line transition peer-checked:bg-success peer-focus-visible:ring-4 peer-focus-visible:ring-navy-700/20 after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
                                <span class="text-sm text-muted">Account can sign in</span>
                            </label>
                        </div>
                    </div>
                </section>

                {{-- Billing --}}
                <section class="{{ $card }}">
                    <header class="border-b border-line px-6 py-4">
                        <h3 class="font-semibold">Billing</h3>
                        <p class="mt-0.5 text-sm text-muted">Key account manager: {{ $creator['fullname'] ?: $creator['username'] }}</p>
                    </header>

                    <div class="p-6">
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach (['prepaid' => ['Prepaid', 'Pays in advance with credits', 'banknotes'], 'postpaid' => ['Postpaid', 'Billed monthly for usage', 'document-text']] as $value => [$label, $hint, $icon])
                                <label class="choice-card items-start p-4">
                                    <input type="radio" name="account_type" value="{{ $value }}" class="choice-radio mt-0.5" x-model="accountType" required>
                                    <span class="flex-1">
                                        <span class="flex items-center gap-2 font-medium"><x-dynamic-component :component="'heroicon-o-' . $icon" class="size-5 text-muted" />{{ $label }}</span>
                                        <span class="mt-0.5 block text-sm text-muted">{{ $hint }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <div x-show="accountType === 'prepaid'" x-cloak x-transition class="mt-4 sm:max-w-xs">
                            <label for="balance" class="field-label">Opening credits</label>
                            <div class="relative">
                                <input id="balance" name="balance" type="number" min="1" value="{{ old('balance') }}" class="field-input pr-20 tabular-nums" @error('balance') aria-invalid="true" @enderror>
                                <span class="pointer-events-none absolute inset-y-0 right-3.5 grid place-items-center text-sm text-muted">credits</span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            {{-- Live preview and actions --}}
            <aside class="space-y-6 lg:sticky lg:top-24">
                    @include('resellers.partials.preview')

                <div class="{{ $card }} flex flex-col gap-2 p-4">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-navy-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:opacity-70" :disabled="loading">
                        <svg x-show="loading" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                        <span x-text="loading ? 'Creating…' : 'Create reseller'">Create reseller</span>
                    </button>
                    <a href="{{ route('resellers.index') }}" class="rounded-lg px-5 py-2.5 text-center text-sm font-medium text-muted transition hover:bg-surface hover:text-ink">Cancel</a>
                </div>
            </aside>
        </form>
    </div>
@endsection