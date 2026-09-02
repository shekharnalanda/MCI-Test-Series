@extends('layouts.app')

@section('title','MCI Test Series')

@section('content')
<div class="hero">
    <h1>Prepare. Practice. Perform.</h1>
    <p>
        Professional online test platform for SSC, Railway, Banking,
        UPSC, BPSC, Bihar Police, Teaching and major competitive examinations.
    </p>

    <p>
        <a class="btn" href="{{ route('admission.create') }}">
            Join Test Series
        </a>
    </p>
</div>

<div class="grid">
    <div class="card"><h3>Professional Mock Tests</h3><p>Real examination style test experience.</p></div>
    <div class="card"><h3>Smart Question Bank</h3><p>Central reusable question bank with controlled randomization.</p></div>
    <div class="card"><h3>Performance Analytics</h3><p>Score, accuracy, percentage and detailed evaluation.</p></div>
    <div class="card"><h3>Current Affairs</h3><p>Designed for continuously updated current-affairs testing.</p></div>
</div>
@endsection
