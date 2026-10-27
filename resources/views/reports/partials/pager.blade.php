{{-- Previous / next pager for reports that skip COUNT(*) on very large tables. --}}
@php($page = $filters['page'])
<div class="flex flex-col gap-3 border-t border-line px-4 py-3 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
    <p>Page <span class="font-medium tabular-nums text-ink">{{ number_format($page) }}</span> · {{ $filters['per_page'] }} per page</p>
    <div class="flex gap-2">
        @if ($page > 1)
            <a href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}" class="inline-flex items-center gap-1 rounded-lg border border-line bg-white px-3 py-2 font-medium text-ink transition hover:border-navy-700/40">
                <x-heroicon-m-chevron-left class="size-4" /> Previous
            </a>
        @endif
        @if ($hasMore)
            <a href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}" class="inline-flex items-center gap-1 rounded-lg border border-line bg-white px-3 py-2 font-medium text-ink transition hover:border-navy-700/40">
                Next <x-heroicon-m-chevron-right class="size-4" />
            </a>
        @endif
    </div>
</div>