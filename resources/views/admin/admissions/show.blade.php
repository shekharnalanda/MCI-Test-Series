@extends('layouts.app')

@section('title','Admission Details')

@section('content')

<div class="card">
<h2>{{ $application->name }}</h2>

<div class="grid">

<div>
<p><strong>Application:</strong><br>
{{ $application->application_no }}</p>
</div>

<div>
<p><strong>Status:</strong><br>
{{ ucfirst($application->status) }}</p>
</div>

<div>
<p><strong>Email:</strong><br>
{{ $application->email }}</p>
</div>

<div>
<p><strong>Phone:</strong><br>
{{ $application->phone }}</p>
</div>

<div>
<p><strong>Date of Birth:</strong><br>
{{ optional($application->date_of_birth)->format('d-m-Y') }}</p>
</div>

<div>
<p><strong>Gender:</strong><br>
{{ $application->gender }}</p>
</div>

</div>

<p>
<strong>Address:</strong><br>
{{ $application->address }},
{{ $application->city }},
{{ $application->district }},
{{ $application->state }} -
{{ $application->pincode }}
</p>

@if($application->photo_path)
<p><strong>Photograph:</strong></p>
<img
    src="{{ asset('storage/'.$application->photo_path) }}"
    alt="Student Photograph"
    style="width:130px;height:160px;object-fit:cover;border-radius:8px;border:1px solid #ddd"
>
@endif

</div>

@if($application->status === 'submitted')

<div class="card">

<h3>Admission Decision</h3>

<form
    method="POST"
    action="{{ route('admin.admissions.approve',$application) }}"
    style="display:inline-block;margin-right:10px"
>
@csrf
<button type="submit">
Approve & Create Student Account
</button>
</form>

<hr style="margin:25px 0">

<form
    method="POST"
    action="{{ route('admin.admissions.reject',$application) }}"
>
@csrf

<label>Rejection / Admin Notes</label>
<textarea
    name="admin_notes"
    rows="4"
    placeholder="Optional admin remarks"
></textarea>

<br><br>

<button type="submit" class="btn-danger">
Reject Application
</button>

</form>

</div>

@endif

@endsection
