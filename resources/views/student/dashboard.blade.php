@extends('layouts.app')

@section('title','Student Dashboard')

@section('content')

<h1>Student Dashboard</h1>

<div class="card">

<h2>Welcome, {{ $user->name }}</h2>

@if($student)

<div class="grid">

<div>
<strong>Student ID</strong>
<p>{{ $student->student_code }}</p>
</div>

<div>
<strong>Email</strong>
<p>{{ $user->email }}</p>
</div>

<div>
<strong>Mobile</strong>
<p>{{ $student->phone }}</p>
</div>

<div>
<strong>Status</strong>
<p>{{ ucfirst($student->status) }}</p>
</div>

</div>

@else

<p>Student profile is not available.</p>

@endif

</div>

<div class="grid">

<div class="card">
<h3>My Test Series</h3>
<p>Your assigned test packages will appear here.</p>
</div>

<div class="card">
<h3>My Results</h3>
<p>Scores, accuracy and performance analytics will appear here.</p>
</div>

<div class="card">
<h3>My ID Card</h3>
<p>Your authenticated MCI Test Series ID card will be available here.</p>
</div>

<div class="card">
<h3>Certificates</h3>
<p>Your performance certificates and score cards will be available here.</p>
</div>

</div>

<form method="POST" action="{{ route('logout') }}">
@csrf
<button>Logout</button>
</form>

@endsection
