@extends('layouts.app')
@section('title','Test Result')
@section('content')
<style>
.result-hero{background:linear-gradient(135deg,#102a56,#1765ad);color:#fff;border-radius:14px;padding:24px;margin-bottom:20px}.result-hero h1{margin:0 0 8px}.result-hero p{margin:4px 0;opacity:.9}
.result-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:22px}.result-stat{background:#fff;border-radius:12px;padding:18px;text-align:center;box-shadow:0 3px 14px rgba(16,42,86,.08);border-top:4px solid #1765ad}.result-stat h2{margin:0 0 5px;font-size:26px}.result-stat p{margin:0;color:#526078;font-size:13px}
.review-heading{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin:25px 0 14px}.review-card{background:#fff;border-radius:12px;padding:20px;margin-bottom:15px;border-left:5px solid #8a96a8;box-shadow:0 3px 14px rgba(16,42,86,.07)}.review-card.correct{border-left-color:#198754}.review-card.wrong{border-left-color:#dc3545}.review-card.unanswered{border-left-color:#d59b00}
.review-top{display:flex;justify-content:space-between;gap:12px;margin-bottom:12px}.review-number{font-weight:800;color:#102a56}.status{font-size:12px;font-weight:700;border-radius:20px;padding:5px 10px}.status.correct{background:#dff5e9;color:#146c43}.status.wrong{background:#fde2e4;color:#a11d2b}.status.unanswered{background:#fff0c7;color:#765600}
.review-question{font-size:17px;line-height:1.6;margin-bottom:14px}.answer-line{padding:10px 12px;border-radius:8px;margin-top:8px;background:#f4f7fb;line-height:1.5}.answer-line.student-wrong{background:#fdecee;color:#8e2029}.answer-line.right{background:#e7f7ee;color:#146c43}.answer-label{font-weight:700}.review-explanation{margin-top:10px;padding:12px;border-left:3px solid #1765ad;background:#eef5fc;line-height:1.55}.result-actions{display:flex;gap:10px;flex-wrap:wrap;margin:20px 0}
@media(max-width:900px){.result-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:520px){.result-stat{padding:13px 8px}.review-card{padding:15px}.result-hero{padding:18px}}
</style>
<div class="result-hero"><h1>Test Result / परीक्षा परिणाम</h1><h2>{{ $attempt->test->title }}</h2>@if($attempt->test->exam)<p>{{ $attempt->test->exam->name }}</p>@endif<p>Attempt #{{ $attempt->attempt_number }} · Time {{ gmdate('H:i:s', $attempt->time_taken_seconds) }}</p></div>
<div class="result-grid">
<div class="result-stat"><h2>{{ $attempt->obtained_marks }}</h2><p>Marks Obtained / प्राप्तांक</p></div><div class="result-stat"><h2>{{ $attempt->maximum_marks }}</h2><p>Maximum Marks / पूर्णांक</p></div><div class="result-stat"><h2>{{ $attempt->percentage }}%</h2><p>Percentage / प्रतिशत</p></div><div class="result-stat"><h2>{{ data_get($attempt->analytics,'accuracy',0) }}%</h2><p>Accuracy / शुद्धता</p></div>
<div class="result-stat"><h2>{{ $attempt->correct_answers }}</h2><p>Correct / सही</p></div><div class="result-stat"><h2>{{ $attempt->wrong_answers }}</h2><p>Wrong / गलत</p></div><div class="result-stat"><h2>{{ $attempt->unanswered }}</h2><p>Unanswered / छोड़े गए</p></div><div class="result-stat"><h2>{{ data_get($attempt->analytics,'attempt_rate',0) }}%</h2><p>Attempt Rate / प्रयास</p></div>
</div>
<div class="review-heading"><div><h2>Question-wise Review / प्रश्नवार समीक्षा</h2><p>अपने उत्तर को सही उत्तर से मिलाइए।</p></div>@if($attempt->rank)<strong>Rank: {{ $attempt->rank }}</strong>@endif</div>
@foreach($attempt->attemptQuestions->sortBy('question_order')->values() as $index => $snapshot)
@php
$question=$snapshot->question;$answer=$attempt->answers->firstWhere('question_id',$snapshot->question_id);$selected=$answer?->selectedOption;$correctOption=$question->options->firstWhere('is_correct',true);$status=!$answer||!$answer->selected_option_id?'unanswered':($answer->is_correct?'correct':'wrong');$statusText=['correct'=>'Correct / सही','wrong'=>'Wrong / गलत','unanswered'=>'Unanswered / छोड़ा गया'][$status];
@endphp
<article class="review-card {{ $status }}"><div class="review-top"><span class="review-number">Question {{ $index+1 }} / प्रश्न {{ $index+1 }}</span><span class="status {{ $status }}">{{ $statusText }}</span></div>
<div class="review-question">{!! nl2br(e($question->question_text)) !!}@if($question->question_text_hi)<br><small>{!! nl2br(e($question->question_text_hi)) !!}</small>@endif</div>
<div class="answer-line {{ $status==='wrong'?'student-wrong':'' }}"><span class="answer-label">Your Answer / आपका उत्तर:</span> @if($selected){{ $selected->option_text }}@if($selected->option_text_hi) / {{ $selected->option_text_hi }}@endif @else Not attempted / उत्तर नहीं दिया @endif</div>
<div class="answer-line right"><span class="answer-label">Correct Answer / सही उत्तर:</span> @if($correctOption){{ $correctOption->option_text }}@if($correctOption->option_text_hi) / {{ $correctOption->option_text_hi }}@endif @else Answer key unavailable / उत्तर कुंजी उपलब्ध नहीं @endif</div>
@if($question->explanation||$question->explanation_hi)<div class="review-explanation"><strong>Explanation / व्याख्या:</strong><br>@if($question->explanation){!! nl2br(e($question->explanation)) !!}@endif @if($question->explanation_hi)<br>{!! nl2br(e($question->explanation_hi)) !!}@endif</div>@endif
</article>
@endforeach
<div class="result-actions"><a class="btn" href="{{ route('student.tests.index') }}">Back to Tests / टेस्ट सूची</a></div>
@endsection
