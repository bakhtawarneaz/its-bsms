@extends('layouts.guest')

@section('title', 'OTP Recipients')
@section('heading', 'Select OTP Recipients')

@section('content')
    <form method="POST" action="{{ route('otp.send') }}">
        @csrf

        @if (count($mobiles))
            <label for="mobile">Via SMS</label>
            <select id="mobile" name="mobile">
                @foreach ($mobiles as $index => $mobile)
                    <option value="{{ $index }}">{{ $mobile }}</option>
                @endforeach
            </select>
        @endif

        @if (count($emails))
            <label for="email">Via Email</label>
            <select id="email" name="email">
                @foreach ($emails as $index => $email)
                    <option value="{{ $index }}">{{ $email }}</option>
                @endforeach
            </select>
        @endif

        <button type="submit">Send OTP</button>
    </form>

    <a class="link" href="{{ route('login') }}">Back to Login</a>
@endsection