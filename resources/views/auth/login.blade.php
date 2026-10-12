@extends('layouts.guest')

@section('title', 'Sign in')
@section('heading', 'Sign in to your account')
@section('subheading', 'Use your username or email and password.')

@section('content')
    <form
        method="POST"
        action="{{ route('login.store') }}"
        class="space-y-5"
        x-data="{ loading: false, reveal: false }"
        @submit="loading = true"
        @pageshow.window="loading = false"
    >
        @csrf

        <div>
            <label for="login" class="field-label">Username or email</label>
            <input
                id="login" name="login" type="text" value="{{ old('login') }}"
                class="field-input" autocomplete="username" required autofocus
                @error('login') aria-invalid="true" @enderror
            >
        </div>

        <div>
            <label for="password" class="field-label">Password</label>
            <div class="relative">
                <input
                    id="password" name="password" type="password" :type="reveal ? 'text' : 'password'"
                    class="field-input pr-16" autocomplete="current-password" required
                >
                <button
                    type="button"
                    class="absolute inset-y-0 right-0 px-3.5 text-sm font-medium text-muted transition hover:text-ink"
                    @click="reveal = !reveal"
                    x-text="reveal ? 'Hide' : 'Show'"
                    :aria-label="reveal ? 'Hide password' : 'Show password'"
                >Show</button>
            </div>
        </div>

        <div class="pt-1">
            @include('partials.submit-button', ['label' => 'Sign in', 'loadingLabel' => 'Signing in…'])
        </div>
    </form>

    <p class="mt-8 text-sm text-muted">Trouble signing in? Contact your account manager.</p>
@endsection