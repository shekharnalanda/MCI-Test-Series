<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<title>{{ $attempt->test->title }} | MCI Test Series</title>

<meta name="csrf-token" content="{{ csrf_token() }}">

<style>
*{box-sizing:border-box}

body{
margin:0;
font-family:Arial,sans-serif;
background:#eef2f7;
color:#172033
}

.topbar{
min-height:72px;
background:#102a56;
color:#fff;
display:flex;
align-items:center;
justify-content:space-between;
gap:20px;
padding:10px 24px;
position:sticky;
top:0;
z-index:100
}

.brand-wrap{
display:flex;
align-items:center;
gap:12px
}

.logo-box{
width:48px;
height:48px;
border-radius:8px;
background:#fff;
color:#102a56;
display:flex;
align-items:center;
justify-content:center;
font-weight:800;
font-size:18px
}

.brand{
font-weight:700;
font-size:20px
}

.brand small{
display:block;
font-size:12px;
font-weight:400;
opacity:.85;
margin-top:3px
}

.timer{
font-size:21px;
font-weight:700;
background:#fff;
color:#a11d1d;
padding:10px 16px;
border-radius:8px;
min-width:130px;
text-align:center
}

.exam-layout{
display:grid;
grid-template-columns:minmax(0,1fr) 315px;
min-height:calc(100vh - 72px)
}

.main{
padding:24px
}

.sidebar{
background:#fff;
border-left:1px solid #d7deea;
padding:20px;
position:sticky;
top:72px;
height:calc(100vh - 72px);
overflow:auto
}

.test-title{
background:#fff;
padding:15px 20px;
border-radius:10px;
margin-bottom:18px;
box-shadow:0 3px 12px rgba(0,0,0,.05)
}

.question-card{
background:#fff;
border-radius:10px;
padding:24px;
box-shadow:0 4px 16px rgba(0,0,0,.06)
}

.question-number{
font-size:14px;
font-weight:700;
color:#526078;
margin-bottom:12px
}

.question-text{
font-size:18px;
line-height:1.6;
margin-bottom:22px
}

.option{
display:flex;
align-items:flex-start;
gap:10px;
border:1px solid #dce2eb;
padding:13px;
border-radius:8px;
margin:10px 0;
cursor:pointer
}

.option:hover{
background:#f5f8fc
}

.option input{
margin-top:3px
}

.actions{
display:flex;
gap:10px;
flex-wrap:wrap;
margin-top:24px
}

button{
border:0;
border-radius:7px;
padding:11px 17px;
font-weight:600;
cursor:pointer
}

.primary{
background:#1655a5;
color:#fff
}

.secondary{
background:#e5eaf1;
color:#172033
}

.review{
background:#6f42c1;
color:#fff
}

.clear{
background:#fff3cd;
color:#725900
}

.submit{
background:#198754;
color:#fff;
width:100%;
margin-top:18px
}

.palette{
display:grid;
grid-template-columns:repeat(5,1fr);
gap:8px;
margin:15px 0
}

.palette button{
height:42px;
padding:0;
border-radius:50%;
background:#e8edf4;
color:#172033
}

.palette button.current{
outline:3px solid #1b5ca8
}

.palette button.answered{
background:#198754;
color:#fff
}

.palette button.reviewed{
background:#6f42c1;
color:#fff
}

.palette button.answered-reviewed{
background:#5b2c83;
color:#fff;
box-shadow:inset 0 0 0 3px #29a36a
}

.legend{
font-size:13px;
line-height:1.9
}

.dot{
display:inline-block;
width:12px;
height:12px;
border-radius:50%;
margin-right:5px
}

