@extends('layouts.app')
@section('title', 'Content & Test Generator')
@section('content')
<h1>Content & Test Generator</h1>
<p>Subjects: {{ $counts['subjects'] }} | Topics: {{ $counts['topics'] }} | Questions: {{ $counts['questions'] }} | Published: {{ $counts['published'] }} | Tests: {{ $counts['tests'] }}</p>

<div class="grid">
<section class="card"><h2>Add Subject</h2><form method="POST" action="{{ route('admin.content.subjects.store') }}">@csrf
<p><input name="name" placeholder="English name" required style="width:100%;padding:10px"></p>
<p><input name="name_hi" placeholder="Hindi name" required style="width:100%;padding:10px"></p>
<button class="btn" style="border:0">Add Subject</button></form></section>

<section class="card"><h2>Add Topic</h2><form method="POST" action="{{ route('admin.content.topics.store') }}">@csrf
<p><select name="subject_id" required style="width:100%;padding:10px"><option value="">Subject</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></p>
<p><input name="name" placeholder="English topic" required style="width:100%;padding:10px"></p>
<p><input name="name_hi" placeholder="Hindi topic" required style="width:100%;padding:10px"></p>
<button class="btn" style="border:0">Add Topic</button></form></section>

<section class="card"><h2>Automatic Test Generator</h2><form method="POST" action="{{ route('admin.content.generate') }}">@csrf
<p><input name="per_exam" type="number" min="1" max="5" value="1" required style="width:100%;padding:10px" title="Tests per exam"></p>
<p><input name="questions" type="number" min="5" max="100" value="25" required style="width:100%;padding:10px" title="Questions per test"></p>
<p><select name="difficulty" style="width:100%;padding:10px"><option value="mixed">Mixed</option><option>easy</option><option>medium</option><option>hard</option></select></p>
<p><select name="type" style="width:100%;padding:10px"><option value="practice">Practice</option><option value="mock">Mock</option><option value="sectional">Sectional</option><option value="previous_year">Previous Year</option></select></p>
<button class="btn" style="border:0">Generate Tests</button></form></section>
</div>

<section class="card" style="margin-top:24px"><h2>Publish Bilingual Question</h2>
<form method="POST" action="{{ route('admin.content.questions.store') }}">@csrf
<div class="grid">
<div><select name="subject_id" required style="width:100%;padding:10px"><option value="">Subject</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></div>
<div><select name="topic_id" style="width:100%;padding:10px"><option value="">Topic (optional)</option>@foreach($topics as $topic)<option value="{{ $topic->id }}">{{ $topic->subject_name }} — {{ $topic->name }}</option>@endforeach</select></div>
<div><select name="exam_id" style="width:100%;padding:10px"><option value="">All exams</option>@foreach($exams as $exam)<option value="{{ $exam->id }}">{{ $exam->name }}</option>@endforeach</select></div>
</div>
<p><textarea name="question_text" placeholder="Question in English" required style="width:100%;padding:10px"></textarea></p>
<p><textarea name="question_text_hi" placeholder="प्रश्न हिंदी में" required style="width:100%;padding:10px"></textarea></p>
<div class="grid">@for($i=0;$i<4;$i++)<div><input name="options[]" placeholder="Option {{ $i+1 }} English" required style="width:100%;padding:9px"><input name="options_hi[]" placeholder="विकल्प {{ $i+1 }} हिंदी" required style="width:100%;padding:9px;margin-top:4px"></div>@endfor</div>
<p><select name="correct_option" required><option value="">Correct option</option><option value="0">Option 1</option><option value="1">Option 2</option><option value="2">Option 3</option><option value="3">Option 4</option></select>
<select name="difficulty"><option>easy</option><option selected>medium</option><option>hard</option></select></p>
<p><textarea name="explanation" placeholder="English explanation" style="width:100%;padding:10px"></textarea></p>
<p><textarea name="explanation_hi" placeholder="हिंदी व्याख्या" style="width:100%;padding:10px"></textarea></p>
<button class="btn" style="border:0">Verify & Publish</button></form></section>

<h2>Recent Questions</h2>
@foreach($questions as $question)<div class="card"><strong>{{ $question->subject_name }} / {{ $question->topic_name ?? 'General' }}</strong> — {{ $question->difficulty }}<p>{{ $question->question_text }}</p><p>{{ $question->question_text_hi }}</p><small>{{ $question->verification_status }} | {{ $question->is_published ? 'Published' : 'Draft' }}</small></div>@endforeach
{{ $questions->links() }}

<div class="grid" style="margin-top:24px"><section class="card"><h2>Recent Tests</h2>
@forelse($tests as $test)<p><strong>{{ $test->title }}</strong><br>{{ $test->exam_name }} — {{ $test->total_questions }} questions
<form method="POST" action="{{ route('admin.content.tests.toggle', $test->id) }}" style="display:inline">@csrf @method('PATCH')<button>{{ $test->is_active ? 'Deactivate' : 'Activate' }}</button></form></p>@empty<p>No tests generated yet.</p>@endforelse
</section><section class="card"><h2>Test Series</h2>
@forelse($series as $item)<p><strong>{{ $item->name }}</strong><br>{{ $item->exam_name }}
<form method="POST" action="{{ route('admin.content.series.toggle', $item->id) }}" style="display:inline">@csrf @method('PATCH')<button>{{ $item->is_active ? 'Deactivate' : 'Activate' }}</button></form></p>@empty<p>No test series yet.</p>@endforelse
</section></div>
@endsection
