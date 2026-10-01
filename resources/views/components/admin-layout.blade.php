@props(['title'])
@php($otherLocale = app()->getLocale() === 'en' ? 'ja' : 'en')
@php($pendingSuggestions = \App\Models\Suggestion::pending()->count())
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · {{ __('AllerScan team') }}</title>
    <x-preferences-head/>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="admin-app">
<a class="skip-link" href="#main">{{ __('Skip to content') }}</a>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('products') }}" aria-label="{{ __('AllerScan team') }}"><span class="brand-mark"><x-icon name="scan"/></span><span><span class="brand-name">Aller<span class="brand-light">Scan</span></span><small>{{ __('CATALOG TEAM') }}</small></span></a>
        <p class="workspace-label">{{ __('WORKSPACE') }}</p>
        <nav aria-label="{{ __('Team navigation') }}">
            <a class="nav-link {{ request()->routeIs('products') ? 'active' : '' }}" href="{{ route('products') }}" @if(request()->routeIs('products')) aria-current="page" @endif><x-icon name="box"/><span>{{ __('Products') }}</span></a>
            <a class="nav-link {{ request()->routeIs('admin.suggestions') ? 'active' : '' }}" href="{{ route('admin.suggestions') }}" @if(request()->routeIs('admin.suggestions')) aria-current="page" @endif><x-icon name="inbox"/><span>{{ __('Suggestions') }}</span>@if($pendingSuggestions)<b class="nav-badge" aria-label="{{ trans_choice('{1} :count suggestion waiting for review|[2,*] :count suggestions waiting for review', $pendingSuggestions, ['count' => $pendingSuggestions]) }}">{{ $pendingSuggestions }}</b>@endif</a>
            <a class="nav-link" href="{{ route('discover') }}"><x-icon name="home"/><span>{{ __('View shopper site') }}</span></a>
        </nav>
        <div class="staff">
            <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            <div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="icon-button" aria-label="{{ __('Sign out') }}"><x-icon name="logout"/></button></form>
        </div>
    </aside>
    <div class="workspace">
        <header class="topbar">
            <div><p class="eyebrow">{{ __('CATALOG TEAM') }}</p><h1>{{ $title }}</h1></div>
            <div class="header-actions">
                <a class="text-link" href="{{ route('language', $otherLocale) }}" hreflang="{{ $otherLocale }}" lang="{{ $otherLocale }}">{{ $otherLocale === 'en' ? 'English' : '日本語' }}</a>
                <form class="topbar-signout" method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="text-button">{{ __('Sign out') }}</button></form>
            </div>
        </header>
        <main id="main" class="admin-main">
            @if($pendingSuggestions && ! request()->routeIs('admin.suggestions'))
                <a class="notice warning review-notice" href="{{ route('admin.suggestions') }}"><x-icon name="inbox"/><span><strong>{{ trans_choice('{1} :count suggestion waiting for review|[2,*] :count suggestions waiting for review', $pendingSuggestions, ['count' => $pendingSuggestions]) }}</strong> {{ __('Shoppers asked for new products or allergens.') }}</span><span class="review-notice-link">{{ __('Review now') }} <x-icon name="arrow"/></span></a>
            @endif
            {{ $slot }}
        </main>
    </div>
</div>
@include('partials.js-translations')
@livewireScripts
</body>
</html>
