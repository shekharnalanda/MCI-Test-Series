<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Start Free Demo - MCI Test Series</title>
    <style>
        *{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f3f6fb;color:#172033}.wrap{min-height:100vh;display:grid;place-items:center;padding:24px}.card{width:min(520px,100%);background:#fff;border-radius:20px;padding:32px;box-shadow:0 20px 60px rgba(20,46,92,.14)}.brand{color:#153765;font-weight:800;font-size:14px;letter-spacing:.08em}.card h1{margin:10px 0 8px;font-size:30px}.muted{color:#60708a;line-height:1.6}.alert{padding:12px 14px;border-radius:10px;margin:16px 0}.success{background:#eaf8ef;color:#176b37}.error{background:#fff0f0;color:#9b2525}label{display:block;font-weight:700;margin:18px 0 7px}input{width:100%;padding:13px 14px;border:1px solid #cbd5e1;border-radius:10px;font-size:16px}.btn{width:100%;border:0;border-radius:10px;background:#1554a2;color:#fff;font-weight:700;padding:14px;margin-top:18px;cursor:pointer}.links{display:flex;justify-content:space-between;gap:14px;margin-top:20px}.links a{color:#1554a2;text-decoration:none;font-weight:700}@media(max-width:480px){.card{padding:24px}.links{flex-direction:column}}
    </style>
</head>
<body><main class="wrap"><section class="card">
    <div class="brand">MCI TEST SERIES</div><h1>Start Your Free Demo</h1>
    <p class="muted">Verify your email and go directly to the free demo tests. No admission form is required.</p>
    @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
    @if(!session('otp_sent'))
        <form method="POST" action="{{ route('demo.send-otp') }}">@csrf
            <label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
            <button class="btn" type="submit">Send OTP</button>
        </form>
    @else
        <form method="POST" action="{{ route('demo.verify-otp') }}">@csrf
            <input type="hidden" name="email" value="{{ old('email') }}">
            <label for="otp">6-digit OTP</label><input id="otp" inputmode="numeric" name="otp" maxlength="6" required autocomplete="one-time-code">
            <button class="btn" type="submit">Verify &amp; Open Demo</button>
        </form>
    @endif
    <div class="links"><a href="{{ route('home') }}">Back to Home</a><a href="{{ route('admission.create') }}">Apply for Admission</a></div>
</section></main></body></html>
