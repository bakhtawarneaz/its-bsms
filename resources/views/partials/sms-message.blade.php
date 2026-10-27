{{--
    Message textarea with the legacy SMS counter (GSM 160/153, Unicode 70/67).
    Params: $name (default "message"), $value, $id, $rows, $label.
--}}
@php
    $name ??= 'message';
    $id ??= 'message';
    $rows ??= 6;
    $label ??= 'Message';
@endphp
<div x-data="{
        text: @js(old($name, $value ?? '')),
        gsm: '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\x22#¤%&\x27()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà',
        ext: '\f^{}\\[~]|€',
        get stats() {
            const chars = [...this.text];
            let used = 0;
            for (const ch of chars) {
                if (this.gsm.includes(ch)) used += 1;
                else if (this.ext.includes(ch)) used += 2;
                else return { unicode: true, used: chars.length, parts: chars.length <= 70 ? 1 : Math.ceil(chars.length / 67) };
            }
            return { unicode: false, used, parts: used <= 160 ? 1 : Math.ceil(used / 153) };
        },
    }" x-effect="$dispatch('sms-stats', { ...stats, text })">
    <label class="field-label" for="{{ $id }}">{{ $label }}</label>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" x-model="text" required
              class="field-input resize-y leading-relaxed" dir="auto"></textarea>
    <p class="mt-1.5 flex items-center justify-between text-xs">
        <span :class="stats.unicode ? 'font-semibold text-danger' : 'text-muted'"
              x-text="stats.used + (stats.unicode ? ' unicode character(s)' : ' character(s)')"></span>
        <span class="rounded-full bg-surface px-2 py-0.5 font-medium tabular-nums text-ink" x-text="stats.parts + (stats.parts === 1 ? ' SMS' : ' SMS parts')"></span>
    </p>
</div>