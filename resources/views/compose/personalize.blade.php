@extends('layouts.app')

@section('title', 'Personalize SMS')

@section('content')
    @php
        $panel = 'rounded-2xl border border-line bg-white';
        $step = 'grid size-7 shrink-0 place-items-center rounded-full bg-navy-900 text-xs font-semibold text-white';
        $defaultSender = old('sender', count($masks) === 1 ? $masks[0] : '');
    @endphp

    <div class="mb-4">
        <h2 class="font-display text-2xl font-semibold tracking-tight text-ink">Send SMS</h2>
        <p class="mt-1 text-sm text-muted">One message per contact — values come from the columns of your file</p>
    </div>

    @include('compose.partials.tabs')

    <form method="POST" action="{{ route('compose.personalize.send') }}" enctype="multipart/form-data"
          class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]"
          x-data="{
              busy: false,
              sender: @js($defaultSender),
              schedule: @js(old('schedule', 'now')),
              fileName: '',
              header: [],
              sample: [],
              rows: null,
              sms: { text: '', parts: 1, used: 0, unicode: false },
              get keys() {
                  const count = this.header.length > 1 ? Math.min(this.header.length - 1, 25) : 9;
                  return Array.from({ length: count }, (_, i) => {
                      const col = String.fromCharCode(66 + i);
                      return { key: '$' + col + '$', col: col, label: this.header[i + 1] || '' };
                  });
              },
              get preview() {
                  return this.sms.text.replace(/\$([B-Z])\$/g, (match, col) => {
                      const value = this.sample[col.charCodeAt(0) - 65];
                      return value !== undefined ? value : '[' + col + ']';
                  });
              },
              insert(key) {
                  const el = document.getElementById('pers-message');
                  if (! el) return;
                  const start = el.selectionStart ?? el.value.length;
                  el.setRangeText(key, start, el.selectionEnd ?? start, 'end');
                  el.dispatchEvent(new Event('input', { bubbles: true }));
                  el.focus();
              },
              async readFile(file) {
                  this.fileName = file ? file.name : '';
                  this.header = []; this.sample = []; this.rows = null;
                  if (! file || ! /\.(csv|txt)$/i.test(file.name)) return;
                  const whole = file.size <= 5 * 1024 * 1024;
                  const text = await (whole ? file : file.slice(0, 65536)).text();
                  const lines = text.replace(/^\uFEFF/, '').split(/\r?\n/).filter(line => line.trim() !== '');
                  const split = line => line.split(',').map(value => value.replace(/[\x27\x22]/g, '').trim());
                  this.header = lines.length ? split(lines[0]) : [];
                  this.sample = lines.length > 1 ? split(lines[1]) : [];
                  if (whole) this.rows = Math.max(lines.length - 1, 0);
              },
          }"
          @sms-stats="sms = $event.detail"
          @submit="busy = true">
        @csrf

        <div class="space-y-6">
            {{-- 1. Campaign --}}
            <section class="{{ $panel }} p-6">
                <div class="mb-4 flex items-center gap-3">
                    <span class="{{ $step }}">1</span>
                    <div>
                        <h3 class="font-semibold">Campaign</h3>
                        <p class="text-sm text-muted">Any active masking of this account can be used</p>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="field-label" for="pers-name">Campaign name</label>
                        <input id="pers-name" type="text" name="camp_name" value="{{ old('camp_name') }}" maxlength="100" required class="field-input" placeholder="e.g. Fee reminder October">
                        @error('camp_name') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label" for="pers-sender">From</label>
                        @if ($masks)
                            <select id="pers-sender" name="sender" x-model="sender" required class="field-input">
                                <option value="">Select a masking</option>
                                @foreach ($masks as $mask)
                                    <option value="{{ $mask }}">{{ $mask }}</option>
                                @endforeach
                            </select>
                        @else
                            <p class="rounded-lg border border-danger/30 bg-danger/5 px-4 py-2.5 text-sm text-danger">No active masking on this account.</p>
                        @endif
                        @error('sender') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            {{-- 2. Contact file --}}
            <section class="{{ $panel }} p-6">
                <div class="mb-4 flex items-center gap-3">
                    <span class="{{ $step }}">2</span>
                    <div>
                        <h3 class="font-semibold">Contact file</h3>
                        <p class="text-sm text-muted">First row is the header · column A = mobile number · columns B to Z = values</p>
                    </div>
                </div>

                <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-line px-4 py-8 text-center transition hover:border-navy-700/40"
                       :class="fileName && 'border-navy-900/40 bg-navy-900/[0.02]'">
                    <x-heroicon-o-document-arrow-up class="size-8 text-muted" />
                    <span class="text-sm font-medium text-ink" x-text="fileName || 'Choose a CSV, TXT or XLSX file'"></span>
                    <span class="text-xs text-muted">Up to {{ number_format(\App\Support\PersonalizeFile::MAX_ROWS) }} rows · 20 MB</span>
                    <input type="file" name="file" accept=".csv,.txt,.xlsx" required class="sr-only" @change="readFile($event.target.files[0])">
                </label>
                @error('file') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror

                {{-- Header + first row of a CSV, so the keywords can be matched to real columns --}}
                <div x-show="header.length" x-cloak class="mt-4 overflow-x-auto rounded-xl border border-line">
                    <table class="min-w-full text-left text-xs">
                        <thead class="bg-surface text-muted">
                            <tr>
                                <template x-for="(name, i) in header.slice(0, 26)" :key="i">
                                    <th class="px-3 py-2 font-medium whitespace-nowrap">
                                        <span class="font-mono text-ink" x-text="String.fromCharCode(65 + i)"></span>
                                        <span x-text="name ? '· ' + name : ''"></span>
                                    </th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-t border-line">
                                <template x-for="(value, i) in header.slice(0, 26).map((_, i) => sample[i] ?? '')" :key="i">
                                    <td class="max-w-48 truncate px-3 py-2 text-ink" x-text="value || '—'"></td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p x-show="fileName && ! header.length" x-cloak class="mt-3 text-xs text-muted">Column preview is available for CSV/TXT files. XLSX files are read when you send.</p>
            </section>

            {{-- 3. Message --}}
            <section class="{{ $panel }} p-6">
                <div class="mb-4 flex items-center gap-3">
                    <span class="{{ $step }}">3</span>
                    <div>
                        <h3 class="font-semibold">Message</h3>
                        <p class="text-sm text-muted">Click a column to insert its keyword where the cursor is</p>
                    </div>
                </div>

                <div class="mb-3 flex flex-wrap gap-2">
                    <template x-for="item in keys" :key="item.key">
                        <button type="button" @click="insert(item.key)"
                                class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white px-3 py-1.5 text-xs font-medium text-ink transition hover:border-navy-900 hover:bg-navy-900/[0.03]"
                                :class="sms.text.includes(item.key) && 'border-navy-900 bg-navy-900/[0.05]'">
                            <span class="font-mono text-navy-900" x-text="item.key"></span>
                            <span class="max-w-28 truncate text-muted" x-text="item.label || 'Column ' + item.col"></span>
                        </button>
                    </template>
                </div>

                @include('partials.sms-message', ['name' => 'message', 'id' => 'pers-message', 'value' => old('message', ''), 'label' => ''])
                @error('message') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                <p class="mt-2 text-xs text-muted">Keywords $B$ to $Z$ are supported; $A$ is reserved for the number. SMS parts can differ per contact once the values are filled in.</p>
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
                        <label class="field-label" for="pers-time">Time</label>
                        <input id="pers-time" type="time" name="send_time" value="{{ old('send_time', now()->addHour()->format('H:00')) }}" class="field-input">
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
                        <span class="text-[11px] text-muted" x-show="sample[0]" x-cloak x-text="'To ' + sample[0]"></span>
                    </div>
                    <div class="flex min-h-56 flex-col justify-end gap-1 px-3 py-4">
                        <p class="text-center text-[11px] text-muted">Text Message · <span x-text="schedule === 'now' ? 'Now' : 'Scheduled'"></span></p>
                        <div class="max-w-[85%] self-start rounded-2xl rounded-bl-md bg-white px-3.5 py-2.5 text-sm leading-relaxed text-ink shadow-xs ring-1 ring-line" dir="auto">
                            <p class="break-words whitespace-pre-wrap" x-text="preview || 'Your message preview appears here.'" :class="! preview && 'text-muted italic'"></p>
                        </div>
                        <p class="text-center text-[11px] text-muted" x-show="sample.length" x-cloak>Preview with the first contact of your file</p>
                    </div>
                </div>
            </div>

            <div class="{{ $panel }} space-y-4 p-6">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between" x-show="fileName" x-cloak><dt class="text-muted">File</dt><dd class="max-w-40 truncate font-medium text-ink" x-text="fileName"></dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Contacts</dt><dd class="font-medium tabular-nums text-ink" x-text="rows !== null ? rows.toLocaleString() : (fileName ? 'From file' : '0')"></dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Keywords used</dt><dd class="font-medium tabular-nums text-ink" x-text="(sms.text.match(/\$[B-Z]\$/g) || []).length"></dd></div>
                    <div class="flex justify-between"><dt class="text-muted">SMS parts (template)</dt><dd class="font-medium tabular-nums text-ink" x-text="sms.parts"></dd></div>
                    <div class="flex justify-between border-t border-line pt-3">
                        <dt class="font-semibold text-ink">Estimated credits</dt>
                        <dd class="font-display text-lg font-semibold tabular-nums text-ink" x-text="rows !== null ? (rows * sms.parts).toLocaleString() + '+' : '—'"></dd>
                    </div>
                    @if ($prepaid)
                        <div class="flex justify-between"><dt class="text-muted">Balance</dt><dd class="font-medium tabular-nums text-ink">{{ number_format($balance) }}</dd></div>
                    @endif
                </dl>
                <p class="rounded-lg bg-surface px-3 py-2 text-xs text-muted">Exact credits are counted per contact after the values are filled in. Invalid numbers are skipped.</p>
                @if ($needsApproval)
                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">Campaigns from this account wait for approval before sending.</p>
                @endif
                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-navy-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="busy || ! sender || ! fileName || ! sms.text.trim()">
                    <svg x-show="busy" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                    <span x-text="busy ? 'Sending…' : 'Send campaign'">Send campaign</span>
                </button>
            </div>
        </aside>
    </form>
@endsection