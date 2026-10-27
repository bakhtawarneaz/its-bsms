@extends('layouts.app')

@section('title', 'Groups')

@section('content')
    @php
        $editingId = old('_group_id');
        $formOpen = $errors->any()
            ? ['id' => $editingId ? (int) $editingId : null, 'groupname' => old('groupname'), 'description' => old('description')]
            : null;
        $iconButton = 'grid size-8 place-items-center rounded-lg text-muted transition hover:bg-surface hover:text-ink';
    @endphp

    <div x-data="{ form: @js($formOpen), deleting: null }">
        {{-- Page header --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-display text-2xl font-semibold tracking-tight text-ink">Groups</h2>
                <p class="mt-1 text-sm text-muted">{{ number_format($groups->total()) }} contact groups for campaign sending</p>
            </div>
            <button type="button" @click="form = { id: null, groupname: '', description: '' }"
                    class="inline-flex items-center gap-2 self-start rounded-lg bg-navy-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 sm:self-auto">
                <x-heroicon-m-plus class="size-4" />
                Add group
            </button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-line bg-white">
            {{-- Search --}}
            <form method="GET" action="{{ route('groups.index') }}" class="flex flex-col gap-3 border-b border-line p-4 sm:flex-row">
                <label class="relative flex-1">
                    <span class="sr-only">Search groups</span>
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted" />
                    <input type="search" name="search" value="{{ $search }}" placeholder="Search by group name" class="field-input pl-10">
                </label>
                @if ($search)
                    <a href="{{ route('groups.index') }}" class="inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink">
                        <x-heroicon-m-arrow-path class="size-4" /> Reset
                    </a>
                @endif
            </form>

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-surface/60 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                        <tr>
                            <th class="px-4 py-3">Group</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3 text-right">Contacts</th>
                            <th class="px-4 py-3">Created</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($groups as $group)
                            @php
                                $row = ['id' => (int) $group->id, 'groupname' => $group->groupname, 'description' => (string) $group->description, 'contacts' => (int) $group->total_contact];
                            @endphp
                            <tr class="transition hover:bg-surface/50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-navy-900 text-white">
                                            <x-heroicon-o-user-group class="size-5" />
                                        </span>
                                        <div class="min-w-0">
                                            <a href="{{ route('groups.show', $group->id) }}" class="block truncate font-semibold text-ink hover:underline">{{ $group->groupname }}</a>
                                            <p class="text-xs text-muted">#{{ $group->id }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="max-w-md px-4 py-3 text-muted">
                                    <p class="truncate" title="{{ $group->description }}">{{ $group->description ?: '—' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums font-medium text-ink">{{ number_format((int) $group->total_contact) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-muted">
                                    {{ $group->crtime ? \Illuminate\Support\Carbon::parse($group->crtime)->format('d M Y') : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('groups.show', $group->id) }}" class="{{ $iconButton }}" title="Contacts">
                                            <x-heroicon-o-users class="size-4.5" />
                                        </a>
                                        <button type="button" class="{{ $iconButton }}" title="Edit" @click="form = @js($row)">
                                            <x-heroicon-o-pencil-square class="size-4.5" />
                                        </button>
                                        <button type="button" class="{{ $iconButton }} hover:text-danger" title="Delete" @click="deleting = @js($row)">
                                            <x-heroicon-o-trash class="size-4.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-16 text-center">
                                    <x-heroicon-o-user-group class="mx-auto size-10 text-muted/50" />
                                    <p class="mt-3 font-medium text-ink">{{ $search ? 'No groups match' : 'No groups yet' }}</p>
                                    <p class="mt-1 text-sm text-muted">{{ $search ? 'Try a different search.' : 'Create a group to organise contacts.' }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($groups->total() > 0)
                <div class="flex flex-col gap-3 border-t border-line px-4 py-3 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
                    <p>Showing <span class="font-medium text-ink">{{ $groups->firstItem() }}–{{ $groups->lastItem() }}</span> of <span class="font-medium text-ink">{{ number_format($groups->total()) }}</span></p>
                    {{ $groups->onEachSide(1)->links() }}
                </div>
            @endif
        </div>

        {{-- Add / edit modal --}}
        <div x-show="form" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="form = null">
            <div x-show="form" x-transition.opacity.duration.200ms class="absolute inset-0 bg-navy-950/50 backdrop-blur-[2px]" @click="form = null"></div>

            <template x-if="form">
                <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-xl shadow-navy-950/20" x-data="{ busy: false }">
                    <div class="flex items-start gap-4 border-b border-line p-6">
                        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-navy-900 text-white">
                            <x-heroicon-o-user-group class="size-6" />
                        </span>
                        <div>
                            <h3 class="font-display text-lg font-semibold" x-text="form.id ? 'Edit group' : 'Add group'"></h3>
                            <p class="mt-0.5 text-sm text-muted">Hyphens, quotes, brackets and commas are removed from the name.</p>
                        </div>
                    </div>

                    <form method="POST" class="space-y-4 p-6" @submit="busy = true"
                          :action="form.id ? @js(url('/groups')) + '/' + form.id : @js(route('groups.store'))">
                        @csrf
                        <template x-if="form.id">
                            <div>
                                <input type="hidden" name="_method" value="PUT">
                                <input type="hidden" name="_group_id" :value="form.id">
                            </div>
                        </template>

                        <div>
                            <label class="field-label" for="group-name">Group name</label>
                            <input id="group-name" type="text" name="groupname" x-model="form.groupname" maxlength="100" required class="field-input">
                        </div>
                        <div>
                            <label class="field-label" for="group-description">Description <span class="font-normal text-muted">(optional)</span></label>
                            <textarea id="group-description" name="description" x-model="form.description" rows="3" maxlength="500" class="field-input resize-y"></textarea>
                        </div>

                        @if ($errors->any())
                            <p class="rounded-lg border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">{{ $errors->first() }}</p>
                        @endif

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink" @click="form = null">Cancel</button>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:opacity-60" :disabled="busy">
                                <span x-text="busy ? 'Saving…' : 'Save group'">Save group</span>
                            </button>
                        </div>
                    </form>
                </div>
            </template>
        </div>

        {{-- Delete modal --}}
        <div x-show="deleting" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="deleting = null">
            <div x-show="deleting" x-transition.opacity.duration.200ms class="absolute inset-0 bg-navy-950/50 backdrop-blur-[2px]" @click="deleting = null"></div>

            <template x-if="deleting">
                <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl shadow-navy-950/20" x-data="{ busy: false }">
                    <div class="flex items-start gap-4">
                        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-danger/10 text-danger">
                            <x-heroicon-o-trash class="size-6" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="font-display text-lg font-semibold">Delete group?</h3>
                            <p class="mt-1 text-sm text-muted">
                                "<span class="font-medium text-ink" x-text="deleting.groupname"></span>" and its
                                <span class="font-semibold text-danger" x-text="deleting.contacts.toLocaleString() + ' contact(s)'"></span>
                                will be deleted permanently.
                            </p>
                        </div>
                    </div>
                    <form method="POST" :action="@js(url('/groups')) + '/' + deleting.id" class="mt-6 flex justify-end gap-2" @submit="busy = true">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink" @click="deleting = null">Cancel</button>
                        <button type="submit" class="rounded-lg bg-danger px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60" :disabled="busy">Delete group</button>
                    </form>
                </div>
            </template>
        </div>
    </div>
@endsection