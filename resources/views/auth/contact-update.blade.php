@extends('layouts.guest')

@section('title', 'Confirm contact details')
@section('heading', 'Confirm your contact details')
@section('subheading', 'We use these to send your sign-in codes and account alerts.')

@section('content')
    <form
        method="POST"
        action="{{ route('contact.update') }}"
        class="space-y-5"
        x-data="{ loading: false }"
        @submit="loading = true"
        @pageshow.window="loading = false"
    >
        @csrf

        <div>
            <label for="fullname" class="field-label">Business name</label>
            <input
                id="fullname" name="fullname" type="text"
                value="{{ old('fullname', $user['fullname']) }}"
                class="field-input" required
                @error('fullname') aria-invalid="true" @enderror
            >
        </div>

        <div>
            <label for="mobiles" class="field-label">Mobile numbers</label>
            <input
                id="mobiles" name="mobiles" type="text"
                value="{{ old('mobiles', $user['user_mobile']) }}"
                class="field-input tabular-nums" placeholder="03001234567, 03111234567" required
                @error('mobiles') aria-invalid="true" @enderror
            >
            <p class="field-hint">Separate numbers with commas. Up to 5.</p>
        </div>

        <div>
            <label for="emails" class="field-label">Emails</label>
            <input
                id="emails" name="emails" type="text"
                value="{{ old('emails', $user['email']) }}"
                class="field-input" required
                @error('emails') aria-invalid="true" @enderror
            >
            <p class="field-hint">Separate emails with commas. Up to 5.</p>
        </div>

        @include('partials.submit-button', ['label' => 'Save and continue', 'loadingLabel' => 'Saving…'])
    </form>

    <a href="{{ route('login') }}" class="link-muted mt-8 inline-block">Back to sign in</a>
@endsection