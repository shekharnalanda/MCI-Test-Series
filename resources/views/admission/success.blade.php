@extends('layouts.app')

@section('title','Application Submitted')

@section('content')
<div class="card">
<h2>Application Submitted Successfully</h2>

<p>Your Application Number:</p>
<h3>{{ $application->application_no }}</h3>

<p>
Your application is awaiting administrative approval.
Login credentials will be created after approval.
</p>
</div>
@endsection
