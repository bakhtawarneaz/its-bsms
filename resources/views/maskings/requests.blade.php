@extends('layouts.app')

@section('title', 'Masking requests')

@section('content')
    @php
        $isImage = fn (?string $file) => in_array(strtolower(pathinfo((string) $file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    @endphp

    <div x-data="{ preview: null }">
        <a href="{{ route('maskings.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted transition hover:text-ink">
            <x-heroicon-m-arrow-left class="size-4" />
            Back to maskings
        </a>

        <div class="mb-6">
            <h2 class="font-display text-2xl font-semibold tracking-tight text-ink">Masking requests</h2>
            <p class="mt-1 text-sm text-muted">{{ number_format($requests->total()) }} pending requests from admins and resellers</p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-line bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-surface/60 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                        <tr>
                            <th class="px-4 py-3">Masking</th>
                            <th class="px-4 py-3">Requested by</th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Letter</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($requests as $item)
                            @php
                                $mask = trim((string) $item->masking_name);
                                $letter = trim((string) $item->masking_letter);
                                $letterUrl = $letter !== '' ? asset('maskingsLetters/' . rawurlencode($letter)) : null;
                            @endphp
                            <tr class="transition hover:bg-surface/50">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-ink">{{ $mask }}</p>
                                    <p class="text-xs text-muted">Request #{{ $item->id }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-ink">{{ $item->requested_by ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <p class="text-ink">{{ trim((string) $item->fullname) ?: '—' }}</p>
                                    <p class="text-xs tabular-nums text-muted">{{ trim((string) $item->nic_no) ?: '—' }} · {{ trim((string) $item->c_numbers) ?: '—' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    @if (! $letterUrl)
                                        <span class="text-muted">—</span>
                                    @elseif ($isImage($letter))
                                        <div x-data="{ broken: false }">
                                            <button type="button" x-show="!broken"
                                                    class="block overflow-hidden rounded-lg border border-line transition hover:border-navy-700/40"
                                                    @click="preview = @js($letterUrl)" title="View letter">
                                                <img src="{{ $letterUrl }}" alt="" class="h-12 w-16 object-cover" loading="lazy" x-on:error="broken = true">
                                            </button>
                                            <span x-show="broken" x-cloak class="inline-flex h-12 w-16 flex-col items-center justify-center gap-0.5 rounded-lg border border-dashed border-line text-[10px] text-muted" title="{{ $letter }}">
                                                <x-heroicon-o-photo class="size-4" />
                                                File missing
                                            </span>
                                        </div>
                                    @else
                                        <a href="{{ $letterUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-sm font-medium text-navy-900 hover:underline">
                                            <x-heroicon-o-document-text class="size-4" /> Open file
                                        </a>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-muted">
                                    {{ $item->crtime ? \Illuminate\Support\Carbon::parse($item->crtime)->format('d M Y, H:i') : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    @if ((int) $item->exists_now === 1)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                            <x-heroicon-m-check class="size-3.5" /> Already added
                                        </span>
                                    @else
                                        <a href="{{ route('maskings.create', ['masks' => $mask]) }}"
                                           class="inline-flex items-center gap-1.5 rounded-lg bg-navy-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-navy-800">
                                            <x-heroicon-m-plus class="size-3.5" /> Add masking
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-16 text-center">
                                    <x-heroicon-o-inbox class="mx-auto size-10 text-muted/50" />
                                    <p class="mt-3 font-medium text-ink">No pending requests</p>
                                    <p class="mt-1 text-sm text-muted">New masking requests from admins and resellers appear here.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($requests->total() > 0)
                <div class="flex flex-col gap-3 border-t border-line px-4 py-3 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
                    <p>Showing <span class="font-medium text-ink">{{ $requests->firstItem() }}–{{ $requests->lastItem() }}</span> of <span class="font-medium text-ink">{{ number_format($requests->total()) }}</span></p>
                    {{ $requests->onEachSide(1)->links() }}
                </div>
            @endif
        </div>

        {{-- Letter preview --}}
        <div x-show="preview" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 grid place-items-center bg-navy-950/70 p-6 backdrop-blur-sm"
             @click="preview = null" @keydown.escape.window="preview = null" role="dialog" aria-modal="true" aria-label="Masking letter">
            <img :src="preview" alt="" class="max-h-[85vh] max-w-full rounded-xl bg-white shadow-2xl" @click.stop>
            <button type="button" class="absolute top-5 right-5 grid size-10 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20" @click="preview = null" aria-label="Close">
                <x-heroicon-m-x-mark class="size-5" />
            </button>
        </div>
    </div>
@endsection