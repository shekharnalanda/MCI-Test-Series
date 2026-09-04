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

<form method="POST" action="{{ route('admin.admissions.approve',$application) }}">
@csrf
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin:14px 0">
<div><label>Package (optional)</label><select name="package_id" style="width:100%"><option value="">Create account only</option>@foreach($packages as $package)<option value="{{ $package->id }}">{{ $package->name }} - Rs. {{ number_format($package->price,2) }}</option>@endforeach</select></div>
<div><label>Fee Amount</label><input type="number" name="fee_amount" min="0" step="0.01" placeholder="Package price"></div>
<div><label>Discount</label><input type="number" name="discount_amount" min="0" step="0.01" value="0"></div>
<div><label>Paid Amount</label><input type="number" name="paid_amount" min="0" step="0.01" value="0"></div>
<div><label>Payment Status</label><select name="payment_status" required><option value="unpaid">Unpaid</option><option value="partial">Partial</option><option value="paid">Paid</option><option value="waived">Waived</option></select></div>
</div>
<label style="display:block;margin:12px 0"><input type="checkbox" name="activate_access" value="1"> Activate package access immediately</label>
<button type="submit">Approve, Create Account & Send Credentials</button>
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
