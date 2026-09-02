<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'MCI Test Series')</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#172033}
        header{background:#102a56;color:#fff;padding:15px 5%}
        nav{display:flex;align-items:center;justify-content:space-between;gap:20px}
        nav a{color:#fff;text-decoration:none;margin-left:15px}
        .brand{font-size:21px;font-weight:700}
        .container{width:min(1150px,92%);margin:30px auto}
        .card{background:#fff;border-radius:12px;padding:24px;box-shadow:0 5px 20px rgba(0,0,0,.07);margin-bottom:20px}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:18px}
        label{display:block;font-weight:600;margin:12px 0 6px}
        input,select,textarea{width:100%;padding:11px;border:1px solid #ccd3df;border-radius:7px}
        button,.btn{display:inline-block;background:#164a96;color:#fff;border:0;border-radius:7px;padding:11px 18px;text-decoration:none;cursor:pointer}
        .btn-danger{background:#a82828}
        .success{background:#e8f7ec;padding:12px;border-radius:7px;margin-bottom:15px}
        .error{background:#fdeaea;padding:12px;border-radius:7px;margin-bottom:15px}
        table{width:100%;border-collapse:collapse}
        th,td{padding:10px;border-bottom:1px solid #e5e9f0;text-align:left}
        .hero{padding:55px 0;text-align:center}
        .hero h1{font-size:40px;margin-bottom:12px}
        .badge{display:inline-block;padding:5px 9px;border-radius:20px;background:#e9eff9}
        @media(max-width:600px){.hero h1{font-size:29px}}
    </style>
</head>
<body>

<header>
<nav>
    <div class="brand">MCI TEST SERIES</div>
    <div>
        <a href="{{ route('home') }}">Home</a>
        @guest
            <a href="{{ route('admission.create') }}">Admission</a>
            <a href="{{ route('login') }}">Login</a>
        @endguest
    </div>
</nav>
</header>

<div class="container">

@if(session('success'))
    <div class="success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="error">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

@yield('content')

</div>
</body>
</html>
