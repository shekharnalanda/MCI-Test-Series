@extends('layouts.app')

@section('title','Test Result')

@section('content')

<div class="card">

<h1>Test Result</h1>

<h2>{{ $attempt->test->title }}</h2>

@if($attempt->test->exam)
<p>{{ $attempt->test->exam->name }}</p>
@endif

</div>

<div class="grid">

<div class="card">
<h2>{{ $attempt->obtained_marks }}</h2>
<p>Marks Obtained</p>
</div>

<div class="card">
<h2>{{ $attempt->maximum_marks }}</h2>
<p>Maximum Marks</p>
</div>

<div class="card">
<h2>{{ $attempt->percentage }}%</h2>
<p>Percentage</p>
</div>

<div class="card">
<h2>{{ $attempt->correct_answers }}</h2>
<p>Correct Answers</p>
</div>

<div class="card">
<h2>{{ $attempt->wrong_answers }}</h2>
<p>Wrong Answers</p>
</div>

<div class="card">
<h2>{{ $attempt->unanswered }}</h2>
<p>Unanswered</p>
</div>

<div class="card">
<h2>{{ data_get($attempt->analytics,'accuracy',0) }}%</h2>
<p>Accuracy</p>
</div>

<div class="card">
<h2>{{ data_get($attempt->analytics,'attempt_rate',0) }}%</h2>
<p>Attempt Rate</p>
</div>

</div>

<div class="card">

<h3>Performance Summary</h3>

<p>
Attempt Number:
<strong>{{ $attempt->attempt_number }}</strong>
</p>

<p>
Time Taken:
<strong>
{{ gmdate('H:i:s',$attempt->time_taken_seconds) }}
</strong>
</p>

@if($attempt->rank)
<p>Rank: <strong>{{ $attempt->rank }}</strong></p>
@endif

@if($attempt->percentile)
<p>Percentile: <strong>{{ $attempt->percentile }}</strong></p>
@endif

<a class="btn" href="{{ route('student.tests.index') }}">
Back to Tests
</a>

</div>

@endsection
