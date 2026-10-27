@extends('layouts.app')

@section('title', 'Edit gateway user')

@section('content')
    @php
        $listFrom = fn ($value) => array_values(array_filter(array_map('trim', explode(',', (string) $value))));
        $statusOn = old() ? (bool) old('status') : (int) $user->Status === 1;
        $expiry = $user->ExpiryDateTime ? \Illuminate\Support\Carbon::parse($user->ExpiryDateTime) : null;
        $created = $user->CreatedDatetime ? \Illuminate\Support\Carbon::parse($user->CreatedDatetime) : null;
        $isPrepaid = strtolower(trim((string) $user->AccountType)) === 'prepaid';
        $networkByCode = collect($networks)->mapWithKeys(fn ($network, $key) => [$network['code'] => $network])->all();

        $xIcon = '<svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5.3 5.3a1 1 0 0 1 1.4 0L10 8.6l3.3-3.3a1 1 0 1 1 1.4 1.4L11.4 10l3.3 3.3a1 1 0 0 1-1.4 1.4L10 11.4l-3.3 3.3a1 1 0 0 1-1.4-1.4L8.6 10 5.3 6.7a1 1 0 0 1 0-1.4Z"/></svg>';
        $tagBox = 'field-input flex min-h-11 cursor-text flex-wrap items-center gap-1.5 py-1.5 focus-within:border-navy-700 focus-within:ring-4 focus-within:ring-navy-700/10';
        $card = 'rounded-2xl border border-line bg-white';
    @endphp

    <div class="mx-auto max-w-6xl">
        <a href="{{ route('gateway.users') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted transition hover:text-ink">
            <x-heroicon-m-arrow-left class="size-4" />
            Back to gateway users
        </a>

        {{-- Identity --}}
        <div class="mb-6 flex items-center gap-4">
            <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-navy-900 text-white">
                <x-heroicon-o-cpu-chip class="size-7" />
            </span>
            <div class="min-w-0">
                <h2 class="truncate font-display text-2xl font-semibold tracking-tight">{{ $user->Username }}</h2>
                <p class="mt-1 text-sm text-muted">
                    #{{ $user->Id }} · {{ strtoupper(trim((string) $user->UserType)) }} · Web login: {{ $user->WebUsername ?: '—' }}
                </p>
            </div>
        </div>

        <form
            method="POST"
            action="{{ route('gateway.users.update', $user->Id) }}"
            autocomplete="off"
            class="grid items-start gap-6 lg:grid-cols-3"
            x-data="{ loading: false }"
            @submit="loading = true"
            @pageshow.window="loading = false"
        >
            @csrf
            @method('PUT')

            <div class="space-y-6 lg:col-span-2">
                {{-- Contact --}}
                <section class="{{ $card }}">
                    <header class="border-b border-line px-6 py-4">
                        <h3 class="font-semibold">Contact</h3>
                        <p class="mt-0.5 text-sm text-muted">Who owns this integration</p>
                    </header>

                    <div class="grid gap-5 p-6 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="fullname" class="field-label">Full name</label>
                            <input id="fullname" name="fullname" type="text" value="{{ old('fullname', $user->Fullname) }}" class="field-input" required maxlength="100" @error('fullname') aria-invalid="true" @enderror>
                        </div>

                        <div x-data="tagInput(@js(['values' => $listFrom(old('mobiles', $user->Mobile)), 'type' => 'mobile', 'max' => 5, 'hint' => 'Press Enter after each number.', 'placeholder' => '0300 1234567']))">
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

                        <div x-data="tagInput(@js(['values' => $listFrom(old('emails', $user->Email)), 'type' => 'email', 'max' => 5, 'hint' => 'Press Enter after each email.', 'placeholder' => 'name@company.com']))">
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
                        <p class="mt-0.5 text-sm text-muted">Allowed IPs, validity and passwords</p>
                    </header>

                    <div class="grid gap-5 p-6 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="ips" class="field-label">Allowed IP addresses</label>
                            <input id="ips" name="ips" type="text" value="{{ old('ips', $user->AuthorizedDomain) }}" class="field-input font-mono" required maxlength="500" @error('ips') aria-invalid="true" @enderror>
                            <p class="field-hint">Separate multiple IPs with commas.</p>
                        </div>

                        <div x-data="datePicker(@js(['value' => old('expiry', $expiry?->toDateString())]))">
                            <label for="expiry" class="field-label">Expiry date</label>
                            <input x-ref="input" id="expiry" name="expiry" type="text" class="field-input" placeholder="Select a date" @error('expiry') aria-invalid="true" @enderror>
                        </div>

                        <div>
                            <span class="field-label">Status</span>
                            <label class="mt-2.5 inline-flex cursor-pointer items-center gap-3">
                                <input type="checkbox" name="status" value="1" class="peer sr-only" @checked($statusOn)>
                                <span class="relative h-6 w-11 rounded-full bg-line transition peer-checked:bg-success peer-focus-visible:ring-4 peer-focus-visible:ring-navy-700/20 after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
                                <span class="text-sm text-muted">Account is active</span>
                            </label>
                        </div>

                        <div x-data="{ reveal: false }">
                            <label for="password" class="field-label">New API password</label>
                            <div class="relative">
                                <input id="password" name="password" type="password" :type="reveal ? 'text' : 'password'" class="field-input pr-16 font-mono" minlength="4" maxlength="50" autocomplete="new-password" placeholder="Leave blank to keep" @error('password') aria-invalid="true" @enderror>
                                <button type="button" class="absolute inset-y-0 right-0 px-3.5 text-sm font-medium text-muted hover:text-ink" @click="reveal = !reveal" x-text="reveal ? 'Hide' : 'Show'">Show</button>
                            </div>
                        </div>

                        <div x-data="{ reveal: false }">
                            <label for="web_password" class="field-label">New web password</label>
                            <div class="relative">
                                <input id="web_password" name="web_password" type="password" :type="reveal ? 'text' : 'password'" class="field-input pr-16" minlength="8" autocomplete="new-password" placeholder="Leave blank to keep" @error('web_password') aria-invalid="true" @enderror>
                                <button type="button" class="absolute inset-y-0 right-0 px-3.5 text-sm font-medium text-muted hover:text-ink" @click="reveal = !reveal" x-text="reveal ? 'Hide' : 'Show'">Show</button>
                            </div>
                        </div>
                    </div>
                </section>

            </div>

            {{-- Summary and actions --}}
            <aside class="space-y-6 lg:sticky lg:top-24">
                <section class="{{ $card }} p-5">
                    <h3 class="font-semibold">Account</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">Billing</dt>
                            <dd class="font-medium">{{ $isPrepaid ? 'Prepaid' : 'Postpaid' }}</dd>
                        </div>
                        @if ($isPrepaid)
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted">Credits</dt>
                                <dd class="font-medium tabular-nums">{{ number_format((int) $user->Balance) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">Default mask</dt>
                            <dd class="font-medium">{{ $user->DefaultMask ?: '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">Reseller</dt>
                            <dd class="font-medium tabular-nums">{{ $user->ResellerId ?: '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">Created</dt>
                            <dd class="font-medium tabular-nums">{{ $created?->format('d M Y') ?? '—' }}</dd>
                        </div>
                    </dl>
                    <p class="mt-4 rounded-lg bg-surface px-3 py-2.5 text-xs leading-relaxed text-muted">
                        Username, type, reseller and billing type can't be changed after creation.
                    </p>
                </section>

                <div class="{{ $card }} flex flex-col gap-2 p-4">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-navy-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:opacity-70" :disabled="loading">
                        <svg x-show="loading" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                        <span x-text="loading ? 'Saving…' : 'Save changes'">Save changes</span>
                    </button>
                    <a href="{{ route('gateway.users') }}" class="rounded-lg px-5 py-2.5 text-center text-sm font-medium text-muted transition hover:bg-surface hover:text-ink">Cancel</a>
                </div>
            </aside>
        </form>

        {{-- Masks: outside the main form because it has its own add/delete forms --}}
        @php
            $initialMaskRoutes = [];
            foreach ($networks as $key => $network) {
                $initialMaskRoutes[$key] = (string) old("routes.{$key}", $maskRouteOptions[$key][0]['value']);
            }
        @endphp

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <section class="{{ $card }} lg:col-span-2"
                     x-data="{
                         selected: [],
                         confirming: false,
                         adding: @js($errors->has('masks') || $errors->has('routes') || $errors->has('routes.*')),
                         maskRoutes: @js($initialMaskRoutes),
                     }">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4">
                    <div>
                        <h3 class="font-semibold">Masks</h3>
                        <p class="mt-0.5 text-sm text-muted">{{ count($masks) }} masks with their route on each network</p>
                    </div>
                    <div class="flex items-center gap-2">
                        {{-- Delete selected (click twice to confirm) --}}
                        <form x-show="selected.length" x-cloak method="POST" action="{{ route('gateway.users.masks.destroy', $user->Id) }}"
                              @submit="if (! confirming) { $event.preventDefault(); confirming = true; setTimeout(() => confirming = false, 4000); }">
                            @csrf
                            @method('DELETE')
                            <template x-for="mask in selected" :key="mask">
                                <input type="hidden" name="masks[]" :value="mask">
                            </template>
                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold transition"
                                    :class="confirming ? 'bg-danger text-white' : 'bg-danger/10 text-danger hover:bg-danger/15'">
                                <x-heroicon-o-trash class="size-4" />
                                <span x-text="confirming ? 'Click again to delete ' + selected.length : 'Delete ' + selected.length"></span>
                            </button>
                        </form>

                        <button type="button" @click="adding = ! adding"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-navy-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-navy-800">
                            <x-heroicon-m-plus class="size-4" />
                            Add masks
                        </button>
                    </div>
                </header>

                {{-- Add masks panel --}}
                <div x-show="adding" x-cloak x-transition class="border-b border-line bg-surface/40 p-6">
                    <form method="POST" action="{{ route('gateway.users.masks.store', $user->Id) }}" class="space-y-5" x-data="{ busy: false }" @submit="busy = true">
                        @csrf
                        <div>
                            <label for="new-masks" class="field-label">Masks</label>
                            <textarea id="new-masks" name="masks" rows="2" class="field-input resize-y bg-white" placeholder="ITSALERT, 8558" required @error('masks') aria-invalid="true" @enderror>{{ old('masks') }}</textarea>
                            <p class="field-hint">Separate multiple masks with commas or new lines. Masks this user already has are skipped.</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach ($networks as $key => $network)
                                <div>
                                    <label for="mask-route-{{ $key }}" class="field-label flex items-center gap-2">
                                        <span class="size-2.5 rounded-full {{ $network['dot'] }}"></span>
                                        {{ $network['label'] }}
                                        <span class="text-xs font-normal text-muted">{{ $network['code'] }}</span>
                                    </label>
                                    <select id="mask-route-{{ $key }}" name="routes[{{ $key }}]" x-model="maskRoutes.{{ $key }}" class="field-input bg-white"
                                            :class="maskRoutes.{{ $key }} === '{{ \App\Services\GatewayUserService::BLOCK_ROUTE }}' ? 'text-danger' : ''">
                                        @foreach ($maskRouteOptions[$key] as $option)
                                            <option value="{{ $option['value'] }}">{{ $option['label'] }} ({{ $option['value'] }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-white hover:text-ink" @click="adding = false">Cancel</button>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:opacity-60" :disabled="busy">
                                <svg x-show="busy" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                                <span x-text="busy ? 'Adding…' : 'Add masks'">Add masks</span>
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Mask list --}}
                @if ($masks)
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-surface/60 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                                <tr>
                                    <th class="w-10 py-3 pl-6">
                                        <input type="checkbox" class="size-4 cursor-pointer accent-navy-900" aria-label="Select all masks"
                                               :checked="selected.length === {{ count($masks) }}"
                                               @change="selected = $event.target.checked ? @js(array_map('strval', array_keys($masks))) : []">
                                    </th>
                                    <th class="px-3 py-3">Mask</th>
                                    <th class="px-3 py-3">Routes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($masks as $mask => $routes)
                                    <tr class="transition" :class="selected.includes(@js((string) $mask)) && 'bg-danger/[0.03]'">
                                        <td class="py-3 pl-6">
                                            <input type="checkbox" class="size-4 cursor-pointer accent-navy-900" aria-label="Select {{ $mask }}"
                                                   value="{{ $mask }}" x-model="selected">
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 font-semibold text-ink">
                                            {{ $mask }}
                                            @if ((string) $mask === trim((string) $user->DefaultMask))
                                                <span class="ml-1 rounded bg-navy-900 px-1.5 py-0.5 text-[10px] font-semibold text-white">Default</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach ($routes as $code => $route)
                                                    @php
                                                        $blocked = $route['route'] === \App\Services\GatewayUserService::BLOCK_ROUTE;
                                                        $style = $blocked
                                                            ? 'bg-rose-50 text-rose-700 ring-rose-600/20'
                                                            : ($route['status'] === 1 ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-surface text-muted ring-line');
                                                    @endphp
                                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $style }}"
                                                          title="{{ ($networkByCode[$code]['label'] ?? $code) }}: ({{ $route['route'] }}) {{ $route['operator'] }}">
                                                        <span class="size-1.5 rounded-full bg-current"></span>
                                                        {{ $networkByCode[$code]['label'] ?? $code }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="px-6 py-10 text-center text-sm text-muted">No masks yet. Use Add masks to create the first one.</p>
                @endif
            </section>

            {{-- Two-factor contacts --}}
            <section class="{{ $card }} lg:col-span-2"
                     x-data="{
                         adding: @js($errors->has('contacts') || $errors->has('contacts.*')),
                         contacts: @js(old('contacts', [['name' => '', 'mobile' => '', 'email' => '']])),
                         addRow() { if (this.contacts.length < 10) this.contacts.push({ name: '', mobile: '', email: '' }) },
                         removeRow(index) { if (this.contacts.length > 1) this.contacts.splice(index, 1) },
                     }">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4">
                    <div>
                        <h3 class="font-semibold">Two-factor contacts</h3>
                        <p class="mt-0.5 text-sm text-muted">Who receives the sign-in codes for this account</p>
                    </div>
                    <button type="button" @click="adding = ! adding"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-navy-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-navy-800">
                        <x-heroicon-m-plus class="size-4" />
                        Add contacts
                    </button>
                </header>

                {{-- Add contacts panel --}}
                <div x-show="adding" x-cloak x-transition class="border-b border-line bg-surface/40 p-6">
                    <form method="POST" action="{{ route('gateway.users.2fa.store', $user->Id) }}" class="space-y-4" x-data="{ busy: false }" @submit="busy = true">
                        @csrf

                        <template x-for="(contact, index) in contacts" :key="index">
                            <div class="grid items-end gap-3 sm:grid-cols-[1fr_1fr_1.3fr_auto]">
                                <div>
                                    <label class="field-label" :for="'tfa-name-' + index">Name <span class="font-normal text-muted">(optional)</span></label>
                                    <input :id="'tfa-name-' + index" type="text" :name="`contacts[${index}][name]`" x-model="contact.name" maxlength="100" class="field-input bg-white">
                                </div>
                                <div>
                                    <label class="field-label" :for="'tfa-mobile-' + index">Mobile</label>
                                    <input :id="'tfa-mobile-' + index" type="text" inputmode="tel" :name="`contacts[${index}][mobile]`" x-model="contact.mobile" placeholder="0300 1234567" required class="field-input bg-white tabular-nums">
                                </div>
                                <div>
                                    <label class="field-label" :for="'tfa-email-' + index">Email</label>
                                    <input :id="'tfa-email-' + index" type="email" :name="`contacts[${index}][email]`" x-model="contact.email" placeholder="name@company.com" required class="field-input bg-white">
                                </div>
                                <button type="button" class="grid size-11 place-items-center rounded-lg text-muted transition hover:bg-white hover:text-danger disabled:opacity-30"
                                        :disabled="contacts.length === 1" @click="removeRow(index)" title="Remove">
                                    <x-heroicon-o-trash class="size-4.5" />
                                </button>
                            </div>
                        </template>

                        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                            <button type="button" class="inline-flex items-center gap-1.5 text-sm font-medium text-navy-900 hover:underline disabled:opacity-40" :disabled="contacts.length >= 10" @click="addRow()">
                                <x-heroicon-m-plus class="size-4" /> Add another
                            </button>
                            <div class="flex gap-2">
                                <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-white hover:text-ink" @click="adding = false">Cancel</button>
                                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:opacity-60" :disabled="busy">
                                    <svg x-show="busy" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                                    <span x-text="busy ? 'Saving…' : 'Save contacts'">Save contacts</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- Contact list --}}
                @if ($twoFactor)
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-surface/60 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                                <tr>
                                    <th class="px-6 py-3">Name</th>
                                    <th class="px-3 py-3">Mobile</th>
                                    <th class="px-3 py-3">Email</th>
                                    <th class="px-3 py-3">Added</th>
                                    <th class="px-6 py-3">Last sign-in</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($twoFactor as $contact)
                                    <tr>
                                        <td class="whitespace-nowrap px-6 py-3 font-medium text-ink">
                                            {{ $contact->Name ?: '—' }}
                                            @if ((int) $contact->status !== 1)
                                                <span class="ml-1 rounded bg-surface px-1.5 py-0.5 text-[10px] font-semibold text-muted">Off</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 tabular-nums text-ink">{{ $contact->MobileNo }}</td>
                                        <td class="px-3 py-3 text-ink">{{ $contact->Email ?: '—' }}</td>
                                        <td class="whitespace-nowrap px-3 py-3 tabular-nums text-muted">
                                            {{ $contact->CreatedDate ? \Illuminate\Support\Carbon::parse($contact->CreatedDate)->format('d M Y') : '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-3 tabular-nums text-muted">
                                            {{ $contact->LoginDatetime ? \Illuminate\Support\Carbon::parse($contact->LoginDatetime)->format('d M Y, H:i') : 'Never' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="px-6 py-10 text-center text-sm text-muted">No two-factor contacts yet.</p>
                @endif
            </section>
        </div>
    </div>
@endsection