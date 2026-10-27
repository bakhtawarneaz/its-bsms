{{-- Mobile number chips. Posts valid numbers as "receivers" and emits "numbers-stats". --}}
<div x-data="{
        chips: [],
        draft: '',
        allowInt: @js($allowInternational),
        init() { this.addTokens(@js(old('receivers', ''))) },
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
                if (existing) { existing.dupes++; continue; }
                this.chips.push(value ? { value, valid: true, dupes: 0 } : { value: token.trim(), valid: false, dupes: 0 });
            }
        },
        commit() { if (this.draft.trim()) this.addTokens(this.draft); this.draft = ''; },
        typed() { if (/[\s,;]/.test(this.draft) || this.normalize(this.draft)) this.commit(); },
        get receiverList() { return this.chips.filter((c) => c.valid).map((c) => c.value).join(',') },
        get stats() {
            const valid = this.chips.filter((c) => c.valid).length;
            return { valid, rejected: this.chips.length - valid, duplicates: this.chips.reduce((s, c) => s + c.dupes, 0) };
        },
     }"
     x-effect="$dispatch('numbers-stats', stats)">
    <input type="hidden" name="receivers" :value="receiverList">

    <div class="field-input flex min-h-28 cursor-text flex-wrap content-start items-center gap-1.5 py-2" @click="$refs.numberInput.focus()">
        <template x-for="(chip, index) in chips" :key="index + '-' + chip.value">
            <span class="inline-flex items-center gap-1 rounded-full py-1 pr-1 pl-2.5 text-sm font-medium tabular-nums ring-1 ring-inset"
                  :class="chip.valid ? 'bg-navy-900/5 text-navy-900 ring-navy-900/10' : 'bg-rose-50 text-rose-700 ring-rose-600/20 line-through'">
                <span x-text="chip.value"></span>
                <button type="button" class="grid size-5 place-items-center rounded-full transition hover:bg-black/10" @click.stop="chips.splice(index, 1)" :aria-label="'Remove ' + chip.value">
                    <x-heroicon-m-x-mark class="size-3.5" />
                </button>
            </span>
        </template>
        <input x-ref="numberInput" type="text" inputmode="tel" x-model="draft"
               @input="typed()" @keydown.enter.prevent="commit()" @keydown.backspace="draft === '' && chips.pop()"
               @paste.prevent="addTokens($event.clipboardData.getData('text')); draft = ''" @blur="commit()"
               :placeholder="chips.length ? '' : '03001234567, 03111234567 …'"
               class="min-w-40 flex-1 border-0 bg-transparent p-1 text-sm tabular-nums outline-none focus:ring-0" aria-label="Add mobile number">
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
            <x-heroicon-m-check-circle class="size-3.5" /><span x-text="stats.valid.toLocaleString()"></span> valid
        </span>
        <span x-show="stats.rejected" x-cloak class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 font-medium text-rose-700 ring-1 ring-inset ring-rose-600/20">
            <x-heroicon-m-x-circle class="size-3.5" /><span x-text="stats.rejected"></span> invalid
        </span>
        <span x-show="stats.duplicates" x-cloak class="inline-flex items-center gap-1.5 rounded-full bg-surface px-2.5 py-1 font-medium text-muted ring-1 ring-inset ring-line">
            <x-heroicon-m-document-duplicate class="size-3.5" /><span x-text="stats.duplicates"></span> duplicate skipped
        </span>
        <button type="button" x-show="chips.length" x-cloak @click="chips = []; draft = ''" class="ml-auto font-medium text-muted hover:text-danger">Clear all</button>
    </div>
</div>