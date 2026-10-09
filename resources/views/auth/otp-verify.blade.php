@extends('layouts.guest')

@section('title', 'Verify OTP')
@section('heading', 'Enter OTP')

@section('content')
    <form method="POST" action="{{ route('otp.verify.store') }}">
        @csrf

        <label for="code">5-digit code</label>
        <input id="code" name="code" type="text" inputmode="numeric" maxlength="5" autocomplete="one-time-code" required autofocus>

        <button type="submit">Verify</button>
    </form>

    <a class="link" href="{{ route('otp.recipients') }}">Resend OTP</a>
@endsection