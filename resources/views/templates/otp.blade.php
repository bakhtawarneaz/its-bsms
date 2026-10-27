@extends('layouts.app')

@section('title', 'OTP templates')

@section('content')
    <div class="mb-4">
        <h2 class="font-display text-2xl font-semibold tracking-tight text-ink">Templates</h2>
        <p class="mt-1 text-sm text-muted">OTP messages that users send through the API by Temp ID</p>
    </div>

    @include('templates.partials.tabs')

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Add form --}}
        <form method="POST" action="{{ route('templates.otp.store') }}" class="space-y-4 self-start rounded-2xl border border-line bg-white p-6"
              x-data="{ busy: false }" @submit="busy = true">
            @csrf
            <h3 class="font-semibold">Add OTP template</h3>

            <div>
                <label class="field-label" for="otp-user">User account</label>
                <select id="otp-user" name="user_id" required class="field-input">
                    <option value="">Select user account</option>
                    @foreach ($users as $id => $username)
                        <option value="{{ $id }}" @selected((int) old('user_id', $userId) === $id)>{{ $username }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="field-label" for="otp-sender">Sender</label>
                <input id="otp-sender" type="text" name="sender" value="{{ old('sender') }}" maxlength="50" required placeholder="Active masking" class="field-input">
            </div>

            @include('partials.sms-message', ['name' => 'message', 'id' => 'otp-message', 'value' => ''])

            @if ($errors->any())
                <p class="rounded-lg border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">{{ $errors->first() }}</p>
            @endif

            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 disabled:opacity-60" :disabled="busy">
                <span x-text="busy ? 'Saving…' : 'Add OTP template'">Add OTP template</span>
            </button>
        </form>

        {{-- List --}}
        <div class="overflow-hidden rounded-2xl border border-line bg-white lg:col-span-2">
            <form method="GET" action="{{ route('templates.otp') }}" class="flex items-center gap-3 border-b border-line p-4">
                <select name="user_id" onchange="this.form.requestSubmit()" class="field-input">
                    <option value="">All users</option>
                    @foreach ($users as $id => $username)
                        <option value="{{ $id }}" @selected($userId === $id)>{{ $username }}</option>
                    @endforeach
                </select>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-surface/60 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                        <tr>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Temp ID</th>
                            <th class="px-4 py-3">Sender</th>
                            <th class="px-4 py-3">Message</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($templates as $template)
                            <tr class="align-top transition hover:bg-surface/50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <p class="font-medium text-ink">{{ $template->username ?: '—' }}</p>
                                    <p class="text-xs text-muted">#{{ $template->user_id }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums font-semibold text-ink">{{ $template->temp_id }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-ink">{{ $template->sender }}</td>
                                <td class="min-w-72 max-w-md px-4 py-3 text-ink">
                                    <p class="line-clamp-2 break-words" dir="auto" title="{{ $template->otp_template }}">{{ $template->otp_template }}</p>
                                    @if ((int) $template->is_unicode === 1)
                                        <span class="mt-1 inline-block rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">Unicode</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    @if ((int) $template->status === 1)
                                        <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">Active</span>
                                    @else
                                        <span class="rounded-md bg-surface px-2 py-1 text-xs font-medium text-muted">Off</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-16 text-center">
                                    <x-heroicon-o-key class="mx-auto size-10 text-muted/50" />
                                    <p class="mt-3 font-medium text-ink">No OTP templates</p>
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
    </div>
@endsection