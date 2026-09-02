@extends('layouts.app')

@section('title','Admissions')

@section('content')
<div class="card">
<h2>Admission Applications</h2>

<table>
<thead>
<tr>
<th>Application</th>
<th>Name</th>
<th>Email</th>
<th>Phone</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>
<tbody>
@foreach($applications as $application)
<tr>
<td>{{ $application->application_no }}</td>
<td>{{ $application->name }}</td>
<td>{{ $application->email }}</td>
<td>{{ $application->phone }}</td>
<td>{{ ucfirst($application->status) }}</td>
<td>
<a href="{{ route('admin.admissions.show',$application) }}">
View
</a>
</td>
</tr>
@endforeach
</tbody>
</table>

{{ $applications->links() }}
</div>
@endsection
