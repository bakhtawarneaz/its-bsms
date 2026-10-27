@extends('layouts.app')

@section('title', 'Send SMS')

@section('content')
    @php
        $panel = 'rounded-2xl border border-line bg-white';
        $step = 'grid size-7 shrink-0 place-items-center rounded-full bg-navy-900 text-xs font-semibold text-white';
        $defaultSender = old('sender', count($masks) === 1 ? $masks[0] : '');
    @endphp

    <div class="mb-4">
        <h2 class="font-display text-2xl font-semibold tracking-tight text-ink">Send SMS</h2>
        <p class="mt-1 text-sm text-muted">Quick SMS to up to {{ number_format($limit) }} numbers</p>
    </div>
    @include('compose.partials.tabs')
    <form method="POST" action="{{ route('compose.quick') }}" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]"
          x-data="{
              busy: false,
              sender: @js($defaultSender),
              chips: [],
              draft: '',
              allowInt: @js($allowInternational),
              init() {
                  this.addTokens(@js(old('receivers', '')));
              },
              normalize(raw) {
                  const n = String(raw).replace(/[-\s.+'&quot;<>()]/g, '');
                  let m;
                  if ((m = n.match(/^(00923|009203|09203|9203|923|03|3)([0-7])(\d{8})$/))) return '923' + m[2] + m[3];
                  if (this.allowInt && (m = n.match(/^(00447|0447|447)(\d{9})$/))) return '447' + m[2];
                  if (this.allowInt && (m = n.match(/^(001|01|1)(\d{10})$/))) return '1' + m[2];
                  return null;
              },
              addTokens(text) {
                  for (const token of String(text).split(/[\s,;\/\\]+/)) {
                      if (! token.trim()) continue;
                      const value = this.normalize(token);
                      const existing = value && this.chips.find((chip) => chip.valid && chip.value === value);
                      // A repeat is counted on the chip it duplicates, so removing that chip clears it too.
                      if (existing) { existing.dupes++; continue; }
                      this.chips.push(value ? { value, valid: true, dupes: 0 } : { value: token.trim(), valid: false, dupes: 0 });
                  }
              },
              commit() {
                  if (this.draft.trim()) this.addTokens(this.draft);
                  this.draft = '';
              },
              // A complete valid number becomes a chip on its own; separators also commit.
              typed() {
                  if (/[\s,;]/.test(this.draft) || this.normalize(this.draft)) this.commit();
              },
              pasted(event) {
                  this.addTokens(event.clipboardData.getData('text'));
                  this.draft = '';
              },
              backspace() {
                  if (this.draft === '' && this.chips.length) this.chips.pop();
              },
              remove(index) {
                  this.chips.splice(index, 1);
              },
              clearAll() {
                  this.chips = [];
                  this.draft = '';
              },
              get receiverList() {
                  return this.chips.filter((chip) => chip.valid).map((chip) => chip.value).join(',');
              },
              get stats() {
                  const valid = this.chips.filter((chip) => chip.valid).length;
                  const duplicates = this.chips.reduce((sum, chip) => sum + chip.dupes, 0);
                  return { valid, rejected: this.chips.length - valid, duplicates };
              },
              get credits() { return this.stats.valid * this.sms.parts },
              get enough() { return ! this.prepaid || this.credits <= this.balance },
              get ready() { return this.sender && this.stats.valid > 0 && this.stats.valid <= {{ $limit }} && this.sms.text.trim() && this.enough },
          }"
          @sms-stats="sms = $event.detail"
          @submit="busy = true">
        @csrf

        {{-- Steps --}}
        <div class="space-y-6">
            {{-- 1. From --}}
            <section class="{{ $panel }} p-6">
                <div class="mb-4 flex items-center gap-3">
                    <span class="{{ $step }}">1</span>
                    <div>
                        <h3 class="font-semibold">From</h3>
                        <p class="text-sm text-muted">The sender ID receivers will see</p>
                    </div>
                </div>

                @if ($masks)
                    <div class="relative">
                        <x-heroicon-o-identification class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-muted" />
                        <select name="sender" x-model="sender" required class="field-input pl-11">
                            <option value="">Select a masking</option>
                            @foreach ($masks as $mask)
                                <option value="{{ $mask }}">{{ $mask }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <p class="rounded-lg border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">No active masking on this account. Add one from Maskings first.</p>
                @endif
                @error('sender') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </section>

            {{-- 2. To --}}
            <section class="{{ $panel }} p-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="{{ $step }}">2</span>
                        <div>
                            <h3 class="font-semibold">To</h3>
                            <p class="text-sm text-muted">Type or paste numbers — each one becomes a chip</p>
                        </div>
                    </div>
                    <button type="button" x-show="chips.length" x-cloak @click="clearAll()"
                            class="text-sm font-medium text-muted transition hover:text-danger">Clear all</button>
                </div>

                <input type="hidden" name="receivers" :value="receiverList">

                <div class="field-input flex min-h-28 cursor-text flex-wrap content-start items-center gap-1.5 py-2"
                     @click="$refs.numberInput.focus()">
                    <template x-for="(chip, index) in chips" :key="index + '-' + chip.value">
                        <span class="inline-flex items-center gap-1 rounded-full py-1 pr-1 pl-2.5 text-sm font-medium tabular-nums ring-1 ring-inset"
                              :class="chip.valid ? 'bg-navy-900/5 text-navy-900 ring-navy-900/10' : 'bg-rose-50 text-rose-700 ring-rose-600/20 line-through'"
                              :title="chip.valid ? '' : 'Invalid number — will not be sent'">
                            <span x-text="chip.value"></span>
                            <button type="button" class="grid size-5 place-items-center rounded-full transition hover:bg-black/10"
                                    @click.stop="remove(index)" :aria-label="'Remove ' + chip.value">
                                <x-heroicon-m-x-mark class="size-3.5" />
                            </button>
                        </span>
                    </template>

                    <input x-ref="numberInput" type="text" inputmode="tel" x-model="draft"
                           @input="typed()" @keydown.enter.prevent="commit()" @keydown.backspace="backspace()"
                           @paste.prevent="pasted($event)" @blur="commit()"
                           :placeholder="chips.length ? '' : '03001234567, 03111234567 …'"
                           class="min-w-40 flex-1 border-0 bg-transparent p-1 text-sm tabular-nums outline-none focus:ring-0"
                           aria-label="Add mobile number">
                </div>

                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                        <x-heroicon-m-check-circle class="size-3.5" /><span x-text="stats.valid.toLocaleString()"></span> valid
                    </span>
                    <span x-show="stats.rejected" x-cloak class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 font-medium text-rose-700 ring-1 ring-inset ring-rose-600/20">
                        <x-heroicon-m-x-circle class="size-3.5" /><span x-text="stats.rejected"></span> invalid
                    </span>
                    <span x-show="stats.duplicates" x-cloak class="inline-flex items-center gap-1.5 rounded-full bg-surface px-2.5 py-1 font-medium text-muted ring-1 ring-inset ring-line">
                        <x-heroicon-m-document-duplicate class="size-3.5" /><span x-text="stats.duplicates"></span> duplicate skipped
                    </span>
                    <span x-show="stats.valid > {{ $limit }}" x-cloak class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 font-medium text-rose-700">
                        Max {{ number_format($limit) }} numbers
                    </span>
                </div>
                @error('receivers') <p class="mt-2 text-sm text-danger">{{ $message }}</p> @enderror
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

                @include('partials.sms-message', ['name' => 'message', 'id' => 'message', 'value' => '', 'label' => ''])
                @error('message') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </section>
        </div>

        {{-- Preview and summary --}}
        <aside class="space-y-6 xl:sticky xl:top-6 xl:self-start">
            {{-- Phone preview --}}
            <div class="mx-auto w-full max-w-[320px] rounded-[2.5rem] border-[10px] border-navy-950 bg-navy-950 shadow-xl shadow-navy-950/20">
                <div class="overflow-hidden rounded-[1.9rem] bg-surface">
                    <div class="flex flex-col items-center gap-1.5 border-b border-line bg-white px-4 pt-6 pb-3">
                        <span class="grid size-11 place-items-center rounded-full bg-navy-900 text-sm font-semibold text-white"
                              x-text="(sender || '?').slice(0, 2).toUpperCase()">?</span>
                        <span class="max-w-full truncate text-sm font-semibold text-ink" x-text="sender || 'Sender'">Sender</span>
                    </div>
                    <div class="flex min-h-64 flex-col justify-end gap-1 px-3 py-4">
                        <p class="text-center text-[11px] text-muted">Text Message · Now</p>
                        <div class="max-w-[85%] self-start rounded-2xl rounded-bl-md bg-white px-3.5 py-2.5 text-sm leading-relaxed text-ink shadow-xs ring-1 ring-line"
                             dir="auto">
                            <p class="break-words whitespace-pre-wrap" x-text="sms.text || 'Your message preview appears here.'"
                               :class="! sms.text && 'text-muted italic'"></p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Cost summary --}}
            <div class="{{ $panel }} space-y-4 p-6">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted">Receivers</dt>
                        <dd class="font-medium tabular-nums text-ink" x-text="stats.valid.toLocaleString()">0</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted">SMS parts</dt>
                        <dd class="font-medium tabular-nums text-ink">
                            <span x-text="sms.parts"></span>
                            <span x-show="sms.unicode" x-cloak class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">Unicode</span>
                        </dd>
                    </div>
                    <div class="flex justify-between border-t border-line pt-3">
                        <dt class="font-semibold text-ink">Credits</dt>
                        <dd class="font-display text-lg font-semibold tabular-nums text-ink" x-text="credits.toLocaleString()">0</dd>
                    </div>
                    @if ($prepaid)
                        <div class="flex justify-between">
                            <dt class="text-muted">Balance after sending</dt>
                            <dd class="font-medium tabular-nums" :class="enough ? 'text-ink' : 'text-danger'"
                                x-text="(balance - credits).toLocaleString()">{{ number_format($balance) }}</dd>
                        </div>
                    @else
                        <div class="flex justify-between">
                            <dt class="text-muted">Account</dt>
                            <dd class="font-medium text-ink">Postpaid</dd>
                        </div>
                    @endif
                </dl>

                <p x-show="! enough" x-cloak class="rounded-lg bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">Not enough credits for this send.</p>

                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-navy-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="busy || ! ready">
                    <svg x-show="busy" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                    <x-heroicon-o-paper-airplane class="size-4" x-show="! busy" />
                    <span x-text="busy ? 'Sending…' : 'Send to ' + stats.valid.toLocaleString() + (stats.valid === 1 ? ' number' : ' numbers')">Send SMS</span>
                </button>
            </div>
        </aside>
    </form>
@endsection