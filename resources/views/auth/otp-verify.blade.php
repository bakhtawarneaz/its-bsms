@extends('layouts.guest')

@section('title', 'Enter code')
@section('heading', 'Enter your code')
@section('subheading', 'We sent a 5-digit code to the recipients you selected.')

@section('content')
    <form
        method="POST"
        action="{{ route('otp.verify.store') }}"
        class="space-y-5"
        x-data="{ loading: false }"
        @submit="loading = true"
        @pageshow.window="loading = false"
    >
        @csrf

        <div>
            <label for="code" class="field-label">One-time code</label>
            <input
                id="code" name="code" type="text"
                inputmode="numeric" pattern="[0-9]*" maxlength="5" autocomplete="one-time-code"
                class="field-input text-center font-display text-2xl tracking-[0.6em] tabular-nums"
                required autofocus
                @error('code') aria-invalid="true" @enderror
                @input="$el.value = $el.value.replace(/\D/g, '')"
            >
            <p class="field-hint" x-data="countdown({{ (int) $expiresAt }})">
                <span x-show="remaining > 0">Code expires in <span class="font-semibold text-ink tabular-nums" x-text="label"></span></span>
                <span x-show="remaining === 0" x-cloak class="font-medium text-danger">This code has expired. Request a new one.</span>
            </p>
        </div>

        @include('partials.submit-button', ['label' => 'Verify', 'loadingLabel' => 'Verifying…'])
    </form>

    <div class="mt-8 flex items-center justify-between gap-4">
        <form method="POST" action="{{ route('otp.resend') }}" x-data="countdown({{ (int) $resendAt }})">
            @csrf
            <button
                type="submit"
                class="link-muted disabled:cursor-not-allowed disabled:no-underline disabled:opacity-60"
                :disabled="remaining > 0"
            >
                <span x-show="remaining > 0">Resend code in <span class="tabular-nums" x-text="label"></span></span>
                <span x-show="remaining === 0" x-cloak>Resend code</span>
            </button>
        </form>

        <a href="{{ route('otp.recipients') }}" class="link-muted">Use a different number or email</a>
    </div>
@endsection