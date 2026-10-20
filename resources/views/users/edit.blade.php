@extends('layouts.app')

@section('title', 'Edit user')

@use('App\Enums\UserType')
@use('App\Services\UserService')

@section('content')
    @php
        $listFrom = fn ($value) => array_values(array_filter(array_map('trim', explode(',', (string) $value))));
        $currentType = strtolower(trim((string) $user->user_type));
        $statusOn = old() ? (bool) old('status') : (int) $user->status === 1;
        $expiry = $user->user_expiry ? \Illuminate\Support\Carbon::parse($user->user_expiry) : null;
        $created = $user->user_creation ? \Illuminate\Support\Carbon::parse($user->user_creation) : null;
        $isPrepaid = strtolower(trim((string) $user->account_type)) === 'prepaid';

        // Same precedence as usp_users_list: blocked > disabled > expired > active
        $state = match (true) {
            (int) $user->permanentblock === 1 => ['Blocked', 'bg-rose-50 text-rose-700 ring-rose-600/20'],
            (int) $user->status !== 1 => ['Disabled', 'bg-surface text-muted ring-line'],
            $expiry && $expiry->endOfDay()->isPast() => ['Expired', 'bg-amber-50 text-amber-700 ring-amber-600/20'],
            default => ['Active', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
        };

        $userTypes = [
            'admin'         => ['Admin', 'Full account with its own users', 'building-office-2'],
            'user'          => ['Sub user', 'Sends messages under an admin', 'user'],
            'campreporting' => ['Camp reporting', 'Campaign reporting access', 'chart-bar'],
            'lcreporting'   => ['LC reporting', 'LC reporting access', 'chart-pie'],
            'gwuser'        => ['ITSGW user', 'Gateway account', 'server-stack'],
            'reporting'     => ['Reporting user', 'Reports for a selected user', 'eye'],
        ];

        // Keep legacy types (reseller, campapprover) selectable for accounts that already have them.
        if (! isset($userTypes[$currentType])) {
            $userTypes[$currentType] = [UserType::tryFrom($currentType)?->label() ?? ucfirst($currentType), 'Current account type', 'user-circle'];
        }

        $xIcon = '<svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5.3 5.3a1 1 0 0 1 1.4 0L10 8.6l3.3-3.3a1 1 0 1 1 1.4 1.4L11.4 10l3.3 3.3a1 1 0 0 1-1.4 1.4L10 11.4l-3.3 3.3a1 1 0 0 1-1.4-1.4L8.6 10 5.3 6.7a1 1 0 0 1 0-1.4Z"/></svg>';
        $tagBox = 'field-input flex min-h-11 cursor-text flex-wrap items-center gap-1.5 py-1.5 focus-within:border-navy-700 focus-within:ring-4 focus-within:ring-navy-700/10';
        $card = 'rounded-2xl border border-line bg-white';
    @endphp

    <div class="mx-auto max-w-6xl">
        <a href="{{ route('users.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted transition hover:text-ink">
            <x-heroicon-m-arrow-left class="size-4" />
            Back to users
        </a>

        {{-- Identity --}}
        <div class="mb-6 flex items-center gap-4">
            <span class="grid size-14 shrink-0 place-items-center rounded-full bg-navy-900 text-lg font-semibold uppercase text-white">
                {{ mb_substr($user->username, 0, 2) }}
            </span>
            <div class="min-w-0">
                <h2 class="truncate font-display text-2xl font-semibold tracking-tight">{{ $user->username }}</h2>
                <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted">
                    <span>#{{ $user->id }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ $userTypes[$currentType][0] }}</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $state[1] }}">
                        <span class="size-1.5 rounded-full bg-current"></span>{{ $state[0] }}
                    </span>
                </div>
            </div>
        </div>

        <form
            method="POST"
            action="{{ route('users.update', $user->id) }}"
            autocomplete="off"
            class="grid items-start gap-6 lg:grid-cols-3"
            x-data="{
                userType: @js(old('user_type', $currentType)),
                typesWithAdmin: @js(UserService::TYPES_WITH_ADMIN),
                loading: false,
                get needsAdmin() { return this.typesWithAdmin.includes(this.userType) },
                get needsReportUser() { return this.userType === 'reporting' },
            }"
            @submit="loading = true"
            @pageshow.window="loading = false"
        >
            @csrf
            @method('PUT')

            <div class="space-y-6 lg:col-span-2">
                {{-- Login and contact --}}
                <section class="{{ $card }}">
                    <header class="border-b border-line px-6 py-4">
                        <h3 class="font-semibold">Login and contact</h3>
                        <p class="mt-0.5 text-sm text-muted">Sign-in details and where alerts are sent</p>
                    </header>

                    <div class="grid gap-5 p-6 sm:grid-cols-2">
                        <div>
                            <label for="username" class="field-label">Username</label>
                            <div class="relative">
                                <input id="username" type="text" value="{{ $user->username }}" class="field-input bg-surface pr-10 text-muted" readonly tabindex="-1">
                                <x-heroicon-o-lock-closed class="pointer-events-none absolute right-3.5 top-1/2 size-4 -translate-y-1/2 text-muted" />
                            </div>
                            <p class="field-hint">Usernames can't be changed.</p>
                        </div>

                        <div x-data="{ reveal: false }">
                            <label for="password" class="field-label">New password</label>
                            <div class="relative">
                                <input id="password" name="password" type="password" :type="reveal ? 'text' : 'password'" class="field-input pr-16" minlength="8" autocomplete="new-password" placeholder="Leave blank to keep" @error('password') aria-invalid="true" @enderror>
                                <button type="button" class="absolute inset-y-0 right-0 px-3.5 text-sm font-medium text-muted hover:text-ink" @click="reveal = !reveal" x-text="reveal ? 'Hide' : 'Show'">Show</button>
                            </div>
                            <p class="field-hint">At least 8 characters.</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label for="fullname" class="field-label">Full name</label>
                            <input id="fullname" name="fullname" type="text" value="{{ old('fullname', $user->fullname) }}" class="field-input" required maxlength="50" @error('fullname') aria-invalid="true" @enderror>
                        </div>

                        <div x-data="tagInput(@js(['values' => $listFrom(old('mobiles', $user->user_mobile)), 'type' => 'mobile', 'max' => 5, 'hint' => 'Press Enter after each number.', 'placeholder' => '0300 1234567']))">
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

                        <div x-data="tagInput(@js(['values' => $listFrom(old('emails', $user->email)), 'type' => 'email', 'max' => 5, 'hint' => 'Press Enter after each email.', 'placeholder' => 'name@company.com']))">
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
                    </div>
                </section>

                {{-- Access --}}
                <section class="{{ $card }}">
                    <header class="border-b border-line px-6 py-4">
                        <h3 class="font-semibold">Access</h3>
                        <p class="mt-0.5 text-sm text-muted">Account type, owner and validity</p>
                    </header>

                    <div class="space-y-6 p-6">
                        <fieldset>
                            <legend class="field-label">User type</legend>
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($userTypes as $value => [$label, $hint, $icon])
                                    <label class="choice-card items-start p-4">
                                        <input type="radio" name="user_type" value="{{ $value }}" class="sr-only" x-model="userType">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-lg transition" :class="userType === @js($value) ? 'bg-navy-900 text-white' : 'bg-surface text-muted'">
                                            <x-dynamic-component :component="'heroicon-o-' . $icon" class="size-5" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block font-medium">{{ $label }}</span>
                                            <span class="mt-0.5 block text-sm text-muted">{{ $hint }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <div x-show="needsAdmin" x-cloak x-transition class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="admin" class="field-label">Admin for this user</label>
                                @include('partials.combobox', [
                                    'id' => 'admin',
                                    'name' => 'reseller_id',
                                    'options' => $options['admins'],
                                    'selected' => old('reseller_id', $user->reseller_id ?: null),
                                    'placeholder' => 'Search admins',
                                ])
                            </div>

                            <div x-show="needsReportUser" x-cloak>
                                <label for="report-user" class="field-label">User for reporting</label>
                                @include('partials.combobox', [
                                    'id' => 'report-user',
                                    'name' => 'reporting_user_id',
                                    'options' => $options['reportUsers'],
                                    'selected' => old('reporting_user_id', $user->reporting_user_id ?: null),
                                    'placeholder' => 'Search users',
                                ])
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div x-data="datePicker(@js(['value' => old('expiry', $expiry?->toDateString())]))">
                                <label for="expiry" class="field-label">Expiry date</label>
                                <input x-ref="input" id="expiry" name="expiry" type="text" class="field-input" placeholder="Select a date" @error('expiry') aria-invalid="true" @enderror>
                            </div>

                            <div>
                                <label for="customer" class="field-label">Master customer</label>
                                @include('partials.combobox', [
                                    'id' => 'customer',
                                    'name' => 'customer_id',
                                    'options' => $options['customers'],
                                    'selected' => old('customer_id', $user->customer_id),
                                    'placeholder' => 'Search customers',
                                ])
                            </div>
                        </div>

                        <div>
                            <span class="field-label">Status</span>
                            <label class="inline-flex cursor-pointer items-center gap-3">
                                <input type="checkbox" name="status" value="1" class="peer sr-only" @checked($statusOn)>
                                <span class="relative h-6 w-11 rounded-full bg-line transition peer-checked:bg-success peer-focus-visible:ring-4 peer-focus-visible:ring-navy-700/20 after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
                                <span class="text-sm text-muted">Account can sign in</span>
                            </label>
                        </div>
                    </div>
                </section>

                {{-- Maskings --}}
                <section class="{{ $card }}">
                    <header class="border-b border-line px-6 py-4">
                        <h3 class="font-semibold">Maskings</h3>
                        <p class="mt-0.5 text-sm text-muted">Sender IDs this account can send from</p>
                    </header>

                    <div class="p-6" x-data="multiSelect(@js(['options' => $options['maskings'], 'selected' => old('maskings', $maskings)]))" @click.outside="open = false" @keydown.escape="open = false">
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
                </section>
                {{-- Credit history --}}
                    <section class="{{ $card }}">
                        <header class="border-b border-line px-6 py-4">
                            <h3 class="font-semibold">Credit history</h3>
                            <p class="mt-0.5 text-sm text-muted">Latest 10 balance movements</p>
                        </header>

                        @if ($balanceHistory)
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-surface/60 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                                        <tr>
                                            <th class="px-6 py-3">Date</th>
                                            <th class="px-3 py-3">Details</th>
                                            <th class="px-3 py-3">Type</th>
                                            <th class="px-6 py-3 text-right">Credits</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-line">
                                        @foreach ($balanceHistory as $entry)
                                            @php
                                                $in = (int) $entry->purchased;
                                                $out = (int) $entry->availed;
                                                $kind = match ((int) $entry->balance_type) {
                                                    1 => ['Opening', 'bg-sky-50 text-sky-700'],
                                                    2 => ['Adjustment', 'bg-violet-50 text-violet-700'],
                                                    default => ['Usage', 'bg-surface text-muted'],
                                                };
                                            @endphp
                                            <tr>
                                                <td class="whitespace-nowrap px-6 py-3 tabular-nums text-muted">
                                                    {{ $entry->avail_time ? \Illuminate\Support\Carbon::parse($entry->avail_time)->format('d M Y, H:i') : '—' }}
                                                </td>
                                                <td class="max-w-xs px-3 py-3"><p class="truncate text-ink" title="{{ trim((string) $entry->details) }}">{{ trim((string) $entry->details) ?: '—' }}</p></td>
                                                <td class="px-3 py-3"><span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $kind[1] }}">{{ $kind[0] }}</span></td>
                                                <td class="whitespace-nowrap px-6 py-3 text-right font-semibold tabular-nums">
                                                    @if ($in > 0)
                                                        <span class="text-success">+{{ number_format($in) }}</span>
                                                    @elseif ($out > 0)
                                                        <span class="text-danger">−{{ number_format($out) }}</span>
                                                    @else
                                                        <span class="text-muted">0</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="px-6 py-10 text-center">
                                <x-heroicon-o-banknotes class="mx-auto size-9 text-muted/50" />
                                <p class="mt-2 text-sm text-muted">No credit movements yet.</p>
                            </div>
                        @endif
                    </section>
            </div>

            {{-- Summary and actions --}}
            <aside class="space-y-6 lg:sticky lg:top-24">
                <section class="{{ $card }} p-5">
                    <h3 class="font-semibold">Account</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">Billing</dt>
                            <dd class="font-medium capitalize">{{ $user->account_type ?: '—' }}</dd>
                        </div>
                        @if ($isPrepaid)
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted">Credits</dt>
                                <dd class="font-medium tabular-nums">{{ number_format((int) $user->balance) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">Admin</dt>
                            <dd class="truncate font-medium">{{ $user->admin_username ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">Created</dt>
                            <dd class="font-medium tabular-nums">{{ $created?->format('d M Y') ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">Expires</dt>
                            <dd @class(['font-medium tabular-nums', 'text-danger' => $expiry?->endOfDay()->isPast()])>{{ $expiry?->format('d M Y') ?? '—' }}</dd>
                        </div>
                    </dl>
                    <p class="mt-4 rounded-lg bg-surface px-3 py-2.5 text-xs leading-relaxed text-muted">
                        Billing type and credits are managed from balance settings, not here.
                    </p>
                </section>

                <div class="{{ $card }} flex flex-col gap-2 p-4">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-navy-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:opacity-70" :disabled="loading">
                        <svg x-show="loading" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                        <span x-text="loading ? 'Saving…' : 'Save changes'">Save changes</span>
                    </button>
                    <a href="{{ route('users.index') }}" class="rounded-lg px-5 py-2.5 text-center text-sm font-medium text-muted transition hover:bg-surface hover:text-ink">Cancel</a>
                </div>
            </aside>
        </form>
    </div>
@endsection