@extends('layouts.app')
@section('title', 'Change Admin Password')
@section('content')
<section class="panel" style="max-width:680px;margin:36px auto">
    <h1>Change Admin Password</h1>
    <p class="muted">For security, enter your current password before setting a new one.</p>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('admin.password.update') }}" class="form-grid">@csrf @method('PUT')
        <label>Current Password<input type="password" name="current_password" required autocomplete="current-password"></label>
        <label>New Password<input type="password" name="password" required autocomplete="new-password"></label>
        <label>Confirm New Password<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
        <button class="btn" type="submit">Change Password</button>
    </form>
</section>
@endsection
