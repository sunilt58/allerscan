@props(['title' => null, 'eyebrow' => ''])
@php($otherLocale = app()->getLocale() === 'en' ? 'ja' : 'en')
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#245e4c">
    <meta name="description" content="{{ __('Find product allergen information, highlight your preferences, and listen in Japanese or English. AllerScan graduation demo.') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>{{ $title ?? __('Discover') }} · AllerScan</title>
    <x-preferences-head/>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="shopper-app">
<a class="skip-link" href="#main">{{ __('Skip to content') }}</a>
<header class="site-header">
    <a class="shop-brand" href="{{ route('discover') }}" aria-label="{{ __('AllerScan home') }}"><span class="shop-brand-mark"><x-icon name="scan"/></span>Aller<span>Scan</span><span class="brand-period">.</span></a>
    <nav class="primary-nav" aria-label="{{ __('Main navigation') }}">
        @foreach([['discover','grid','Discover'],['scan','scan','Scan'],['saved','bookmark','Saved'],['settings','settings','Settings']] as [$route,$icon,$label])
            <a href="{{ route($route) }}" class="{{ request()->routeIs($route) ? 'selected' : '' }}" @if(request()->routeIs($route)) aria-current="page" @endif><x-icon :name="$icon"/><span>{{ __($label) }}</span></a>
        @endforeach
    </nav>
    <div class="header-tools">
        <a class="language-switch" href="{{ route('language', $otherLocale) }}" hreflang="{{ $otherLocale }}" lang="{{ $otherLocale }}"><x-icon name="language"/>{{ $otherLocale === 'en' ? 'English' : '日本語' }}</a>
        <a class="profile-shortcut" href="{{ route('settings') }}#allergens"><span class="profile-avatar"><x-icon name="user"/></span><span>{{ __('My allergens') }}<small data-allergen-count>{{ __('Set your preferences') }}</small></span><x-icon name="arrow"/></a>
    </div>
</header>
<aside class="demo-notice" aria-label="{{ __('Safety notice') }}"><x-icon name="info"/><p><strong>{{ __('Demo only — not for medical or dietary decisions.') }}</strong> {{ __('Products and allergen records here are fictional or may be incomplete. Always check the actual package label and manufacturer.') }}</p></aside>
<main id="main" class="shop-main">{{ $slot }}</main>
<footer class="shop-footer"><a class="shop-brand" href="{{ route('discover') }}">AllerScan<span class="brand-period">.</span></a><p>{{ __('More clarity. Every day.') }}</p><div><span>{{ __('Graduation project · 2027') }}</span><a href="{{ route('privacy') }}">{{ __('Privacy & terms') }}</a>@auth @if(auth()->user()->role === 'admin')<a href="{{ route('products') }}">{{ __('Manage catalog') }}</a>@endif<form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="text-button">{{ __('Sign out') }}</button></form>@else<a href="{{ route('login') }}">{{ __('Team sign in') }}</a>@endauth</div></footer>
<div class="toast" role="status" data-toast hidden></div>
<noscript><p class="no-script">{{ __('JavaScript is needed for camera scanning, personal highlights, saved products, and voice. Product search and information still work.') }}</p></noscript>
@php($allergenCatalog = \App\Models\Allergen::all(['code', 'name_en', 'name_ja'])->map(fn ($allergen) => ['code' => $allergen->code, 'name' => $allergen->name]))
<script type="application/json" id="allergen-catalog">@json($allergenCatalog)</script>
@include('partials.js-translations')
@livewireScripts
</body>
</html>
