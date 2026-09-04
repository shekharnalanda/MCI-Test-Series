@extends('layouts.app')

@section('title', 'Admin Operations')

@section('content')
<h1>Admin Operations Center</h1>
<p>Manage students, package access and examinations from one screen.</p>

<div class="grid">
    <section class="card">
        <h2>Create Student</h2>
        <form method="POST" action="{{ route('admin.operations.students.store') }}">
            @csrf
            <p><input name="name" value="{{ old('name') }}" placeholder="Student name" required style="width:100%;padding:10px"></p>
            <p><input name="email" type="email" value="{{ old('email') }}" placeholder="Email" required style="width:100%;padding:10px"></p>
            <p><input name="phone" value="{{ old('phone') }}" placeholder="Phone" required style="width:100%;padding:10px"></p>
            <p><input name="password" type="password" placeholder="Temporary strong password" required style="width:100%;padding:10px"></p>
            <p><select name="package_id" style="width:100%;padding:10px"><option value="">No package yet</option>
                @foreach($packages->where('is_active', 1) as $package)<option value="{{ $package->id }}">{{ $package->name }}</option>@endforeach
            </select></p>
            <button class="btn" type="submit" style="border:0">Create Student</button>
        </form>
    </section>

    <section class="card">
        <h2>Create Package</h2>
        <form method="POST" action="{{ route('admin.operations.packages.store') }}">
            @csrf
            <p><input name="name" placeholder="Package name" required style="width:100%;padding:10px"></p>
            <p><input name="name_hi" placeholder="Hindi name" style="width:100%;padding:10px"></p>
            <p><select name="exam_id" style="width:100%;padding:10px"><option value="">All exams</option>
                @foreach($exams as $exam)<option value="{{ $exam->id }}">{{ $exam->name }}</option>@endforeach
            </select></p>
            <p><input name="price" type="number" min="0" step="0.01" placeholder="Price" required style="width:100%;padding:10px"></p>
            <p><input name="test_limit" type="number" min="1" placeholder="Test limit" required style="width:100%;padding:10px"></p>
            <p><input name="validity_days" type="number" min="1" placeholder="Validity days" required style="width:100%;padding:10px"></p>
            <button class="btn" type="submit" style="border:0">Create Package</button>
        </form>
    </section>

    <section class="card">
        <h2>Create Exam</h2>
        <form method="POST" action="{{ route('admin.operations.exams.store') }}">
            @csrf
            <p><select name="exam_category_id" required style="width:100%;padding:10px"><option value="">Select category</option>
                @foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
            </select></p>
            <p><input name="name" placeholder="Exam name" required style="width:100%;padding:10px"></p>
            <p><input name="name_hi" placeholder="Hindi name" style="width:100%;padding:10px"></p>
            <p><input name="conducting_body" placeholder="Conducting body" style="width:100%;padding:10px"></p>
            <button class="btn" type="submit" style="border:0">Create Exam</button>
        </form>
    </section>
</div>

<h2 style="margin-top:30px">Students</h2>
<div style="overflow:auto"><table style="width:100%;border-collapse:collapse">
<tr><th align="left">Code</th><th align="left">Student</th><th align="left">Access</th><th align="left">Package</th><th>Action</th></tr>
@forelse($students as $student)
<tr style="border-top:1px solid #ddd">
<td>{{ $student->student_code }}</td><td><strong>{{ $student->name }}</strong><br>{{ $student->email }}<br>{{ $student->phone }}</td>
<td>{{ $student->is_active ? 'Active' : 'Disabled' }}</td>
<td>{{ $student->package_name ?? 'Not assigned' }} @if($student->test_limit)<br>{{ $student->tests_used }}/{{ $student->test_limit }} tests used @endif
<form method="POST" action="{{ route('admin.operations.students.package', $student->profile_id) }}" style="margin-top:6px">@csrf
<select name="package_id" required>@foreach($packages->where('is_active', 1) as $package)<option value="{{ $package->id }}">{{ $package->name }}</option>@endforeach</select>
<button type="submit">Assign</button></form></td>
<td align="center"><form method="POST" action="{{ route('admin.operations.students.toggle', $student->user_id) }}">@csrf @method('PATCH')
<button type="submit">{{ $student->is_active ? 'Disable' : 'Enable' }}</button></form></td>
</tr>
@empty<tr><td colspan="5">No students yet.</td></tr>@endforelse
</table></div>
{{ $students->links() }}

<div class="grid" style="margin-top:30px">
<section class="card"><h2>Packages</h2>
@foreach($packages as $package)<p><strong>{{ $package->name }}</strong> — ₹{{ number_format($package->price, 2) }}, {{ $package->test_limit }} tests / {{ $package->validity_days }} days
<form method="POST" action="{{ route('admin.operations.packages.toggle', $package->id) }}" style="display:inline">@csrf @method('PATCH')
<button type="submit">{{ $package->is_active ? 'Deactivate' : 'Activate' }}</button></form></p>@endforeach
</section>
<section class="card"><h2>Exams</h2>
@foreach($exams as $exam)<p><strong>{{ $exam->name }}</strong> @if($exam->name_hi) / {{ $exam->name_hi }} @endif
<form method="POST" action="{{ route('admin.operations.exams.toggle', $exam->id) }}" style="display:inline">@csrf @method('PATCH')
<button type="submit">{{ $exam->is_active ? 'Deactivate' : 'Activate' }}</button></form></p>@endforeach
</section>
</div>
@endsection
