@extends('layouts.app')

@section('title','Admin Dashboard')

@section('content')
<h1>Admin Dashboard</h1>

<div class="grid">
<div class="card"><h2>{{ $students }}</h2><p>Students</p></div>
<div class="card"><h2>{{ $pendingAdmissions }}</h2><p>Pending Admissions</p></div>
<div class="card"><h2>{{ $exams }}</h2><p>Exams</p></div>
<div class="card"><h2>{{ $questions }}</h2><p>Questions</p></div>
<div class="card"><h2>{{ $tests }}</h2><p>Tests</p></div>
<div class="card"><h2>{{ $attempts }}</h2><p>Test Attempts</p></div>
</div>

<a class="btn" href="{{ route('admin.admissions.index') }}">
Manage Admissions
</a>

<a class="btn" href="{{ route('admin.current-affairs.index') }}">
Current Affairs Review
</a>
@endsection

<a class="btn" href="{{ route('admin.operations.index') }}">
    Manage Students, Packages & Exams
</a>

<a class="btn" href="{{ route('admin.content.index') }}">
    Manage Content & Generate Tests
</a>
