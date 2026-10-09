@extends('layouts.guest')

@section('title', 'Update Contact Info')
@section('heading', 'Confirm Your Contact Info')

@section('content')
    <form method="POST" action="{{ route('contact.update') }}">
        @csrf

        <label for="fullname">Business Name</label>
        <input id="fullname" name="fullname" type="text" value="{{ old('fullname', $user['fullname']) }}" required>

        <label for="mobiles">Mobile Number(s) <small>(comma separated, max 5)</small></label>
        <input id="mobiles" name="mobiles" type="text" value="{{ old('mobiles', $user['user_mobile']) }}" placeholder="03001234567, 03111234567" required>

        <label for="emails">Email(s) <small>(comma separated, max 5)</small></label>
        <input id="emails" name="emails" type="text" value="{{ old('emails', $user['email']) }}" required>

        <button type="submit">Save &amp; Continue</button>
    </form>

    <a class="link" href="{{ route('login') }}">Back to Login</a>
@endsection