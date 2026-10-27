@extends('layouts.app')

@section('title', 'Templates')

@section('content')
    @php
        // Reopen the right modal after a validation error.
        $editingId = old('_template_id');
        $formOpen = $errors->any() ? ($editingId ? ['id' => (int) $editingId, 'name' => old('_template_name'), 'message' => old('message')] : ['id' => null, 'name' => old('name'), 'message' => old('message')]) : null;
        $iconButton = 'grid size-8 place-items-center rounded-lg text-muted transition hover:bg-surface hover:text-ink';
    @endphp

    <div x-data="{ form: @js($formOpen), deleting: null }">
        {{-- Page header --}}
        <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-display text-2xl font-semibold tracking-tight text-ink">Templates</h2>
                <p class="mt-1 text-sm text-muted">{{ number_format($templates->total()) }} saved messages for quick sending</p>
            </div>
            <button type="button" @click="form = { id: null, name: '', message: '' }"
                    class="inline-flex items-center gap-2 self-start rounded-lg bg-navy-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 sm:self-auto">
                <x-heroicon-m-plus class="size-4" />
                Add template
            </button>
        </div>

        @include('templates.partials.tabs')

        <div class="overflow-hidden rounded-2xl border border-line bg-white">
            {{-- Search --}}
            <form method="GET" action="{{ route('templates.index') }}" class="flex flex-col gap-3 border-b border-line p-4 sm:flex-row">
                <label class="relative flex-1">
                    <span class="sr-only">Search templates</span>
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted" />
                    <input type="search" name="search" value="{{ $search }}" placeholder="Search by name or message" class="field-input pl-10">
                </label>
                @if ($search)
                    <a href="{{ route('templates.index') }}" class="inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink">
                        <x-heroicon-m-arrow-path class="size-4" /> Reset
                    </a>
                @endif
            </form>

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-surface/60 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                        <tr>
                            <th class="px-4 py-3">Template</th>
                            <th class="px-4 py-3">Message</th>
                            <th class="px-4 py-3">SMS</th>
                            <th class="px-4 py-3">Created</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($templates as $template)
                            @php
                                $row = ['id' => (int) $template->id, 'name' => $template->template_name, 'message' => (string) $template->msgdata];
                                $parts = \App\Support\SmsText::parts((string) $template->msgdata);
                                $unicode = \App\Support\SmsText::isUnicode((string) $template->msgdata);
                            @endphp
                            <tr class="align-top transition hover:bg-surface/50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <p class="font-semibold text-ink">{{ $template->template_name }}</p>
                                    <p class="text-xs text-muted">#{{ $template->id }}</p>
                                </td>
                                <td class="min-w-80 max-w-xl px-4 py-3 text-ink">
                                    <p class="line-clamp-2 break-words" dir="auto" title="{{ $template->msgdata }}">{{ $template->msgdata }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-md bg-surface px-2 py-1 text-xs font-medium tabular-nums text-ink">{{ $parts }}</span>
                                    @if ($unicode)
                                        <span class="ml-1 rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">Unicode</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-muted">
                                    {{ $template->crtime ? \Illuminate\Support\Carbon::parse($template->crtime)->format('d M Y') : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
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
                                    <x-heroicon-o-document-text class="mx-auto size-10 text-muted/50" />
                                    <p class="mt-3 font-medium text-ink">{{ $search ? 'No templates match' : 'No templates yet' }}</p>
                                    <p class="mt-1 text-sm text-muted">{{ $search ? 'Try a different search.' : 'Save a message you send often.' }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($templates->total() > 0)
                <div class="flex flex-col gap-3 border-t border-line px-4 py-3 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
                    <p>Showing <span class="font-medium text-ink">{{ $templates->firstItem() }}–{{ $templates->lastItem() }}</span> of <span class="font-medium text-ink">{{ number_format($templates->total()) }}</span></p>
                    {{ $templates->onEachSide(1)->links() }}
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
                            <x-heroicon-o-document-text class="size-6" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="font-display text-lg font-semibold" x-text="form.id ? 'Edit template' : 'Add template'"></h3>
                            <p class="mt-0.5 truncate text-sm text-muted" x-text="form.id ? form.name : 'Name it once; only the message can change later.'"></p>
                        </div>
                    </div>

                    <form method="POST" class="space-y-4 p-6" @submit="busy = true"
                          :action="form.id ? @js(url('/templates')) + '/' + form.id : @js(route('templates.store'))">
                        @csrf
                        <template x-if="form.id">
                            <div>
                                <input type="hidden" name="_method" value="PUT">
                                <input type="hidden" name="_template_id" :value="form.id">
                                <input type="hidden" name="_template_name" :value="form.name">
                            </div>
                        </template>

                        <template x-if="! form.id">
                            <div>
                                <label class="field-label" for="template-name">Template name</label>
                                <input id="template-name" type="text" name="name" x-model="form.name" maxlength="100" required class="field-input">
                            </div>
                        </template>

                        <div x-data x-init="$nextTick(() => { const box = $el.querySelector('textarea'); box.value = form.message; box.dispatchEvent(new Event('input')) })">
                            @include('partials.sms-message', ['name' => 'message', 'id' => 'template-message', 'value' => ''])
                        </div>

                        @if ($errors->any())
                            <p class="rounded-lg border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">{{ $errors->first() }}</p>
                        @endif

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink" @click="form = null">Cancel</button>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:opacity-60" :disabled="busy">
                                <svg x-show="busy" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                                <span x-text="busy ? 'Saving…' : 'Save template'">Save template</span>
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
                            <h3 class="font-display text-lg font-semibold">Delete template?</h3>
                            <p class="mt-1 text-sm text-muted">"<span class="font-medium text-ink" x-text="deleting.name"></span>" will be removed. This can't be undone.</p>
                        </div>
                    </div>
                    <form method="POST" :action="@js(url('/templates')) + '/' + deleting.id" class="mt-6 flex justify-end gap-2" @submit="busy = true">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition hover:bg-surface hover:text-ink" @click="deleting = null">Cancel</button>
                        <button type="submit" class="rounded-lg bg-danger px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60" :disabled="busy">Delete</button>
                    </form>
                </div>
            </template>
        </div>
    </div>
@endsection