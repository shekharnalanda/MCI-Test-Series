@extends('layouts.app')

@section('title', 'Plans - MCI Test Series')

@section('styles')
.plans-hero{text-align:center;padding:42px 18px 24px}.plans-hero h1{font-size:clamp(32px,5vw,52px);margin:0;color:var(--navy)}.plans-hero p{color:var(--muted);font-size:18px}.plans-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;align-items:stretch}.plan-card{position:relative;display:flex;flex-direction:column;background:#fff;border:1px solid var(--line);border-radius:18px;padding:25px;box-shadow:0 12px 34px #0f2a5212}.plan-card.popular{border:2px solid var(--blue);transform:translateY(-7px)}.plan-card.value{border:2px solid var(--gold)}.plan-tag{position:absolute;top:-13px;right:18px;background:var(--blue);color:#fff;border-radius:20px;padding:5px 11px;font-size:11px;font-weight:850}.plan-card.value .plan-tag{background:var(--gold);color:#17233a}.plan-card h2{margin:2px 0 4px;color:var(--navy)}.plan-hi{color:var(--blue);font-weight:750}.plan-price{font-size:34px;font-weight:900;color:var(--navy);margin:14px 0 2px}.plan-meta{color:var(--muted);font-size:14px}.plan-list{list-style:none;padding:0;margin:20px 0 12px}.plan-list li{padding:8px 0;border-bottom:1px solid #edf2f7}.plan-list li:before{content:'✓';color:#0b9853;font-weight:900;margin-right:8px}.plan-more{border:1px solid var(--line);border-radius:10px;margin:0 0 20px;overflow:hidden}.plan-more summary{cursor:pointer;color:var(--blue);font-weight:800;padding:11px 13px;list-style:none;display:flex;justify-content:space-between}.plan-more summary::-webkit-details-marker{display:none}.plan-more summary:after{content:'+'}.plan-more[open] summary:after{content:'−'}.plan-more .plan-list{margin:0;padding:3px 13px 9px}.plan-select{width:100%;margin-top:auto}.plans-note{text-align:center;color:var(--muted);margin:26px auto 0;max-width:850px}@media(max-width:1000px){.plans-grid{grid-template-columns:repeat(2,1fr)}.plan-card.popular{transform:none}}@media(max-width:600px){.plans-grid{grid-template-columns:1fr}}
@endsection

@section('content')
<section class="plans-hero">
    <h1>MCI Test Series Plans</h1>
    <p>अपनी तैयारी, आवश्यकता और समय के अनुसार सही plan चुनें।</p>
</section>

<div class="plans-grid">
@forelse($packages as $package)
    @php
        $features = collect($details[$package->slug] ?? []);
        $popular = $package->slug === 'safalta-plan';
        $value = $package->slug === 'varshik-lakshya-plan';
    @endphp
    <article class="plan-card {{ $popular ? 'popular' : '' }} {{ $value ? 'value' : '' }}">
        @if($popular)<span class="plan-tag">MOST POPULAR</span>@endif
        @if($value)<span class="plan-tag">BEST VALUE</span>@endif
        <h2>{{ $package->name }}</h2>
        @if($package->name_hi)<div class="plan-hi">{{ $package->name_hi }}</div>@endif
        <div class="plan-price">₹{{ number_format((float) $package->price, 0) }}</div>
        <div class="plan-meta"><strong>{{ $package->test_limit }} Tests</strong> • {{ $package->validity_days }} Days Validity</div>
        <ul class="plan-list">
            @foreach($features->take(4) as $feature)<li>{{ $feature }}</li>@endforeach
        </ul>
        @if($features->count() > 4)
        <details class="plan-more">
            <summary>Read More Details</summary>
            <ul class="plan-list">
                @foreach($features->slice(4) as $feature)<li>{{ $feature }}</li>@endforeach
            </ul>
        </details>
        @endif
        <a class="btn plan-select" href="{{ route('admission.create', ['package' => $package->slug]) }}">Select Plan</a>
    </article>
@empty
    <div class="card">Plans will be available soon.</div>
@endforelse
</div>

<p class="plans-note">विद्यार्थी package limit के भीतर exam, subject और topic filters से अपने tests स्वयं चुन सकेगा। Plan validity admission approval के बाद लागू होगी।</p>
@endsection
