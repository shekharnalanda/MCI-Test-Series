@extends('layouts.app')
@section('title','Reset Admin Password - MCI Test Series')
@section('content')
<div class="card" style="max-width:560px;margin:auto">
<h2>Reset Admin Password</h2>
<p>Verify the administrator email with OTP, then choose a new password.</p>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

@if(!session('otp_verified'))
<form method="POST" action="{{ route('admin.password.forgot.send') }}">@csrf
<label>Admin Email</label><input type="email" name="email" value="{{ old('email', session('admin_reset_email')) }}" required>
<br><br><button type="submit">Send Password Reset OTP</button>
</form>
@if(session('otp_sent'))
<hr><form method="POST" action="{{ route('admin.password.forgot.verify') }}">@csrf
<input type="hidden" name="email" value="{{ old('email', session('admin_reset_email')) }}">
<label>6-digit OTP</label><input type="text" inputmode="numeric" name="otp" maxlength="6" required>
<br><br><button type="submit">Verify OTP</button>
</form>
@endif
@else
<form method="POST" action="{{ route('admin.password.forgot.reset') }}">@csrf @method('PUT')
<label>New Password</label><input type="password" name="password" required>
<label>Confirm New Password</label><input type="password" name="password_confirmation" required>
<small>Minimum 12 characters with upper/lower-case letters and numbers.</small>
<br><br><button type="submit">Reset Admin Password</button>
</form>
@endif
</div>
@endsection
