@extends('layouts.app')

@section('title', 'Users')

@use('App\Enums\UserType')

@section('content')
    @inject('permissions', 'App\Support\Permissions')
    @php
        $tabs = ['' => 'All', 'active' => 'Active', 'expired' => 'Expired', 'disabled' => 'Disabled', 'blocked' => 'Blocked'];
        $pill = [
            'active'   => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            'expired'  => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            'disabled' => 'bg-surface text-muted ring-line',
            'blocked'  => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        ];
        $types = collect(UserType::cases())->reject(fn ($t) => $t->value === 'superadmin');
        // Builds a list URL that keeps current filters, applies $changes and drops empty values.
        $listUrl = fn (array $changes = []) => route('users.index', array_filter(
            array_merge(request()->except('page'), $changes),
            fn ($v) => $v !== null && $v !== ''
        ));
        $hasFilters = $filters['search'] || $filters['type'] || $filters['account'];
        $iconButton = 'grid size-8 place-items-center rounded-lg text-muted transition hover:bg-surface hover:text-ink';
        $canEdit = $permissions->has(\App\Support\Permissions::EDIT_USERS);
    @endphp

    <div x-data="{ remove: null, credit: null }">
        {{-- Page header --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-display text-2xl font-semibold tracking-tight text-ink">Users</h2>
                <p class="mt-1 text-sm text-muted">{{ number_format($counts['all']) }} accounts match the current filters</p>
            </div>
            <a href="{{ route('users.create') }}"
               class="inline-flex items-center gap-2 self-start rounded-lg bg-navy-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 sm:self-auto">
                <x-heroicon-m-plus class="size-4" />
                Add user
            </a>
        </div>

        <div class="overflow-hidden rounded-2xl border border-line bg-white">

            {{-- State tabs --}}
            <nav class="flex gap-1 overflow-x-auto border-b border-line px-4" aria-label="Status">
                @foreach ($tabs as $key => $label)
                    @php $active = ($filters['state'] ?? '') === $key; @endphp
                    <a href="{{ $listUrl(['state' => $key]) }}"
                       @class([
                           'relative flex shrink-0 items-center gap-2 px-3 py-3.5 text-sm font-medium transition',
                           'text-ink after:absolute after:inset-x-2 after:-bottom-px after:h-0.5 after:rounded-full after:bg-navy-900' => $active,
                           'text-muted hover:text-ink' => ! $active,
                       ])>
                        {{ $label }}
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs tabular-nums',
                            'bg-navy-900 text-white' => $active,
                            'bg-surface text-muted' => ! $active,
                        ])>{{ number_format($counts[$key ?: 'all'] ?? 0) }}</span>
                    </a>
                @endforeach
            </nav>

            {{-- Filters --}}
            <form method="GET" action="{{ route('users.index') }}" class="flex flex-col gap-3 border-b border-line p-4 lg:flex-row lg:items-center">
                @if ($filters['state'])
                    <input type="hidden" name="state" value="{{ $filters['state'] }}">
                @endif

                <label class="relative flex-1">
                    <span class="sr-only">Search users</span>
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted" />
                    <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search by ID, username, name, email or mobile" class="field-input pl-10">
                </label>

                <select name="type" onchange="this.form.requestSubmit()" class="field-input lg:w-48">
                    <option value="">All types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected($filters['type'] === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>

                <select name="account" onchange="this.form.requestSubmit()" class="field-input lg:w-44">
                    <option value="">All accounts</option>
                    <option value="prepaid" @selected($filters['account'] === 'prepaid')>Prepaid</option>
                    <option value="postpaid" @selected($filters['account'] === 'postpaid')>Postpaid</option>
                </select>

                @if ($hasFilters)
                    <a href="{{ route('users.index', array_filter(['state' => $filters['state']])) }}"
                       class="inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink">
                        <x-heroicon-m-arrow-path class="size-4" />
                        Reset
                    </a>
                @endif
            </form>

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-surface/60 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                        <tr>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Admin</th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3 text-right">Account</th>
                            <th class="px-4 py-3">Expiry</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($users as $user)
                            @php
                                $type = UserType::tryFrom(strtolower(trim((string) $user->user_type)));
                                $expiry = $user->user_expiry ? \Illuminate\Support\Carbon::parse($user->user_expiry) : null;
                                $isExpired = $expiry?->endOfDay()->isPast() ?? false;
                                $mobile = trim(explode(',', (string) $user->user_mobile)[0]);
                                $email = trim(explode(',', (string) $user->email)[0]);
                                $isOn = (int) $user->status === 1;
                                $isPrepaid = strtolower(trim((string) $user->account_type)) === 'prepaid';
                            @endphp
                            <tr class="transition hover:bg-surface/50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-navy-900 text-xs font-semibold uppercase text-white">
                                            {{ mb_substr($user->username, 0, 2) }}
                                        </span>
                                        <div class="min-w-0">
                                            @if ($canEdit)
                                                <a href="{{ route('users.edit', $user->id) }}" class="block truncate font-semibold text-ink hover:underline">{{ $user->username }}</a>
                                            @else
                                                <p class="truncate font-semibold text-ink">{{ $user->username }}</p>
                                            @endif
                                            <p class="truncate text-xs text-muted">#{{ $user->id }} · {{ $user->fullname ?: '—' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-md bg-surface px-2 py-1 text-xs font-medium text-ink">
                                        {{ $type?->label() ?? ucfirst((string) $user->user_type) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-muted">{{ $user->admin_username ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <p class="max-w-56 truncate text-ink">{{ $email ?: '—' }}</p>
                                    <p class="text-xs tabular-nums text-muted">{{ $mobile ?: '—' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <p class="font-medium capitalize text-ink">{{ $user->account_type ?: '—' }}</p>
                                    @if ($isPrepaid)
                                        <p class="text-xs tabular-nums text-muted">{{ number_format((int) $user->balance) }} credits</p>
                                    @endif
                                </td>
                                <td @class(['whitespace-nowrap px-4 py-3 tabular-nums', 'text-danger' => $isExpired, 'text-muted' => ! $isExpired])>
                                    {{ $expiry?->format('d M Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium capitalize ring-1 ring-inset {{ $pill[$user->state] ?? $pill['disabled'] }}">
                                        <span class="size-1.5 rounded-full bg-current"></span>
                                        {{ $user->state }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($canEdit)
                                            {{-- Sign-in toggle --}}
                                            <form method="POST" action="{{ route('users.status', $user->id) }}" class="mr-1"
                                                  x-data="{ on: @js($isOn), busy: false }" @submit="busy = true; on = !on">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="{{ $isOn ? 0 : 1 }}">
                                                <button type="submit" role="switch" aria-checked="{{ $isOn ? 'true' : 'false' }}"
                                                        title="{{ $isOn ? 'Disable sign-in' : 'Enable sign-in' }}"
                                                        class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-200 disabled:cursor-wait"
                                                        :class="on ? 'bg-success' : 'bg-line'" :disabled="busy">
                                                    <span class="sr-only">Toggle sign-in for {{ $user->username }}</span>
                                                    <span class="inline-block size-4 rounded-full bg-white shadow transition-transform duration-200"
                                                          :class="on ? 'translate-x-4.5' : 'translate-x-0.5'"></span>
                                                </button>
                                            </form>

                                            @if ($user->state === 'blocked')
                                                <form method="POST" action="{{ route('users.unblock', $user->id) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="{{ $iconButton }}" title="Unblock sign-in">
                                                        <x-heroicon-o-lock-open class="size-4.5" />
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- Credits (prepaid only); an empty slot keeps the icons aligned on postpaid rows --}}
                                            @if ($isPrepaid)
                                                <button type="button" class="{{ $iconButton }}" title="Add or deduct credits"
                                                        @click="credit = { name: @js($user->username), url: @js(route('users.balance', $user->id)), balance: {{ (int) $user->balance }}, action: 'add', amount: '' }">
                                                    <x-heroicon-o-banknotes class="size-4.5" />
                                                </button>
                                            @else
                                                <span class="size-8 shrink-0" aria-hidden="true"></span>
                                            @endif

                                            <a href="{{ route('users.edit', $user->id) }}" class="{{ $iconButton }}" title="Edit">
                                                <x-heroicon-o-pencil-square class="size-4.5" />
                                            </a>

                                            <button type="button" class="{{ $iconButton }} hover:bg-danger/10 hover:text-danger" title="Delete"
                                                    @click="remove = { name: @js($user->username), url: @js(route('users.destroy', $user->id)) }">
                                                <x-heroicon-o-trash class="size-4.5" />
                                            </button>
                                        @else
                                            <span class="text-xs text-muted">View only</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-16 text-center">
                                    <x-heroicon-o-users class="mx-auto size-10 text-muted/50" />
                                    <p class="mt-3 font-medium text-ink">No users found</p>
                                    <p class="mt-1 text-sm text-muted">Try a different search or clear the filters.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Footer --}}
            @if ($users->total() > 0)
                <div class="flex flex-col gap-3 border-t border-line px-4 py-3 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
                    <p>Showing <span class="font-medium text-ink">{{ $users->firstItem() }}–{{ $users->lastItem() }}</span> of <span class="font-medium text-ink">{{ number_format($users->total()) }}</span></p>
                    {{ $users->onEachSide(1)->links() }}
                </div>
            @endif
        </div>

        {{-- Delete confirmation --}}
        <div x-show="remove" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" aria-labelledby="delete-title"
             @keydown.escape.window="remove = null">
            <div x-show="remove" x-transition.opacity.duration.200ms class="absolute inset-0 bg-navy-950/50 backdrop-blur-[2px]" @click="remove = null"></div>

            <div x-show="remove"
                 x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="opacity-0 scale-95 translate-y-2" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl shadow-navy-950/20">
                <div class="flex gap-4">
                    <span class="grid size-11 shrink-0 place-items-center rounded-full bg-danger/10 text-danger">
                        <x-heroicon-o-exclamation-triangle class="size-6" />
                    </span>
                    <div>
                        <h3 id="delete-title" class="font-display text-lg font-semibold">Delete <span x-text="remove?.name"></span>?</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-muted">
                            The account, its maskings and opening balance entries are removed permanently.
                            Accounts with sending or billing history, sub users or remaining credits can't be deleted. Disable them instead.
                        </p>
                    </div>
                </div>

                <form method="POST" :action="remove?.url" class="mt-6 flex justify-end gap-2" x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink" @click="remove = null">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-danger px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60" :disabled="busy">
                        <svg x-show="busy" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                        <span x-text="busy ? 'Deleting…' : 'Delete user'">Delete user</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Credits --}}
        <div x-show="credit" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" aria-labelledby="credit-title"
            @keydown.escape.window="credit = null">
            <div x-show="credit" x-transition.opacity.duration.200ms class="absolute inset-0 bg-navy-950/50 backdrop-blur-[2px]" @click="credit = null"></div>

            <template x-if="credit">
                <div class="relative w-full max-w-md rounded-2xl bg-white shadow-xl shadow-navy-950/20"
                    x-data="{ busy: false, get next() { return credit.balance + (credit.action === 'add' ? 1 : -1) * Number(credit.amount || 0) } }"
                    x-init="$nextTick(() => $refs.amount.focus())">
                    <div class="flex items-start gap-4 border-b border-line p-6">
                        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-navy-900 text-white">
                            <x-heroicon-o-banknotes class="size-6" />
                        </span>
                        <div class="min-w-0">
                            <h3 id="credit-title" class="font-display text-lg font-semibold">Credits for <span x-text="credit.name"></span></h3>
                            <p class="mt-0.5 text-sm text-muted">Current balance: <span class="font-medium text-ink tabular-nums" x-text="credit.balance.toLocaleString()"></span> credits</p>
                        </div>
                    </div>

                    <form method="POST" :action="credit.url" class="space-y-5 p-6" @submit="busy = true">
                        @csrf

                        {{-- Add / deduct --}}
                        <div class="grid grid-cols-2 gap-1 rounded-xl bg-surface p-1">
                            <label class="cursor-pointer">
                                <input type="radio" name="action" value="add" class="peer sr-only" x-model="credit.action">
                                <span class="flex items-center justify-center gap-2 rounded-lg py-2 text-sm font-medium text-muted transition peer-checked:bg-white peer-checked:text-success peer-checked:shadow-sm">
                                    <x-heroicon-m-plus-circle class="size-4" /> Add
                                </span>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="action" value="deduct" class="peer sr-only" x-model="credit.action">
                                <span class="flex items-center justify-center gap-2 rounded-lg py-2 text-sm font-medium text-muted transition peer-checked:bg-white peer-checked:text-danger peer-checked:shadow-sm">
                                    <x-heroicon-m-minus-circle class="size-4" /> Deduct
                                </span>
                            </label>
                        </div>

                        <div>
                            <label for="credit-amount" class="field-label">Credits</label>
                            <div class="relative">
                                <input id="credit-amount" x-ref="amount" name="amount" type="text" inputmode="numeric" autocomplete="off"
                                    class="field-input pr-20 text-lg tabular-nums" placeholder="0"
                                    :value="credit.amount ? Number(credit.amount).toLocaleString() : ''"
                                    @input="credit.amount = $event.target.value.replace(/[^0-9]/g, '').slice(0, 10); $event.target.value = credit.amount ? Number(credit.amount).toLocaleString() : ''">
                                <span class="pointer-events-none absolute inset-y-0 right-3.5 grid place-items-center text-sm text-muted">credits</span>
                            </div>
                        </div>

                        {{-- Live preview --}}
                        <div class="flex items-center justify-between rounded-xl border px-4 py-3 transition"
                            :class="next < 0 ? 'border-danger/30 bg-danger/5' : 'border-line bg-surface/60'">
                            <span class="text-sm text-muted">New balance</span>
                            <span class="font-display text-xl font-semibold tabular-nums" :class="next < 0 ? 'text-danger' : 'text-ink'" x-text="next.toLocaleString()"></span>
                        </div>
                        <p x-show="next < 0" x-cloak class="-mt-2 text-sm text-danger">This user doesn't have that many credits to deduct.</p>

                        <div class="flex justify-end gap-2 pt-1">
                            <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink" @click="credit = null">Cancel</button>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="credit.action === 'add' ? 'bg-navy-900 hover:bg-navy-800' : 'bg-danger hover:opacity-90'"
                                    :disabled="busy || !Number(credit.amount) || next < 0">
                                <svg x-show="busy" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                                <span x-text="busy ? 'Saving…' : (credit.action === 'add' ? 'Add credits' : 'Deduct credits')">Add credits</span>
                            </button>
                        </div>
                    </form>
                </div>
            </template>
        </div>
    </div>
@endsection