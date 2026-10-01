<x-layout :title="__('Sign in')">
<div class="auth-panel">
    <span class="section-kicker">{{ __('GOOD TO SEE YOU') }}</span>
    <h1>{{ __('Welcome back') }}<span class="heading-dot">.</span></h1>
    <p>{{ __('Sign in to bring your allergens and saved products to this device.') }}</p>
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label for="email">{{ __('Email address') }}</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" autofocus>
        <label for="password">{{ __('Password') }}</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
        @error('email')<p role="alert" class="error-text">{{ $message }}</p>@enderror
        @error('password')<p role="alert" class="error-text">{{ $message }}</p>@enderror
        <label class="checkbox-label"><input type="checkbox" name="remember" value="1" @checked(old('remember'))>{{ __('Keep me signed in on this device') }}</label>
        <button class="button primary wide" type="submit">{{ __('Sign in') }} <x-icon name="arrow"/></button>
    </form>
    <p>{{ __('New to AllerScan?') }} <a class="text-link" href="{{ route('register') }}">{{ __('Create account') }}</a></p>
    <a class="text-link" href="{{ route('discover') }}">← {{ __('Back to discovering') }}</a>
</div>
</x-layout>
