@extends('layouts.app')

@section('title', 'Change Password')

@section('content')
<div style="max-width:620px;margin:0 auto">
    <h1>Change Password</h1>
    <p>Use a strong password with at least 12 characters, uppercase and lowercase letters, a number and a symbol.</p>

    <form method="POST" action="{{ route('password.update') }}" class="card" style="display:grid;gap:16px">
        @csrf
        @method('PUT')

        <div>
            <label for="current_password"><strong>Current Password</strong></label>
            <input id="current_password" name="current_password" type="password" required autocomplete="current-password"
                   style="width:100%;padding:12px;margin-top:6px;border:1px solid #cbd5e1;border-radius:8px">
        </div>

        <div>
            <label for="password"><strong>New Password</strong></label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   style="width:100%;padding:12px;margin-top:6px;border:1px solid #cbd5e1;border-radius:8px">
        </div>

        <div>
            <label for="password_confirmation"><strong>Confirm New Password</strong></label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   style="width:100%;padding:12px;margin-top:6px;border:1px solid #cbd5e1;border-radius:8px">
        </div>

        <button type="submit" class="btn" style="border:0;cursor:pointer">Update Password</button>
    </form>
</div>
@endsection
