@extends('layouts.app')

@section('title','Current Affairs Review')

@section('content')

<div class="card">

<h1>Current Affairs Review Center</h1>

<p>
Trusted-source items are automatically scored for
trust, freshness and content quality.
Items not eligible for automatic approval remain
here for administrative review.
</p>

<div class="grid">

<div>
<strong>Pending</strong>
<h2>{{ $counts['pending'] }}</h2>
</div>

<div>
<strong>Approved</strong>
<h2>{{ $counts['approved'] }}</h2>
</div>

<div>
<strong>Processed</strong>
<h2>{{ $counts['processed'] }}</h2>
</div>

<div>
<strong>Rejected</strong>
<h2>{{ $counts['rejected'] }}</h2>
</div>

</div>

</div>

<div class="card">

<p>
<a href="{{ route('admin.current-affairs.index',['status'=>'pending']) }}">
Pending
</a>
|
<a href="{{ route('admin.current-affairs.index',['status'=>'approved']) }}">
Approved
</a>
|
<a href="{{ route('admin.current-affairs.index',['status'=>'processed']) }}">
Processed
</a>
|
<a href="{{ route('admin.current-affairs.index',['status'=>'rejected']) }}">
Rejected
</a>
|
<a href="{{ route('admin.current-affairs.index',['status'=>'all']) }}">
All
</a>
</p>

<table>

<thead>
<tr>
<th>Date</th>
<th>Title</th>
<th>Source</th>
<th>Trust</th>
<th>Freshness</th>
<th>Quality</th>
<th>Status</th>
<th></th>
</tr>
</thead>

<tbody>

@forelse($items as $item)

<tr>

<td>
{{ optional($item->published_at)->format('d-m-Y') ?: '-' }}
</td>

<td>
{{ \Illuminate\Support\Str::limit($item->title,80) }}
</td>

<td>
{{ $item->source?->name ?: '-' }}
</td>

<td>{{ $item->trust_score }}</td>
<td>{{ $item->freshness_score }}</td>
<td>{{ $item->quality_score }}</td>

<td>
{{ ucfirst($item->status) }}
@if($item->auto_approved)
<br><small>Auto Approved</small>
@endif
</td>

<td>
<a href="{{ route('admin.current-affairs.show',$item) }}">
Review
</a>
</td>

</tr>

@empty

<tr>
<td colspan="8">
No current affairs items found.
</td>
</tr>

@endforelse

</tbody>

</table>

{{ $items->links() }}

</div>

@endsection
