{{-- DLR forwarding toggle + JSON config (users.StatusCallingAPI / users.CallingAPI). Expects $enabled and $json. --}}
<div x-data="{
        on: @js((bool) $enabled),
        json: @js((string) $json),
        get check() {
            if (! this.json.trim()) return 'empty';
            try {
                const value = JSON.parse(this.json);
                return value !== null && typeof value === 'object' ? 'valid' : 'invalid';
            } catch (e) {
                return 'invalid';
            }
        },
    }">
    <span class="field-label">DLR Forwarding Status</span>
    <label class="inline-flex cursor-pointer items-center gap-3">
        <input type="checkbox" name="dlr_forward" value="1" class="peer sr-only" x-model="on">
        <span class="relative h-6 w-11 rounded-full bg-line transition peer-checked:bg-success peer-focus-visible:ring-4 peer-focus-visible:ring-navy-700/20 after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
        <span class="text-sm text-muted">Forward delivery reports to this account's endpoint</span>
    </label>

    <div x-show="on" x-cloak x-transition class="mt-4">
        <div class="mb-1.5 flex items-center justify-between gap-3">
            <label for="calling-api" class="field-label mb-0">Forwarding JSON</label>
            <span x-show="check === 'valid'" class="inline-flex items-center gap-1 text-xs font-medium text-success">
                <x-heroicon-m-check-circle class="size-4" /> Valid JSON
            </span>
            <span x-show="check === 'invalid'" x-cloak class="inline-flex items-center gap-1 text-xs font-medium text-danger">
                <x-heroicon-m-exclamation-circle class="size-4" /> Invalid JSON
            </span>
        </div>
        <textarea id="calling-api" name="calling_api" rows="8" spellcheck="false" autocomplete="off"
                  x-model="json" :disabled="! on"
                  :class="check === 'invalid' && 'border-danger focus:ring-danger/20'"
                  class="field-input font-mono text-[13px] leading-relaxed"
                  placeholder='{ "url": "https://client.example.com/dlr", "method": "POST" }'
                  @error('calling_api') aria-invalid="true" @enderror></textarea>
        @error('calling_api') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
        <p class="mt-1.5 text-xs text-muted">Paste a JSON object or array. It is saved exactly as entered.</p>
    </div>
</div>