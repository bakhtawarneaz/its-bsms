@extends('layouts.guest')

@section('title', 'Login')

@section('content')
    <form method="POST" action="{{ route('login.store') }}">
        @csrf

        <label for="login">Username or Email</label>
        <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>

        <button type="submit">Sign In</button>
    </form>
@endsection