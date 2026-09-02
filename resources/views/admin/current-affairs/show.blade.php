@extends('layouts.app')

@section('title','Current Affairs Item')

@section('content')

<div class="card">

<h2>{{ $currentAffair->title }}</h2>

<p>
<strong>Source:</strong>
{{ $currentAffair->source?->name }}
</p>

<p>
<strong>Published:</strong>
{{ optional($currentAffair->published_at)->format('d-m-Y H:i') ?: '-' }}
</p>

<p>
<strong>Status:</strong>
{{ ucfirst($currentAffair->status) }}
</p>

<div class="grid">

<div>
<strong>Trust Score</strong>
<h2>{{ $currentAffair->trust_score }}</h2>
</div>

<div>
<strong>Freshness Score</strong>
<h2>{{ $currentAffair->freshness_score }}</h2>
</div>

<div>
<strong>Quality Score</strong>
<h2>{{ $currentAffair->quality_score }}</h2>
</div>

</div>

@if($currentAffair->summary)

<hr>

<h3>Summary</h3>

<p style="line-height:1.7">
{{ $currentAffair->summary }}
</p>

@endif

@if($currentAffair->source_url)

<p>
<strong>Source reference stored:</strong> Yes
</p>

@endif

</div>

@if(
    in_array(
        $currentAffair->status,
        ['pending','approved'],
        true
    )
)

<div class="card">

<form
method="POST"
action="{{ route('admin.current-affairs.approve',$currentAffair) }}"
style="display:inline-block;margin-right:10px"
>
@csrf

<button type="submit">
Approve
</button>

</form>

@if($currentAffair->status !== 'processed')

<form
method="POST"
action="{{ route('admin.current-affairs.reject',$currentAffair) }}"
style="display:inline-block"
>
@csrf

<button type="submit" class="btn-danger">
Reject
</button>

</form>

@endif

</div>

@endif

@endsection
