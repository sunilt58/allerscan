@props(['title' => 'Discover', 'eyebrow' => ''])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#245e4c">
    <meta name="description" content="Find product allergen information, highlight your preferences, and listen in Japanese or English. AllerScan graduation demo.">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>{{ $title }} · AllerScan</title>
    <x-preferences-head/>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="shopper-app">
<a class="skip-link" href="#main">Skip to content / コンテンツへ</a>
<header class="site-header">
    <a class="shop-brand" href="{{ route('discover') }}" aria-label="AllerScan home"><span class="shop-brand-mark"><x-icon name="scan"/></span>Aller<span>Scan</span><span class="brand-period">.</span></a>
    <nav class="primary-nav" aria-label="Main navigation">
        @foreach([['discover','grid','Discover','探す'],['scan','scan','Scan','スキャン'],['saved','bookmark','Saved','保存'],['settings','settings','Settings','設定']] as [$route,$icon,$label,$ja])
            <a href="{{ route($route) }}" class="{{ request()->routeIs($route) ? 'selected' : '' }}" @if(request()->routeIs($route)) aria-current="page" @endif><x-icon :name="$icon"/><span>{{ $label }}<small lang="ja">{{ $ja }}</small></span></a>
        @endforeach
    </nav>
    <a class="profile-shortcut" href="{{ route('settings') }}#allergens"><span class="profile-avatar"><x-icon name="user"/></span><span>My allergens<small data-allergen-count>Set your preferences</small></span><x-icon name="arrow"/></a>
</header>
<aside class="demo-notice" aria-label="Safety notice"><x-icon name="info"/><p><strong>Demo only — not for medical or dietary decisions.</strong> Products and allergen records here are fictional or may be incomplete. Always check the actual package label and manufacturer. <span lang="ja">デモ版です。掲載情報は架空または不完全な場合があります。必ず実際の商品表示・メーカー情報をご確認ください。</span></p></aside>
<main id="main" class="shop-main">{{ $slot }}</main>
<footer class="shop-footer"><a class="shop-brand" href="{{ route('discover') }}">AllerScan<span class="brand-period">.</span></a><p>More clarity. Every day.<small lang="ja">商品の情報を、もっと身近に。</small></p><div><span>Graduation project · 2027</span><a href="{{ route('privacy') }}">Privacy & terms</a>@auth @if(auth()->user()->role === 'admin')<a href="{{ route('products') }}">Manage catalog</a>@endif<form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="text-button">Sign out</button></form>@else<a href="{{ route('login') }}">Team sign in</a>@endauth</div></footer>
<div class="toast" role="status" data-toast hidden></div>
<noscript><p class="no-script">JavaScript is needed for camera scanning, personal highlights, saved products, and voice. Product search and information still work.</p></noscript>
@php($allergenCatalog = \App\Models\Allergen::all(['code', 'name_en', 'name_ja']))
<script type="application/json" id="allergen-catalog">@json($allergenCatalog)</script>
@livewireScripts
</body>
</html>
