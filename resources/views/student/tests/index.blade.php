@extends('layouts.app')
@section('title','My Tests - MCI Test Series')
@section('content')
<style>
.test-toolbar{background:#fff;border:1px solid #dce6f2;border-radius:14px;padding:18px;margin:0 0 24px;box-shadow:0 8px 24px rgba(19,51,91,.07)}
.test-toolbar form{display:grid;grid-template-columns:2fr repeat(3,1fr) auto auto;gap:12px;align-items:end}
.test-toolbar label{display:block;font-size:.82rem;font-weight:700;color:#334155;margin-bottom:6px}
.test-toolbar input,.test-toolbar select{width:100%;min-height:43px;margin:0;border:1px solid #cbd5e1;border-radius:8px;padding:9px 11px;background:#fff}
.test-toolbar .actions{display:flex;gap:8px}.test-toolbar button,.test-toolbar .clear-filter{min-height:43px;display:inline-flex;align-items:center;justify-content:center;white-space:nowrap}
.clear-filter{padding:9px 14px;border:1px solid #cbd5e1;border-radius:8px;text-decoration:none;color:#334155;background:#f8fafc}
.result-line{display:flex;justify-content:space-between;gap:12px;align-items:center;margin:0 0 18px;color:#475569}.demo-note{color:#1261a0;font-weight:700}
@media(max-width:1000px){.test-toolbar form{grid-template-columns:repeat(2,1fr)}}
@media(max-width:600px){.test-toolbar form{grid-template-columns:1fr}.result-line{align-items:flex-start;flex-direction:column}}
</style>

<h1>{{ $demoAccess ? 'Free Demo Tests' : 'Available Tests' }}</h1>
<div class="test-toolbar">
<form method="GET" action="{{ route('student.tests.index') }}">
<div><label for="q">Search Test</label><input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Test or exam name"></div>
<div><label for="category">Category</label><select id="category" name="category"><option value="">All Categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)($filters['category'] ?? '') === (string)$category->id)>{{ $category->name }}</option>@endforeach</select></div>
<div><label for="exam">Exam</label><select id="exam" name="exam"><option value="">All Exams</option>@foreach($exams as $exam)<option value="{{ $exam->id }}" @selected((string)($filters['exam'] ?? '') === (string)$exam->id)>{{ $exam->name }}</option>@endforeach</select></div>
<div><label for="type">Test Type</label><select id="type" name="type"><option value="">All Types</option>@foreach($testTypes as $type)<option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ ucwords(str_replace('_',' ',$type)) }}</option>@endforeach</select></div>
<button type="submit">Apply Filters</button><a class="clear-filter" href="{{ route('student.tests.index') }}">Clear</a>
</form>
</div>

<div class="result-line"><span><strong>{{ number_format($tests->total()) }}</strong> test{{ $tests->total() === 1 ? '' : 's' }} found</span>@if($demoAccess)<span class="demo-note">Only free demo tests are shown</span>@endif</div>
<div class="grid">
@forelse($tests as $test)
<div class="card">
<span class="badge">{{ strtoupper(str_replace('_',' ',$test->test_type)) }}</span>
<h3>{{ $test->title }}</h3>
@if($test->exam)<p>{{ $test->exam->name }}@if($test->exam->category) · {{ $test->exam->category->name }}@endif</p>@endif
<p><strong>Questions:</strong> {{ $test->total_questions }}<br><strong>Duration:</strong> {{ $test->duration_minutes }} Minutes<br><strong>Positive Marks:</strong> {{ $test->positive_marks }}<br><strong>Negative Marks:</strong> {{ $test->negative_marks }}</p>
<form method="POST" action="{{ route('student.tests.start',$test) }}">@csrf<button>Start Test</button></form>
</div>
@empty
<div class="card"><h3>No matching tests found</h3><p>Change or clear the selected filters.</p></div>
@endforelse
</div>
{{ $tests->links() }}
@endsection