.green{background:#198754}
.purple{background:#6f42c1}
.gray{background:#e8edf4;border:1px solid #bbb}

.save-status{
font-size:13px;
margin-top:10px;
min-height:18px;
color:#526078
}

@media(max-width:900px){
.exam-layout{grid-template-columns:1fr}
.sidebar{
position:static;
height:auto;
border-left:0;
border-top:1px solid #d7deea
}
}

@media(max-width:600px){
.topbar{padding:10px}
.logo-box{width:40px;height:40px}
.brand{font-size:16px}
.timer{font-size:17px;min-width:105px}
.main{padding:12px}
.question-card{padding:16px}
.palette{grid-template-columns:repeat(6,1fr)}
}
</style>
</head>

<body>

<div class="topbar">

<div class="brand-wrap">

<div class="logo-box">
MCI
</div>

<div class="brand">
MCI TEST SERIES
<small>
Professional Competitive Examination Platform
</small>
</div>

</div>

<div class="timer" id="timer">
--:--:--
</div>

</div>

<div class="exam-layout">

<main class="main">

<div class="test-title">
<strong>{{ $attempt->test->title }}</strong>
<br>
<small>
Attempt #{{ $attempt->attempt_number }}
|
Total Questions: {{ $attempt->total_questions }}
</small>
</div>

@foreach($attempt->attemptQuestions->sortBy('question_order') as $index => $snapshot)

@php
$question = $snapshot->question;
$saved = $attempt->answers->firstWhere('question_id',$question->id);

$orderedOptions = collect($snapshot->option_order)
    ->map(fn($id) => $question->options->firstWhere('id',$id))
    ->filter();
@endphp

<section
class="question-card question-panel"
id="question-panel-{{ $index }}"
data-index="{{ $index }}"
data-question="{{ $question->id }}"
style="{{ $index === 0 ? '' : 'display:none' }}"
>

<div class="question-number">
Question {{ $index + 1 }} of {{ $attempt->total_questions }}
</div>

<div class="question-text">
{!! nl2br(e($question->question_text)) !!}

@if($question->question_text_hi)
<hr>
{!! nl2br(e($question->question_text_hi)) !!}
@endif
</div>

<div class="options">

@foreach($orderedOptions as $option)

<label class="option">

<input
type="radio"
name="question_{{ $question->id }}"
value="{{ $option->id }}"
@if($saved && $saved->selected_option_id === $option->id)
checked
@endif
onchange="saveCurrent(false)"
>

<span>
{{ $option->option_text }}

@if($option->option_text_hi)
<br>
<small>{{ $option->option_text_hi }}</small>
@endif
</span>

</label>

@endforeach

</div>

<div class="actions">

<button
type="button"
class="secondary"
onclick="previousQuestion()"
>
Previous
</button>

<button
type="button"
class="clear"
onclick="clearResponse()"
>
Clear Response
</button>

<button
type="button"
class="review"
onclick="markReviewAndNext()"
>
Mark for Review & Next
</button>

<button
type="button"
class="primary"
onclick="saveAndNext()"
>
Save & Next
</button>

</div>

<div class="save-status" id="save-status-{{ $index }}"></div>

</section>

@endforeach

</main>

<aside class="sidebar">

<h3>Question Palette</h3>

<div class="legend">
<div><span class="dot green"></span> Answered</div>
<div><span class="dot purple"></span> Marked for Review</div>
<div><span class="dot gray"></span> Not Answered</div>
</div>

<div class="palette">

@foreach($attempt->attemptQuestions->sortBy('question_order') as $index => $snapshot)

@php
$saved = $attempt->answers->firstWhere(
    'question_id',
    $snapshot->question_id
);

$class = '';

if($saved && $saved->selected_option_id && $saved->is_marked_for_review){
    $class='answered-reviewed';
} elseif($saved && $saved->is_marked_for_review){
    $class='reviewed';
} elseif($saved && $saved->selected_option_id){
    $class='answered';
}
@endphp

<button
type="button"
id="palette-{{ $index }}"
class="{{ $class }} {{ $index === 0 ? 'current' : '' }}"
onclick="goToQuestion({{ $index }})"
>
{{ $index + 1 }}
</button>

@endforeach

</div>

<form
id="submit-form"
method="POST"
action="{{ route('student.attempts.submit',$attempt) }}"
onsubmit="return confirmSubmit()"
>
@csrf

<button type="submit" class="submit">
Submit Test
</button>

</form>

</aside>

</div>

<script>
const attemptId = {{ $attempt->id }};
const totalQuestions = {{ $attempt->attemptQuestions->count() }};
const answerUrl = @json(route('student.attempts.answer',$attempt));
const deadline = new Date(@json($deadline->toIso8601String())).getTime();
const csrf = document.querySelector('meta[name="csrf-token"]').content;

let currentIndex = 0;
let reviewState = {};

@foreach($attempt->answers as $answer)
reviewState[{{ $answer->question_id }}] =
    {{ $answer->is_marked_for_review ? 'true' : 'false' }};
@endforeach

function panel(index){
    return document.getElementById('question-panel-' + index);
}

function questionId(index){
    return Number(panel(index).dataset.question);
}

function selectedOption(index){
    const qid = questionId(index);

    const selected = document.querySelector(
        'input[name="question_' + qid + '"]:checked'
    );

    return selected ? Number(selected.value) : null;
}

function setSaveStatus(text){
    const el = document.getElementById('save-status-' + currentIndex);

    if(el){
        el.textContent = text;
    }
}

async function saveCurrent(markReview = null){

    const qid = questionId(currentIndex);

    if(markReview !== null){
        reviewState[qid] = markReview;
    }

    setSaveStatus('Saving...');

    try{

        const response = await fetch(answerUrl,{
            method:'POST',
            headers:{
                'Content-Type':'application/json',
                'Accept':'application/json',
                'X-CSRF-TOKEN':csrf
            },
            body:JSON.stringify({
                question_id:qid,
                selected_option_id:selectedOption(currentIndex),
                marked_for_review:!!reviewState[qid]
            })
        });

        if(response.status === 409){
            window.location.reload();
            return false;
        }

        if(!response.ok){
            throw new Error('Save failed');
        }

        updatePalette(currentIndex);

        setSaveStatus('Response saved');

        return true;

    }catch(error){

        setSaveStatus('Unable to save. Please try again.');

        return false;
    }
}

function updatePalette(index){

    const btn = document.getElementById('palette-' + index);
    const qid = questionId(index);
    const answered = selectedOption(index) !== null;
    const reviewed = !!reviewState[qid];

    btn.classList.remove(
        'answered',
        'reviewed',
        'answered-reviewed'
    );

    if(answered && reviewed){
        btn.classList.add('answered-reviewed');
    }else if(reviewed){
        btn.classList.add('reviewed');
    }else if(answered){
        btn.classList.add('answered');
    }
}

function goToQuestion(index){

    if(index < 0 || index >= totalQuestions){
        return;
    }

    panel(currentIndex).style.display='none';

    document
        .getElementById('palette-' + currentIndex)
        .classList.remove('current');

    currentIndex=index;

    panel(currentIndex).style.display='block';

    document
        .getElementById('palette-' + currentIndex)
        .classList.add('current');

    window.scrollTo({top:0,behavior:'smooth'});
}

async function saveAndNext(){

    const ok = await saveCurrent(false);

    if(ok && currentIndex < totalQuestions - 1){
        goToQuestion(currentIndex + 1);
    }
}

async function markReviewAndNext(){

    const ok = await saveCurrent(true);

    if(ok && currentIndex < totalQuestions - 1){
        goToQuestion(currentIndex + 1);
    }
}

function previousQuestion(){

    if(currentIndex > 0){
        goToQuestion(currentIndex - 1);
    }
}

async function clearResponse(){

    const qid = questionId(currentIndex);

    document
        .querySelectorAll('input[name="question_' + qid + '"]')
        .forEach(input => input.checked=false);

    await saveCurrent(false);
}

function confirmSubmit(){

    return confirm(
        'Are you sure you want to submit the test? ' +
        'After submission answers cannot be changed.'
    );
}

function updateTimer(){

    const remaining = deadline - Date.now();

    if(remaining <= 0){

        document.getElementById('timer').textContent='00:00:00';

        const form = document.getElementById('submit-form');

        form.onsubmit = null;
        form.submit();

        return;
    }

    const totalSeconds=Math.floor(remaining/1000);

    const hours=Math.floor(totalSeconds/3600);
    const minutes=Math.floor((totalSeconds%3600)/60);
    const seconds=totalSeconds%60;

    document.getElementById('timer').textContent=
        String(hours).padStart(2,'0')+':' +
        String(minutes).padStart(2,'0')+':' +
        String(seconds).padStart(2,'0');
}

updateTimer();
setInterval(updateTimer,1000);
</script>

</body>
</html>
