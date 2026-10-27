@extends('layouts.app')

@section('title', 'Contacts')

@section('content')
    @php
        $fields = \App\Services\ContactService::FIELDS;
        $iconButton = 'grid size-8 place-items-center rounded-lg text-muted transition hover:bg-surface hover:text-ink';
        $editingId = old('_contact_id');
        $formErrors = $errors->hasAny(['fullname', 'mobile', 'email', 'option1', 'option2', 'option3', 'group_id']);
        $formOpen = $formErrors ? [
            'id' => $editingId ? (int) $editingId : null, 'fullname' => old('fullname'), 'mobile' => old('mobile'),
            'email' => old('email'), 'option1' => old('option1'), 'option2' => old('option2'), 'option3' => old('option3'),
            'group_id' => old('group_id', ''), 'more' => (bool) (old('option1') || old('option2') || old('option3')),
        ] : null;
        $empty = ['id' => null, 'fullname' => '', 'mobile' => '', 'email' => '', 'option1' => '', 'option2' => '', 'option3' => '', 'group_id' => '', 'more' => false];
        $hasRows = count($rows) > 0;
    @endphp

    <div x-data="{ form: @js($formOpen), deleting: null, assigning: null, exporting: false, importing: @js($errors->hasAny(['file'])) }">
        {{-- Page header --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-display text-2xl font-semibold tracking-tight text-ink">Contacts</h2>
                <p class="mt-1 text-sm text-muted">Contacts of every user, newest first</p>
            </div>
            <div class="flex gap-2 self-start sm:self-auto">
                <button type="button" @click="importing = true"
                        class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-4 py-2.5 text-sm font-semibold text-ink transition hover:border-navy-700/40">
                    <x-heroicon-o-arrow-up-tray class="size-4" /> Import
                </button>
                <button type="button" @click="exporting = true"
                        class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-4 py-2.5 text-sm font-semibold text-ink transition hover:border-navy-700/40">
                    <x-heroicon-o-arrow-down-tray class="size-4" /> Export
                </button>
                <button type="button" @click="form = @js($empty)"
                        class="inline-flex items-center gap-2 rounded-lg bg-navy-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">
                    <x-heroicon-m-plus class="size-4" /> Add contact
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-line bg-white">
            {{-- Search --}}
            <form method="GET" action="{{ route('contacts.index') }}" class="flex flex-col gap-3 border-b border-line p-4 lg:flex-row lg:items-center">
                <select name="field" class="field-input lg:w-48">
                    @foreach ($fields as $key => $label)
                        <option value="{{ $key }}" @selected($field === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="relative flex-1">
                    <span class="sr-only">Search value</span>
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted" />
                    <input type="search" name="value" value="{{ $value }}" placeholder="Search value" class="field-input pl-10">
                </label>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">Search</button>
                @if ($value)
                    <a href="{{ route('contacts.index') }}" class="inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink">
                        <x-heroicon-m-arrow-path class="size-4" /> Reset
                    </a>
                @endif
            </form>

            @if ($errors->hasAny(['value', 'groups']))
                <p class="border-b border-line bg-danger/5 px-4 py-2.5 text-sm text-danger">{{ $errors->first('value') ?: $errors->first('groups') }}</p>
            @endif

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-surface/60 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                        <tr>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Mobile</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Groups</th>
                            <th class="px-4 py-3">Extra fields</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($rows as $contact)
                            @php
                                $mine = (int) $contact->user_id === $userId;
                                $extras = array_filter([$contact->option1, $contact->option2, $contact->option3], fn ($o) => trim((string) $o) !== '');
                                $row = [
                                    'id' => (int) $contact->id, 'number' => $contact->numbers, 'fullname' => (string) $contact->fullname,
                                    'mobile' => (string) $contact->numbers, 'email' => (string) $contact->emails,
                                    'option1' => (string) $contact->option1, 'option2' => (string) $contact->option2, 'option3' => (string) $contact->option3,
                                    'more' => (bool) $extras, 'groupIds' => array_column($contact->groups, 'id'),
                                ];
                            @endphp
                            <tr class="align-top transition hover:bg-surface/50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <p class="font-semibold text-ink">{{ $contact->fullname ?: '—' }}</p>
                                    <p class="text-xs text-muted">#{{ $contact->id }} · {{ $mine ? 'Your contact' : 'User #' . $contact->user_id }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-ink">{{ $contact->numbers }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-muted">{{ $contact->emails ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($contact->groups)
                                        <div class="flex max-w-xs flex-wrap gap-1">
                                            @foreach ($contact->groups as $group)
                                                <span class="rounded-full bg-navy-900/5 px-2 py-0.5 text-xs font-medium text-navy-900 ring-1 ring-inset ring-navy-900/10">{{ $group['name'] }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="max-w-xs px-4 py-3 text-muted">
                                    <p class="truncate" title="{{ implode(' · ', $extras) }}">{{ $extras ? implode(' · ', $extras) : '—' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($mine && $groups)
                                            <button type="button" class="{{ $iconButton }}" title="Groups" @click="assigning = @js($row)">
                                                <x-heroicon-o-user-group class="size-4.5" />
                                            </button>
                                        @else
                                            <span class="size-8 shrink-0" aria-hidden="true"></span>
                                        @endif
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
                                <td colspan="6" class="px-4 py-16 text-center">
                                    <x-heroicon-o-book-open class="mx-auto size-10 text-muted/50" />
                                    <p class="mt-3 font-medium text-ink">{{ $value ? 'No contacts match' : 'No contacts yet' }}</p>
                                    <p class="mt-1 text-sm text-muted">{{ $value ? 'Try a different search.' : 'Add a contact to get started.' }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pager (no total: the table is too large to count) --}}
            @if ($hasRows || $page > 1)
                <div class="flex items-center justify-between gap-3 border-t border-line px-4 py-3 text-sm text-muted">
                    <p>Page <span class="font-medium tabular-nums text-ink">{{ number_format($page) }}</span></p>
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
            @endif
        </div>

        {{-- Add / edit modal --}}
        <div x-show="form" x-cloak class="fixed inset-0 z-50 grid place-items-center overflow-y-auto p-4" role="dialog" aria-modal="true" @keydown.escape.window="form = null">
            <div x-show="form" x-transition.opacity.duration.200ms class="fixed inset-0 bg-navy-950/50 backdrop-blur-[2px]" @click="form = null"></div>

            <template x-if="form">
                <div class="relative w-full max-w-xl rounded-2xl bg-white shadow-xl shadow-navy-950/20" x-data="{ busy: false }">
                    <div class="flex items-start gap-4 border-b border-line p-6">
                        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-navy-900 text-white">
                            <x-heroicon-o-user-plus class="size-6" />
                        </span>
                        <div>
                            <h3 class="font-display text-lg font-semibold" x-text="form.id ? 'Edit contact' : 'Add contact'"></h3>
                            <p class="mt-0.5 text-sm text-muted" x-text="form.id ? 'Empty optional fields keep their current value.' : 'Saved to your own contacts.'"></p>
                        </div>
                    </div>

                    <form method="POST" class="space-y-4 p-6" @submit="busy = true"
                          :action="form.id ? @js(url('/contacts')) + '/' + form.id : @js(route('contacts.store'))">
                        @csrf
                        <template x-if="form.id">
                            <div>
                                <input type="hidden" name="_method" value="PUT">
                                <input type="hidden" name="_contact_id" :value="form.id">
                            </div>
                        </template>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="field-label" for="c-name">Name</label>
                                <input id="c-name" type="text" name="fullname" x-model="form.fullname" maxlength="150" required class="field-input">
                            </div>
                            <div>
                                <label class="field-label" for="c-mobile">Mobile</label>
                                <input id="c-mobile" type="text" inputmode="tel" name="mobile" x-model="form.mobile" placeholder="0300 1234567" :required="! form.id" class="field-input tabular-nums">
                            </div>
                            <div>
                                <label class="field-label" for="c-email">Email <span class="font-normal text-muted">(optional)</span></label>
                                <input id="c-email" type="email" name="email" x-model="form.email" maxlength="150" class="field-input">
                            </div>
                            <template x-if="! form.id">
                                <div>
                                    <label class="field-label" for="c-group">Group <span class="font-normal text-muted">(optional)</span></label>
                                    <select id="c-group" name="group_id" x-model="form.group_id" class="field-input">
                                        <option value="">No group</option>
                                        @foreach ($groups as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </template>
                        </div>

                        {{-- Extra fields: free text kept with the contact (shown in the list and export) --}}
                        <div>
                            <button type="button" class="inline-flex items-center gap-1 text-sm font-medium text-navy-900 hover:underline" @click="form.more = ! form.more">
                                <x-heroicon-m-chevron-down class="size-4 transition" ::class="form.more && 'rotate-180'" />
                                More fields
                            </button>
                            <div x-show="form.more" x-cloak class="mt-3 grid gap-4 sm:grid-cols-3">
                                @foreach ([1, 2, 3] as $n)
                                    <div>
                                        <label class="field-label" for="c-option{{ $n }}">Extra field {{ $n }}</label>
                                        <input id="c-option{{ $n }}" type="text" name="option{{ $n }}" x-model="form.option{{ $n }}" maxlength="250" placeholder="e.g. City" class="field-input">
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @if ($formErrors)
                            <p class="rounded-lg border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">{{ $errors->first() }}</p>
                        @endif

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink" @click="form = null">Cancel</button>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:opacity-60" :disabled="busy">
                                <span x-text="busy ? 'Saving…' : 'Save contact'">Save contact</span>
                            </button>
                        </div>
                    </form>
                </div>
            </template>
        </div>

        {{-- Groups modal: ticked = in group, unticked = removed --}}
        <div x-show="assigning" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="assigning = null">
            <div x-show="assigning" x-transition.opacity.duration.200ms class="absolute inset-0 bg-navy-950/50 backdrop-blur-[2px]" @click="assigning = null"></div>

            <template x-if="assigning">
                <div class="relative w-full max-w-md rounded-2xl bg-white shadow-xl shadow-navy-950/20" x-data="{ busy: false }">
                    <div class="border-b border-line p-6">
                        <h3 class="font-display text-lg font-semibold">Groups</h3>
                        <p class="mt-0.5 text-sm text-muted" x-text="(assigning.fullname || 'Contact') + ' · ' + assigning.number"></p>
                    </div>
                    <form method="POST" :action="@js(url('/contacts')) + '/' + assigning.id + '/groups'" class="p-6" @submit="busy = true">
                        @csrf
                        <div class="max-h-72 space-y-1 overflow-y-auto">
                            @foreach ($groups as $id => $name)
                                <label class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-sm transition hover:bg-surface">
                                    <input type="checkbox" name="groups[]" value="{{ $id }}" class="size-4 accent-navy-900" :checked="assigning.groupIds.includes({{ $id }})">
                                    <span class="text-ink">{{ $name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-3 text-xs text-muted">Untick a group to remove the contact from it.</p>
                        <div class="mt-6 flex justify-end gap-2">
                            <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink" @click="assigning = null">Cancel</button>
                            <button type="submit" class="rounded-lg bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:opacity-60" :disabled="busy">Save groups</button>
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
                            <h3 class="font-display text-lg font-semibold">Delete contact?</h3>
                            <p class="mt-1 text-sm text-muted"><span class="font-medium text-ink" x-text="(deleting.fullname || 'Contact') + ' · ' + deleting.number"></span> will be removed from every group.</p>
                        </div>
                    </div>
                    <form method="POST" :action="@js(url('/contacts')) + '/' + deleting.id" class="mt-6 flex justify-end gap-2" @submit="busy = true">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink" @click="deleting = null">Cancel</button>
                        <button type="submit" class="rounded-lg bg-danger px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60" :disabled="busy">Delete</button>
                    </form>
                </div>
            </template>
        </div>

        {{-- Import modal --}}
        <div x-show="importing" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="importing = false">
            <div x-show="importing" x-transition.opacity.duration.200ms class="absolute inset-0 bg-navy-950/50 backdrop-blur-[2px]" @click="importing = false"></div>

            <div x-show="importing" x-transition class="relative w-full max-w-lg rounded-2xl bg-white shadow-xl shadow-navy-950/20" x-data="{ busy: false, fileName: '' }">
                <div class="flex items-start gap-4 border-b border-line p-6">
                    <span class="grid size-11 shrink-0 place-items-center rounded-full bg-navy-900 text-white">
                        <x-heroicon-o-arrow-up-tray class="size-6" />
                    </span>
                    <div>
                        <h3 class="font-display text-lg font-semibold">Import contacts</h3>
                        <p class="mt-0.5 text-sm text-muted">CSV or XLSX, saved to your own contacts.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('contacts.import') }}" enctype="multipart/form-data" class="space-y-4 p-6" @submit="busy = true">
                    @csrf

                    <div class="rounded-xl border border-line bg-surface/50 p-4 text-xs text-muted">
                        <p class="font-semibold text-ink">File columns</p>
                        <div class="mt-2 grid grid-cols-3 gap-x-4 gap-y-1 sm:grid-cols-6">
                            @foreach (['A' => 'Mobile', 'B' => 'Name', 'C' => 'Email', 'D' => 'Extra 1', 'E' => 'Extra 2', 'F' => 'Extra 3'] as $col => $label)
                                <span><span class="font-semibold text-ink">{{ $col }}</span> {{ $label }}</span>
                            @endforeach
                        </div>
                        <p class="mt-2">Rows without a valid mobile number (like a header row) are skipped.</p>
                    </div>

                    <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-line px-4 py-8 text-center transition hover:border-navy-700/40">
                        <x-heroicon-o-document-arrow-up class="size-8 text-muted" />
                        <span class="text-sm font-medium text-ink" x-text="fileName || 'Choose a CSV or XLSX file'"></span>
                        <span class="text-xs text-muted">Up to 20 MB</span>
                        <input type="file" name="file" accept=".csv,.xlsx" required class="sr-only"
                               @change="fileName = $event.target.files[0]?.name || ''">
                    </label>

                    <div>
                        <label class="field-label" for="import-group">Add to group <span class="font-normal text-muted">(optional)</span></label>
                        <select id="import-group" name="group_id" class="field-input">
                            <option value="">No group</option>
                            @foreach ($groups as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($errors->hasAny(['file']))
                        <p class="rounded-lg border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">{{ $errors->first('file') }}</p>
                    @endif

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink" @click="importing = false">Cancel</button>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:opacity-60" :disabled="busy">
                            <svg x-show="busy" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                            <span x-text="busy ? 'Importing…' : 'Import'">Import</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        {{-- Export modal --}}
        <div x-show="exporting" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="exporting = false">
            <div x-show="exporting" x-transition.opacity.duration.200ms class="absolute inset-0 bg-navy-950/50 backdrop-blur-[2px]" @click="exporting = false"></div>

            <div x-show="exporting" x-transition class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl shadow-navy-950/20">
                <h3 class="font-display text-lg font-semibold">Export your contacts</h3>
                <p class="mt-0.5 text-sm text-muted">One row per contact with its groups, newest first, up to {{ number_format(\App\Services\ContactService::EXPORT_LIMIT) }} rows.</p>
                <form method="GET" action="{{ route('contacts.export') }}" class="mt-5 space-y-4" @submit="setTimeout(() => exporting = false, 300)">
                    @if ($value)
                        <input type="hidden" name="field" value="{{ $field }}">
                        <input type="hidden" name="value" value="{{ $value }}">
                    @endif
                    <div>
                        <label class="field-label" for="export-scope">Contacts</label>
                        <select id="export-scope" name="scope" class="field-input">
                            <option value="all">All contacts{{ $value ? ' — current search (' . $fields[$field] . ': ' . $value . ')' : ' (all users)' }}</option>
                            <option value="mine">My contacts</option>
                            @if ($groups)
                                <optgroup label="My groups">
                                    @foreach ($groups as $groupId => $groupName)
                                        <option value="group:{{ $groupId }}">{{ $groupName }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </select>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink" @click="exporting = false">Cancel</button>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">
                            <x-heroicon-o-arrow-down-tray class="size-4" /> Download CSV
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection