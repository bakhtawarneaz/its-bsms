@extends('layouts.app')

@section('title', $group->groupname)

@section('content')
    <div class="mb-6">
        <a href="{{ route('groups.index') }}" class="inline-flex items-center gap-1 text-sm text-muted transition hover:text-ink">
            <x-heroicon-m-arrow-left class="size-4" /> Groups
        </a>
        <h2 class="mt-2 font-display text-2xl font-semibold tracking-tight text-ink">{{ $group->groupname }}</h2>
        <p class="mt-1 text-sm text-muted">{{ number_format((int) $group->total_contact) }} contacts{{ $group->description ? ' · ' . $group->description : '' }}</p>
    </div>

    <div class="overflow-hidden rounded-2xl border border-line bg-white">
        {{-- Search --}}
        <form method="GET" action="{{ route('groups.show', $group->id) }}" class="flex flex-col gap-3 border-b border-line p-4 sm:flex-row">
            <label class="relative flex-1">
                <span class="sr-only">Search contacts</span>
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted" />
                <input type="search" name="search" value="{{ $search }}" placeholder="Name, email or mobile number" class="field-input pl-10">
            </label>
            @if ($search)
                <a href="{{ route('groups.show', $group->id) }}" class="inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink">
                    <x-heroicon-m-arrow-path class="size-4" /> Reset
                </a>
            @endif
        </form>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-surface/60 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Mobile</th>
                        <th class="px-4 py-3">Email</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($contacts as $contact)
                        <tr class="transition hover:bg-surface/50">
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-ink">{{ $contact->fullname ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-ink">{{ $contact->numbers }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-muted">{{ $contact->emails ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-16 text-center">
                                <x-heroicon-o-users class="mx-auto size-10 text-muted/50" />
                                <p class="mt-3 font-medium text-ink">{{ $search ? 'No contacts match' : 'No contacts in this group' }}</p>
                                <p class="mt-1 text-sm text-muted">
                                    Add contacts to groups from the
                                    <a href="{{ route('contacts.index') }}" class="font-medium text-navy-900 hover:underline">Contacts</a> page.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($contacts->total() > 0)
            <div class="flex flex-col gap-3 border-t border-line px-4 py-3 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
                <p>Showing <span class="font-medium text-ink">{{ $contacts->firstItem() }}–{{ $contacts->lastItem() }}</span> of <span class="font-medium text-ink">{{ number_format($contacts->total()) }}</span></p>
                {{ $contacts->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection