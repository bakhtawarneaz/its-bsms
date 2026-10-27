@extends('layouts.app')

@section('title', 'Add gateway user')

@section('content')
    @php
        $listFrom = fn ($value) => array_values(array_filter(array_map('trim', explode(',', (string) $value))));
        $statusOn = old() ? (bool) old('status') : true;
        $initialRoutes = [];
        foreach ($networks as $key => $network) {
            $initialRoutes[$key] = (string) old("routes.{$key}", $network['default'][0]);
        }
        $userTypes = [
            'SMPP' => ['SMPP', 'Binds over SMPP', 'server-stack'],
            'HTTP' => ['HTTP', 'Sends through the HTTP API', 'globe-alt'],
            'DB'   => ['Database', 'Direct database integration', 'circle-stack'],
        ];

        $xIcon = '<svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5.3 5.3a1 1 0 0 1 1.4 0L10 8.6l3.3-3.3a1 1 0 1 1 1.4 1.4L11.4 10l3.3 3.3a1 1 0 0 1-1.4 1.4L10 11.4l-3.3 3.3a1 1 0 0 1-1.4-1.4L8.6 10 5.3 6.7a1 1 0 0 1 0-1.4Z"/></svg>';
        $tagBox = 'field-input flex min-h-11 cursor-text flex-wrap items-center gap-1.5 py-1.5 focus-within:border-navy-700 focus-within:ring-4 focus-within:ring-navy-700/10';
        $card = 'rounded-2xl border border-line bg-white';
    @endphp

    <div class="mx-auto max-w-6xl">
        <a href="{{ route('gateway.users') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted transition hover:text-ink">
            <x-heroicon-m-arrow-left class="size-4" />
            Back to gateway users
        </a>

        <form
            method="POST"
            action="{{ route('gateway.users.store') }}"
            autocomplete="off"
            class="grid items-start gap-6 lg:grid-cols-3"
            x-data="{
                loading: false,
                userType: @js(old('user_type', '')),
                accountType: @js(old('account_type', '')),
                mask: @js(old('default_mask', '')),
                routes: @js($initialRoutes),
                get scomBlocked() { return this.routes.scom === '{{ \App\Services\GatewayUserService::BLOCK_ROUTE }}' },
                get scomConflict() { return ! this.scomBlocked && this.mask !== '' && ! /^\d+$/.test(this.mask) },
            }"
            @submit="loading = true"
            @pageshow.window="loading = false"
        >
            @csrf

            <div class="space-y-6 lg:col-span-2">
                {{-- Logins --}}
                <section class="{{ $card }}">
                    <header class="border-b border-line px-6 py-4">
                        <h3 class="font-semibold">Logins</h3>
                        <p class="mt-0.5 text-sm text-muted">API credentials for SMPP/HTTP and a separate web portal login</p>
                    </header>

                    <div class="grid gap-5 p-6 sm:grid-cols-2">
                        <div>
                            <label for="username" class="field-label">API username</label>
                            <input id="username" name="username" type="text" value="{{ old('username') }}" class="field-input font-mono" required maxlength="50" pattern="[A-Za-z0-9._\-]+" @error('username') aria-invalid="true" @enderror>
                        </div>

                        <div x-data="{ reveal: false }">
                            <label for="password" class="field-label">API password</label>
                            <div class="relative">
                                <input id="password" name="password" type="password" :type="reveal ? 'text' : 'password'" class="field-input pr-16 font-mono" required minlength="4" maxlength="50" autocomplete="new-password" @error('password') aria-invalid="true" @enderror>
                                <button type="button" class="absolute inset-y-0 right-0 px-3.5 text-sm font-medium text-muted hover:text-ink" @click="reveal = !reveal" x-text="reveal ? 'Hide' : 'Show'">Show</button>
                            </div>
                            <p class="field-hint">SMPP clients usually allow 8 characters at most.</p>
                        </div>

                        <div>
                            <label for="web_username" class="field-label">Web username</label>
                            <input id="web_username" name="web_username" type="text" value="{{ old('web_username') }}" class="field-input" required maxlength="50" pattern="[A-Za-z0-9._\-]+" @error('web_username') aria-invalid="true" @enderror>
                        </div>

                        <div x-data="{ reveal: false }">
                            <label for="web_password" class="field-label">Web password</label>
                            <div class="relative">
                                <input id="web_password" name="web_password" type="password" :type="reveal ? 'text' : 'password'" class="field-input pr-16" required minlength="8" autocomplete="new-password" @error('web_password') aria-invalid="true" @enderror>
                                <button type="button" class="absolute inset-y-0 right-0 px-3.5 text-sm font-medium text-muted hover:text-ink" @click="reveal = !reveal" x-text="reveal ? 'Hide' : 'Show'">Show</button>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Contact --}}
                <section class="{{ $card }}">
                    <header class="border-b border-line px-6 py-4">
                        <h3 class="font-semibold">Contact</h3>
                        <p class="mt-0.5 text-sm text-muted">Who owns this integration</p>
                    </header>

                    <div class="grid gap-5 p-6 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="fullname" class="field-label">Full name</label>
                            <input id="fullname" name="fullname" type="text" value="{{ old('fullname') }}" class="field-input" required maxlength="100" @error('fullname') aria-invalid="true" @enderror>
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
                    </div>
                </section>

                {{-- Connection --}}
                <section class="{{ $card }}">
                    <header class="border-b border-line px-6 py-4">
                        <h3 class="font-semibold">Connection</h3>
                        <p class="mt-0.5 text-sm text-muted">How the client connects and from where</p>
                    </header>

                    <div class="space-y-6 p-6">
                        <fieldset>
                            <legend class="field-label">User type</legend>
                            <div class="grid gap-3 sm:grid-cols-3">
                                @foreach ($userTypes as $value => [$label, $hint, $icon])
                                    <label class="choice-card items-start p-4">
                                        <input type="radio" name="user_type" value="{{ $value }}" class="sr-only" x-model="userType" required>
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

                        <div>
                            <label for="ips" class="field-label">Allowed IP addresses</label>
                            <input id="ips" name="ips" type="text" value="{{ old('ips') }}" class="field-input font-mono" placeholder="203.0.113.10, 198.51.100.4" required maxlength="500" @error('ips') aria-invalid="true" @enderror>
                            <p class="field-hint">Only these IPs can connect. Separate multiple IPs with commas.</p>
                        </div>
                    </div>
                </section>

                {{-- Default mask and routes --}}
                <section class="{{ $card }}">
                    <header class="border-b border-line px-6 py-4">
                        <h3 class="font-semibold">Default mask and routes</h3>
                        <p class="mt-0.5 text-sm text-muted">The first mask of this account and the route on each network</p>
                    </header>

                    <div class="space-y-5 p-6">
                        <div class="sm:max-w-sm">
                            <label for="default_mask" class="field-label">Default mask</label>
                            <input id="default_mask" name="default_mask" type="text" x-model="mask" class="field-input" required maxlength="50" @error('default_mask') aria-invalid="true" @enderror>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach ($networks as $key => $network)
                                <div>
                                    <label for="route-{{ $key }}" class="field-label flex items-center gap-2">
                                        <span class="size-2.5 rounded-full {{ $network['dot'] }}"></span>
                                        {{ $network['label'] }}
                                        <span class="text-xs font-normal text-muted">{{ $network['code'] }}</span>
                                    </label>
                                    <select id="route-{{ $key }}" name="routes[{{ $key }}]" x-model="routes.{{ $key }}" class="field-input"
                                            :class="routes.{{ $key }} === '{{ \App\Services\GatewayUserService::BLOCK_ROUTE }}' ? 'text-danger' : ''" @error("routes.{$key}") aria-invalid="true" @enderror>
                                        @foreach ($options['routes'][$key] as $option)
                                            <option value="{{ $option['value'] }}">{{ $option['label'] }} ({{ $option['value'] }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>

                        <p x-show="scomConflict" x-cloak class="flex gap-2 rounded-lg bg-amber-50 px-3 py-2.5 text-sm text-amber-800">
                            <x-heroicon-o-exclamation-triangle class="size-5 shrink-0" />
                            SCOM only accepts numeric (short code) masks. Block SCOM or use a numeric default mask.
                        </p>
                    </div>
                </section>

                {{-- Billing and ownership --}}
                <section class="{{ $card }}">
                    <header class="border-b border-line px-6 py-4">
                        <h3 class="font-semibold">Billing and ownership</h3>
                        <p class="mt-0.5 text-sm text-muted">Key account manager: {{ $creator['fullname'] ?: $creator['username'] }}</p>
                    </header>

                    <div class="space-y-6 p-6">
                        <div>
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
                                <label for="balance" class="field-label">Initial balance</label>
                                <div class="relative">
                                    <input id="balance" name="balance" type="number" min="0" value="{{ old('balance', 0) }}" class="field-input pr-20 tabular-nums" @error('balance') aria-invalid="true" @enderror>
                                    <span class="pointer-events-none absolute inset-y-0 right-3.5 grid place-items-center text-sm text-muted">credits</span>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="reseller_id" class="field-label">Reseller</label>
                                <select id="reseller_id" name="reseller_id" class="field-input" required @error('reseller_id') aria-invalid="true" @enderror>
                                    <option value="">Select reseller</option>
                                    @foreach ($options['resellers'] as $reseller)
                                        <option value="{{ $reseller['value'] }}" @selected((string) old('reseller_id') === (string) $reseller['value'])>{{ $reseller['label'] }} ({{ $reseller['value'] }})</option>
                                    @endforeach
                                </select>
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

                            <div x-data="datePicker(@js(['value' => old('expiry'), 'minDate' => 'today']))">
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
                        </div>
                    </div>
                </section>
            </div>

            {{-- Summary and actions --}}
            <aside class="space-y-6 lg:sticky lg:top-24">
                <section class="{{ $card }} p-5">
                    <h3 class="font-semibold">Before you add</h3>
                    <ul class="mt-3 space-y-2.5 text-sm text-muted">
                        <li class="flex gap-2"><x-heroicon-o-key class="mt-0.5 size-4 shrink-0" /> Share the API and web credentials securely with the client.</li>
                        <li class="flex gap-2"><x-heroicon-o-shield-check class="mt-0.5 size-4 shrink-0" /> Connections from IPs outside the list are rejected.</li>
                        <li class="flex gap-2"><x-heroicon-o-tag class="mt-0.5 size-4 shrink-0" /> More masks can be added after the account is created.</li>
                    </ul>
                </section>

                <div class="{{ $card }} flex flex-col gap-2 p-4">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-navy-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:opacity-70" :disabled="loading || scomConflict">
                        <svg x-show="loading" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                        <span x-text="loading ? 'Creating…' : 'Create gateway user'">Create gateway user</span>
                    </button>
                    <a href="{{ route('gateway.users') }}" class="rounded-lg px-5 py-2.5 text-center text-sm font-medium text-muted transition hover:bg-surface hover:text-ink">Cancel</a>
                </div>
            </aside>
        </form>
    </div>
@endsection