@if ($items)
    <ol class="mt-5 space-y-4">
        @foreach ($items as $item)
            <li class="flex items-center gap-4">
                <span class="grid size-7 shrink-0 place-items-center rounded-lg bg-surface text-xs font-semibold text-muted tabular-nums">{{ $loop->iteration }}</span>
                <div class="min-w-0 flex-1">
                    <div class="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                        <span class="truncate font-medium">{{ $item['name'] }}</span>
                        <span class="shrink-0 tabular-nums">
                            <span class="font-semibold">{{ number_format($item['messages']) }}</span>
                            <span class="ml-1.5 text-muted">{{ $item['share'] }}%</span>
                        </span>
                    </div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-surface" x-data="meter({{ $item['width'] }})">
                        <div class="h-full rounded-full transition-[width] duration-1000 ease-out" :style="`width: ${width}%; background: {{ $color }}`"></div>
                    </div>
                </div>
            </li>
        @endforeach
    </ol>
@else
    <p class="mt-6 text-sm text-muted">No messages in this period.</p>
@endif