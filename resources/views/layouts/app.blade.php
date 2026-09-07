<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="MCI Test Series - bilingual online mock tests and practice sets for competitive examinations.">
<meta name="theme-color" content="#082654">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="apple-touch-icon" href="{{ asset('images/mci-test-series-logo.png') }}">
<title>@yield('title','MCI Test Series')</title>
<style>
:root{--navy:#082654;--blue:#155ba7;--gold:#f4b63e;--ink:#14213d;--muted:#5d6b82;--line:#dce6f2}*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;background:#f6f9fd;color:var(--ink);line-height:1.55}a{color:inherit}.site-header{position:sticky;top:0;z-index:40;background:rgba(8,38,84,.98);color:#fff;box-shadow:0 4px 20px #071b3f2e}.top-strip{background:#061d42;font-size:13px}.top-inner,.nav-inner{width:min(1200px,92%);margin:auto;display:flex;align-items:center;justify-content:space-between;gap:18px}.top-inner{min-height:34px}.top-contact{display:flex;gap:18px;flex-wrap:wrap}.top-strip a{text-decoration:none;color:#e5efff}.nav-inner{min-height:76px}.brand{display:flex;align-items:center;gap:12px;text-decoration:none}.brand img{width:54px;height:54px;object-fit:contain;border-radius:50%;background:#fff}.brand-copy{display:flex;flex-direction:column;line-height:1.1}.brand-name{font-size:20px;font-weight:850}.brand-tag{font-size:11px;color:#bdd3f4;margin-top:6px;letter-spacing:.6px;text-transform:uppercase}.main-nav{display:flex;align-items:center;justify-content:flex-end;gap:5px;flex-wrap:wrap}.main-nav>a,.nav-form button{color:#fff;text-decoration:none;padding:9px 11px;border-radius:7px;font-weight:650;font-size:14px;background:none;border:0;cursor:pointer;font-family:inherit}.main-nav>a:hover,.nav-form button:hover{background:#ffffff1f}.main-nav .nav-cta{background:var(--gold);color:#17233a}.nav-form{display:inline;margin:0}.page-shell{min-height:60vh}.container{width:min(1160px,92%);margin:32px auto}.card{background:#fff;border:1px solid #e5edf6;border-radius:14px;padding:24px;box-shadow:0 8px 26px #0f2a5212;margin-bottom:20px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:18px}label{display:block;font-weight:700;margin:12px 0 6px}input,select,textarea{width:100%;padding:11px;border:1px solid #cbd7e6;border-radius:8px;font:inherit}button,.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;background:var(--blue);color:#fff;border:0;border-radius:8px;padding:11px 18px;text-decoration:none;cursor:pointer;font-weight:750;font-family:inherit}.btn:hover{filter:brightness(.94)}.btn-danger{background:#a82828}.success{background:#e8f7ec;padding:12px;border-radius:7px;margin-bottom:15px}.error{background:#fdeaea;padding:12px;border-radius:7px;margin-bottom:15px}table{width:100%;border-collapse:collapse}th,td{padding:10px;border-bottom:1px solid #e5e9f0;text-align:left}.badge{display:inline-block;padding:5px 9px;border-radius:20px;background:#e9eff9}.site-footer{background:#061d42;color:#d7e4f7;margin-top:64px}.footer-grid{width:min(1200px,92%);margin:auto;padding:48px 0 32px;display:grid;grid-template-columns:1.35fr 1fr 1fr 1.25fr;gap:34px}.footer-brand{display:flex;align-items:center;gap:12px;color:#fff;font-weight:850;font-size:18px}.footer-brand img{width:58px;height:58px;object-fit:contain;border-radius:50%;background:#fff}.site-footer h3{color:#fff;font-size:16px;margin:0 0 15px}.site-footer p{margin:8px 0}.site-footer a{color:#d7e4f7;text-decoration:none}.footer-links{display:grid;gap:9px}.footer-bottom{border-top:1px solid #ffffff1f}.copyright-wrap{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.pwa-install{padding:7px 12px;border-radius:7px;background:var(--gold);color:#17233a;font-size:12px;font-weight:850;box-shadow:none}.pwa-install[hidden]{display:none}@media(min-width:769px){.pwa-install{display:none}}.footer-bottom-inner{width:min(1200px,92%);margin:auto;padding:18px 0;display:flex;justify-content:space-between;gap:15px;flex-wrap:wrap;font-size:13px;color:#aebfda}@media(max-width:900px){.top-strip{display:none}.site-header{position:static}.footer-grid{grid-template-columns:1fr 1fr}}@media(max-width:640px){.nav-inner{display:block;padding:10px 0}.brand{margin-bottom:10px}.main-nav{justify-content:flex-start}.main-nav>a,.nav-form button{font-size:13px;padding:7px 8px}.brand img{width:46px;height:46px}.brand-name{font-size:17px}.brand-tag{display:none}.footer-grid{grid-template-columns:1fr;padding-top:36px}.site-footer{margin-top:44px}}@yield('styles')
</style>
</head><body>
<header class="site-header"><div class="top-strip"><div class="top-inner"><div class="top-contact"><a href="tel:+917004773247">☎ 7004773247</a><a href="tel:+919334779133">☎ 9334779133</a><a href="mailto:mcieducationalgroup@gmail.com">✉ mcieducationalgroup@gmail.com</a></div><span>Hindi + English • Exam-style Practice</span></div></div>
<nav class="nav-inner" aria-label="Main navigation"><a class="brand" href="{{ route('home') }}"><img src="{{ asset('images/mci-test-series-logo.png') }}" alt="MCI Test Series logo"><span class="brand-copy"><span class="brand-name">MCI TEST SERIES</span><span class="brand-tag">Prepare • Practice • Perform</span></span></a><div class="main-nav"><a href="{{ route('home') }}">Home</a><a href="{{ route('home') }}#exams">Exams</a><a href="{{ route('home') }}#demo-tests">Free Demo</a><a href="{{ route('home') }}#features">Features</a>@guest<a href="{{ route('login') }}">Login</a><a class="nav-cta" href="{{ route('admission.create') }}">Admission</a>@endguest @auth<a href="{{ auth()->user()->role === 'admin' ? route('admin.dashboard') : route('student.dashboard') }}">Dashboard</a><a href="{{ route('password.edit') }}">Password</a><form class="nav-form" method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Logout</button></form>@endauth</div></nav></header>
<main class="page-shell">@hasSection('fullwidth')@yield('fullwidth')@else<div class="container">@if(session('success'))<div class="success">{{ session('success') }}</div>@endif @if($errors->any())<div class="error">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif @yield('content')</div>@endif</main>
<footer class="site-footer" id="contact"><div class="footer-grid"><div><div class="footer-brand"><img src="{{ asset('images/mci-test-series-logo.png') }}" alt="MCI logo"><span>MCI TEST SERIES</span></div><p>A professional bilingual online practice and test platform for major competitive examinations.</p><p>Run under Chandrashekhar &amp; Narayan Educational Trust.</p></div><div><h3>Quick Links</h3><div class="footer-links"><a href="{{ route('home') }}#exams">Explore Exams</a><a href="{{ route('home') }}#demo-tests">Free Demo Tests</a><a href="{{ route('admission.create') }}">Online Admission</a><a href="{{ route('login') }}">Student Login</a></div></div><div><h3>Popular Exams</h3><div class="footer-links"><span>SSC &amp; Railway</span><span>Banking</span><span>UPSC &amp; BPSC</span><span>Bihar Police</span><span>Teaching Exams</span></div></div><div><h3>Contact Us</h3><p>MCI Campus, Quamruddin Ganj,<br>Bihar Sharif, Nalanda – 803101</p><p><a href="tel:+917004773247">7004773247</a> • <a href="tel:+919334779133">9334779133</a></p><p><a href="mailto:mcieducationalgroup@gmail.com">mcieducationalgroup@gmail.com</a></p></div></div><div class="footer-bottom"><div class="footer-bottom-inner"><span class="copyright-wrap"><span>© {{ date('Y') }} MCI Test Series. All rights reserved.</span><button type="button" id="pwa-install-button" class="pwa-install">⬇ Install App</button></span><span>Secure • Bilingual • Verified Question Bank</span></div></div></footer>
<script>
(() => {
  const button = document.getElementById('pwa-install-button');
  let installPrompt = null;
  const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  if (standalone && button) { button.textContent = '✓ App Installed'; button.disabled = true; }

  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    installPrompt = event;
    if (button) button.hidden = false;
  });

  button?.addEventListener('click', async () => {
    if (standalone) return;
    if (installPrompt) {
      installPrompt.prompt();
      const result = await installPrompt.userChoice;
      if (result.outcome === 'accepted') button.hidden = true;
      installPrompt = null;
      return;
    }
    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
    alert(ios
      ? 'iPhone/iPad: Safari का Share बटन दबाकर “Add to Home Screen” चुनिए।'
      : 'Browser menu (⋮) खोलकर “Install app” या “Add to Home screen” चुनिए।');
  });

  window.addEventListener('appinstalled', () => { if (button) { button.textContent = '✓ App Installed'; button.disabled = true; } });
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', async () => {
      try {
        await navigator.serviceWorker.register('/sw.js', { scope: '/' });
        await navigator.serviceWorker.ready;
        if (!navigator.serviceWorker.controller && !sessionStorage.getItem('mci-pwa-activated')) {
          sessionStorage.setItem('mci-pwa-activated', '1');
          window.location.reload();
          return;
        }
        sessionStorage.removeItem('mci-pwa-activated');
      } catch (error) {
        console.error('MCI app installation service could not start.', error);
      }
    });
  }
})();
</script>
</body></html>
