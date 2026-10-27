@extends('layouts.app')

@section('title', 'Campaign SMS')

@section('content')
    @php
        $panel = 'rounded-2xl border border-line bg-white';
        $step = 'grid size-7 shrink-0 place-items-center rounded-full bg-navy-900 text-xs font-semibold text-white';
        $defaultSender = old('sender', count($promotionalMasks) === 1 ? $promotionalMasks[0] : '');
        $oldGroups = array_map('intval', old('groups', []));
    @endphp

    <div class="mb-4">
        <h2 class="font-display text-2xl font-semibold tracking-tight text-ink">Send SMS</h2>
        <p class="mt-1 text-sm text-muted">Campaigns to numbers, groups or a file — confirmed with an OTP</p>
    </div>

    @include('compose.partials.tabs')

    <form method="POST" action="{{ route('compose.campaign.prepare') }}" enctype="multipart/form-data"
          class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]"
          x-data="{
              busy: false,
              sender: @js($defaultSender),
              audience: 'numbers',
              schedule: @js(old('schedule', 'now')),
              fileName: '',
              sms: { text: '', parts: 1, used: 0, unicode: false },
              numbers: { valid: 0, rejected: 0, duplicates: 0 },
              groupCounts: @js(collect($groups)->mapWithKeys(fn ($g) => [(int) $g->id => (int) $g->total_contact])),
              picked: @js($oldGroups),
              allContacts: @js((bool) old('all_contacts')),
              get groupContacts() { return this.picked.reduce((sum, id) => sum + (this.groupCounts[id] || 0), 0) },
              get estimate() { return this.numbers.valid + this.groupContacts },
          }"
          @sms-stats="sms = $event.detail"
          @numbers-stats="numbers = $event.detail"
          @submit="busy = true">
        @csrf

        <div class="space-y-6">
            {{-- 1. Campaign --}}
            <section class="{{ $panel }} p-6">
                <div class="mb-4 flex items-center gap-3">
                    <span class="{{ $step }}">1</span>
                    <div>
                        <h3 class="font-semibold">Campaign</h3>
                        <p class="text-sm text-muted">Only promotional masks can send campaigns</p>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="field-label" for="camp-name">Campaign name <span class="font-normal text-muted">(optional)</span></label>
                        <input id="camp-name" type="text" name="camp_name" value="{{ old('camp_name') }}" maxlength="100" class="field-input" placeholder="e.g. Eid offer">
                        @error('camp_name') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label" for="camp-sender">From</label>
                        @if ($promotionalMasks)
                            <select id="camp-sender" name="sender" x-model="sender" required class="field-input">
                                <option value="">Select a masking</option>
                                @foreach ($promotionalMasks as $mask)
                                    <option value="{{ $mask }}">{{ $mask }}</option>
                                @endforeach
                            </select>
                        @else
                            <p class="rounded-lg border border-danger/30 bg-danger/5 px-4 py-2.5 text-sm text-danger">No promotional masking on this account.</p>
                        @endif
                        @error('sender') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            {{-- 2. Audience --}}
            <section class="{{ $panel }} p-6">
                <div class="mb-4 flex items-center gap-3">
                    <span class="{{ $step }}">2</span>
                    <div>
                        <h3 class="font-semibold">Audience</h3>
                        <p class="text-sm text-muted">Numbers, groups and a file can be combined; duplicates are removed</p>
                    </div>
                </div>

                <div class="mb-4 inline-flex rounded-lg bg-surface p-1" role="tablist">
                    @foreach (['numbers' => 'Numbers', 'groups' => 'Groups', 'file' => 'File'] as $key => $label)
                        <button type="button" role="tab" class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                                :class="audience === @js($key) ? 'bg-white text-ink shadow-xs' : 'text-muted hover:text-ink'"
                                @click="audience = @js($key)">{{ $label }}</button>
                    @endforeach
                </div>

                <div x-show="audience === 'numbers'">
                    @include('compose.partials.number-chips', ['allowInternational' => $allowInternational])
                </div>

                <div x-show="audience === 'groups'" x-cloak>
                    <label class="mb-3 flex cursor-pointer items-center gap-3 rounded-xl border border-line px-4 py-3 text-sm transition hover:border-navy-700/30">
                        <input type="checkbox" name="all_contacts" value="1" x-model="allContacts" class="size-4 accent-navy-900">
                        <span class="font-medium text-ink">All my contacts</span>
                    </label>
                    @if ($groups)
                        <div class="grid max-h-72 gap-2 overflow-y-auto sm:grid-cols-2">
                            @foreach ($groups as $group)
                                <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-line px-4 py-3 text-sm transition hover:border-navy-700/30"
                                       :class="picked.includes({{ (int) $group->id }}) && 'border-navy-900 bg-navy-900/[0.03]'">
                                    <span class="flex items-center gap-3">
                                        <input type="checkbox" name="groups[]" value="{{ $group->id }}" x-model.number="picked" class="size-4 accent-navy-900">
                                        <span class="font-medium text-ink">{{ $group->groupname }}</span>
                                    </span>
                                    <span class="text-xs tabular-nums text-muted">{{ number_format((int) $group->total_contact) }}</span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-muted">No groups yet. Create them on the Groups page.</p>
                    @endif
                </div>

                <div x-show="audience === 'file'" x-cloak>
                    <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-line px-4 py-8 text-center transition hover:border-navy-700/40">
                        <x-heroicon-o-document-arrow-up class="size-8 text-muted" />
                        <span class="text-sm font-medium text-ink" x-text="fileName || 'Choose a CSV, TXT or XLSX file'"></span>
                        <span class="text-xs text-muted">Numbers in the first column · up to 20 MB</span>
                        <input type="file" name="file" accept=".csv,.txt,.xlsx" class="sr-only" @change="fileName = $event.target.files[0]?.name || ''">
                    </label>
                    @error('file') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                </div>

                @error('receivers') <p class="mt-3 text-sm text-danger">{{ $message }}</p> @enderror
            </section>

            {{-- 3. Message --}}
            <section class="{{ $panel }} p-6">
                <div class="mb-4 flex items-center gap-3">
                    <span class="{{ $step }}">3</span>
                    <div>
                        <h3 class="font-semibold">Message</h3>
                        <p class="text-sm text-muted">Urdu switches the message to Unicode (70 characters per SMS)</p>
                    </div>
                </div>
                @include('partials.sms-message', ['name' => 'message', 'id' => 'camp-message', 'value' => '', 'label' => ''])
                @error('message') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </section>

            {{-- 4. Schedule --}}
            <section class="{{ $panel }} p-6">
                <div class="mb-4 flex items-center gap-3">
                    <span class="{{ $step }}">4</span>
                    <h3 class="font-semibold">When</h3>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="choice-card cursor-pointer" :class="schedule === 'now' && 'border-navy-900 bg-navy-900/[0.03]'">
                        <input type="radio" name="schedule" value="now" x-model="schedule" class="choice-radio">
                        <span class="font-medium">Send now</span>
                    </label>
                    <label class="choice-card cursor-pointer" :class="schedule === 'later' && 'border-navy-900 bg-navy-900/[0.03]'">
                        <input type="radio" name="schedule" value="later" x-model="schedule" class="choice-radio">
                        <span class="font-medium">Schedule</span>
                    </label>
                </div>
                <div x-show="schedule === 'later'" x-cloak class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-date-field name="send_date" label="Date" :value="old('send_date', now()->toDateString())" :min="now()->toDateString()" max="{{ now()->addMonths(3)->toDateString() }}" />
                    <div>
                        <label class="field-label" for="send-time">Time</label>
                        <input id="send-time" type="time" name="send_time" value="{{ old('send_time', now()->addHour()->format('H:00')) }}" class="field-input">
                    </div>
                </div>
                @error('send_time') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </section>
        </div>

        {{-- Preview and summary --}}
        <aside class="space-y-6 xl:sticky xl:top-6 xl:self-start">
            <div class="mx-auto w-full max-w-[320px] rounded-[2.5rem] border-[10px] border-navy-950 bg-navy-950 shadow-xl shadow-navy-950/20">
                <div class="overflow-hidden rounded-[1.9rem] bg-surface">
                    <div class="flex flex-col items-center gap-1.5 border-b border-line bg-white px-4 pt-6 pb-3">
                        <span class="grid size-11 place-items-center rounded-full bg-navy-900 text-sm font-semibold text-white" x-text="(sender || '?').slice(0, 2).toUpperCase()">?</span>
                        <span class="max-w-full truncate text-sm font-semibold text-ink" x-text="sender || 'Sender'">Sender</span>
                    </div>
                    <div class="flex min-h-56 flex-col justify-end gap-1 px-3 py-4">
                        <p class="text-center text-[11px] text-muted">Text Message · <span x-text="schedule === 'now' ? 'Now' : 'Scheduled'"></span></p>
                        <div class="max-w-[85%] self-start rounded-2xl rounded-bl-md bg-white px-3.5 py-2.5 text-sm leading-relaxed text-ink shadow-xs ring-1 ring-line" dir="auto">
                            <p class="break-words whitespace-pre-wrap" x-text="sms.text || 'Your message preview appears here.'" :class="! sms.text && 'text-muted italic'"></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="{{ $panel }} space-y-4 p-6">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Typed numbers</dt><dd class="font-medium tabular-nums text-ink" x-text="numbers.valid.toLocaleString()"></dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Group contacts</dt><dd class="font-medium tabular-nums text-ink" x-text="allContacts ? 'All contacts' : groupContacts.toLocaleString()"></dd></div>
                    <div class="flex justify-between" x-show="fileName" x-cloak><dt class="text-muted">File</dt><dd class="max-w-40 truncate font-medium text-ink" x-text="fileName"></dd></div>
                    <div class="flex justify-between"><dt class="text-muted">SMS parts</dt><dd class="font-medium tabular-nums text-ink" x-text="sms.parts"></dd></div>
                    <div class="flex justify-between border-t border-line pt-3">
                        <dt class="font-semibold text-ink">Estimated credits</dt>
                        <dd class="font-display text-lg font-semibold tabular-nums text-ink" x-text="(estimate * sms.parts).toLocaleString() + (fileName || allContacts ? '+' : '')"></dd>
                    </div>
                    @if ($prepaid)
                        <div class="flex justify-between"><dt class="text-muted">Balance</dt><dd class="font-medium tabular-nums text-ink">{{ number_format($balance) }}</dd></div>
                    @endif
                </dl>
                <p class="rounded-lg bg-surface px-3 py-2 text-xs text-muted">Exact numbers and credits are shown on the next step, before you enter the OTP.</p>
                @if ($needsApproval)
                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">Campaigns from this account wait for approval before sending.</p>
                @endif
                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-navy-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="busy || ! sender || ! sms.text.trim()">
                    <svg x-show="busy" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                    <span x-text="busy ? 'Checking…' : 'Review & send'">Review & send</span>
                </button>
            </div>
        </aside>
    </form>

    {{-- Review + OTP --}}
    @if ($review)
        <div class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" x-data="{ busy: false }">
            <div class="absolute inset-0 bg-navy-950/50 backdrop-blur-[2px]"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white shadow-xl shadow-navy-950/20">
                <div class="flex items-start gap-4 border-b border-line p-6">
                    <span class="grid size-11 shrink-0 place-items-center rounded-full bg-navy-900 text-white">
                        <x-heroicon-o-shield-check class="size-6" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="font-display text-lg font-semibold">Confirm campaign</h3>
                        <p class="mt-0.5 truncate text-sm text-muted">{{ $review['name'] }} · {{ $review['sender'] }}</p>
                    </div>
                </div>

                <div class="space-y-4 p-6">
                    <dl class="grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-xl bg-surface px-2 py-3"><dt class="text-xs text-muted">Receivers</dt><dd class="mt-1 font-semibold tabular-nums text-ink">{{ number_format($review['receivers']) }}</dd></div>
                        <div class="rounded-xl bg-surface px-2 py-3"><dt class="text-xs text-muted">Rejected</dt><dd class="mt-1 font-semibold tabular-nums text-ink">{{ number_format($review['rejected']) }}</dd></div>
                        <div class="rounded-xl bg-surface px-2 py-3"><dt class="text-xs text-muted">Duplicate</dt><dd class="mt-1 font-semibold tabular-nums text-ink">{{ number_format($review['duplicates']) }}</dd></div>
                    </dl>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-muted">Credits ({{ $review['cost'] }} per SMS)</dt><dd class="font-semibold tabular-nums text-ink">{{ number_format($review['credits']) }}</dd></div>
                        @if ($review['balance'] !== null)
                            <div class="flex justify-between"><dt class="text-muted">Balance after</dt><dd class="font-medium tabular-nums text-ink">{{ number_format($review['balance']) }}</dd></div>
                        @endif
                        <div class="flex justify-between"><dt class="text-muted">When</dt><dd class="font-medium text-ink">{{ $review['scheduled'] ?? 'Now' }}</dd></div>
                    </dl>
                    @if ($review['approval'])
                        <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">This campaign will wait for approval.</p>
                    @endif

                    <form method="POST" action="{{ route('compose.campaign.confirm') }}" class="space-y-3" @submit="busy = true">
                        @csrf
                        <input type="hidden" name="token" value="{{ $review['token'] }}">
                        <div>
                            <label class="field-label" for="campaign-otp">OTP sent to your login mobile / email</label>
                            <input id="campaign-otp" type="text" name="code" inputmode="numeric" maxlength="5" autocomplete="one-time-code" required autofocus
                                   class="field-input text-center font-mono text-lg tracking-[0.5em]" placeholder="•••••">
                            @error('code') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-navy-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:opacity-60" :disabled="busy">
                            <span x-text="busy ? 'Creating campaign…' : 'Confirm & send'">Confirm & send</span>
                        </button>
                    </form>

                    <div class="flex items-center justify-between text-sm">
                        <form method="POST" action="{{ route('compose.campaign.resend') }}">
                            @csrf
                            <button type="submit" class="font-medium text-navy-900 hover:underline">Resend code</button>
                        </form>
                        <form method="POST" action="{{ route('compose.campaign.cancel') }}">
                            @csrf
                            <button type="submit" class="font-medium text-muted hover:text-ink">Cancel</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection