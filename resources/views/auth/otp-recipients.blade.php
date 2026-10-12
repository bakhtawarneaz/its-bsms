@extends('layouts.guest')

@section('title', 'Verify sign-in')
@section('heading', 'Verify it’s you')
@section('subheading', 'Choose where to send your one-time code.')

@section('content')
    <form
        method="POST"
        action="{{ route('otp.send') }}"
        class="space-y-6"
        x-data="{ loading: false }"
        @submit="loading = true"
        @pageshow.window="loading = false"
    >
        @csrf

        @if (count($mobiles))
            <fieldset>
                <legend class="field-label">Send by SMS</legend>
                <div class="space-y-2">
                    @foreach ($mobiles as $index => $mobile)
                        <label class="choice-card">
                            <input type="radio" name="mobile" value="{{ $index }}" class="choice-radio" @checked($loop->first)>
                            <span class="tabular-nums">{{ $mobile }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif

        @if (count($emails))
            <fieldset>
                <legend class="field-label">Send by email</legend>
                <div class="space-y-2">
                    @foreach ($emails as $index => $email)
                        <label class="choice-card">
                            <input type="radio" name="email" value="{{ $index }}" class="choice-radio" @checked($loop->first)>
                            <span>{{ $email }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif

        @include('partials.submit-button', ['label' => 'Send code', 'loadingLabel' => 'Sending…'])
    </form>

    <a href="{{ route('login') }}" class="link-muted mt-8 inline-block">Back to sign in</a>
@endsection