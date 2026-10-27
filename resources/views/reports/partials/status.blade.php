@php
    $text = trim((string) $status);
    $lower = strtolower($text);
    $style = match (true) {
        $text === '' => 'bg-surface text-muted ring-line',
        str_contains($lower, 'undeliv'), str_contains($lower, 'fail'), str_contains($lower, 'reject'), str_contains($lower, 'expire'), str_contains($lower, 'block') => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        str_contains($lower, 'deliv') => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        default => 'bg-sky-50 text-sky-700 ring-sky-600/20',
    };
@endphp
<span class="inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $style }}">{{ $text ?: '—' }}</span>