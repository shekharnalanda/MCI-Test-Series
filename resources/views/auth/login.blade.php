@extends('layouts.app')

@section('title','Login - MCI Test Series')

@section('content')
<div class="card" style="max-width:500px;margin:auto">
<h2>Student / Admin Login</h2>

<form method="POST" action="{{ route('login.submit') }}">
@csrf

<label>Email</label>
<input type="email" name="email" value="{{ old('email') }}" required>

<label>Password</label>
<input type="password" name="password" required>

<label>
<input type="checkbox" name="remember" value="1" style="width:auto">
 Remember me
</label>

<br><br>
<button type="submit">Login</button>
</form>
</div>
@endsection
