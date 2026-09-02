@extends('layouts.app')

@section('title','My Tests')

@section('content')

<h1>Available Tests</h1>

<div class="grid">

@forelse($tests as $test)

<div class="card">

<span class="badge">
{{ strtoupper(str_replace('_',' ',$test->test_type)) }}
</span>

<h3>{{ $test->title }}</h3>

@if($test->exam)
<p>{{ $test->exam->name }}</p>
@endif

<p>
<strong>Questions:</strong> {{ $test->total_questions }}
<br>
<strong>Duration:</strong> {{ $test->duration_minutes }} Minutes
<br>
<strong>Positive Marks:</strong> {{ $test->positive_marks }}
<br>
<strong>Negative Marks:</strong> {{ $test->negative_marks }}
</p>

<form
    method="POST"
    action="{{ route('student.tests.start',$test) }}"
>
@csrf
<button>Start Test</button>
</form>

</div>

@empty

<div class="card">
No tests are currently available.
</div>

@endforelse

</div>

{{ $tests->links() }}

@endsection
