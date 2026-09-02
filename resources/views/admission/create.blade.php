@extends('layouts.app')

@section('title','Online Admission - MCI Test Series')

@section('content')

<div class="card">
<h2>Online Test Series Admission</h2>
<p>
    Complete all required information. Photograph and verified email
    are mandatory before final submission.
</p>
</div>

<div class="card">
<h3>Step 1: Verify Email</h3>

<form method="POST" action="{{ route('admission.send-otp') }}">
@csrf
<label>Email Address</label>
<input type="email" name="email" value="{{ old('email') }}" required>
<br><br>
<button>Send Email OTP</button>
</form>

<br>

<form method="POST" action="{{ route('admission.verify-otp') }}">
@csrf
<label>Email Address</label>
<input type="email" name="email" value="{{ old('email') }}" required>

<label>6 Digit OTP</label>
<input type="text" name="otp" maxlength="6" required>

<br><br>
<button>Verify OTP</button>
</form>
</div>

<div class="card">
<h3>Step 2: Admission Details</h3>

<form method="POST"
      action="{{ route('admission.store') }}"
      enctype="multipart/form-data">

@csrf

<div class="grid">

<div>
<label>Student Name *</label>
<input name="name" value="{{ old('name') }}" required>
</div>

<div>
<label>Father's Name</label>
<input name="father_name" value="{{ old('father_name') }}">
</div>

<div>
<label>Mother's Name</label>
<input name="mother_name" value="{{ old('mother_name') }}">
</div>

<div>
<label>Email *</label>
<input type="email" name="email" value="{{ old('email') }}" required>
</div>

<div>
<label>Mobile Number *</label>
<input name="phone" value="{{ old('phone') }}" required>
</div>

<div>
<label>Date of Birth *</label>
<input type="date" name="date_of_birth" required>
</div>

<div>
<label>Gender *</label>
<select name="gender" required>
<option value="">Select</option>
<option>Male</option>
<option>Female</option>
<option>Other</option>
</select>
</div>

<div>
<label>Photograph *</label>
<input type="file" name="photo" accept="image/*" required>
</div>

<div>
<label>City *</label>
<input name="city" required>
</div>

<div>
<label>District *</label>
<input name="district" required>
</div>

<div>
<label>State *</label>
<input name="state" required>
</div>

<div>
<label>PIN Code *</label>
<input name="pincode" required>
</div>

</div>

<label>Full Address *</label>
<textarea name="address" rows="4" required></textarea>

<br><br>
<button type="submit">Submit Admission Application</button>

</form>
</div>
@endsection
