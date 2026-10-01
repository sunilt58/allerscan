<x-layout :title="__('Create account')">
<div class="auth-panel">
    <span class="section-kicker">{{ __('ACROSS YOUR DEVICES') }}</span>
    <h1>{{ __('Make it yours') }}<span class="heading-dot">.</span></h1>
    <p>{{ __('A free account keeps your allergens and saved products with you on every device. Browsing and scanning never need one.') }}</p>
    <form method="POST" action="{{ route('register') }}">
        @csrf
        <label for="name">{{ __('Name') }}</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="100" autocomplete="name" autofocus>
        @error('name')<p role="alert" class="error-text">{{ $message }}</p>@enderror
        <label for="email">{{ __('Email address') }}</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="username">
        @error('email')<p role="alert" class="error-text">{{ $message }}</p>@enderror
        <label for="password">{{ __('Password') }}</label>
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" aria-describedby="password-help">
        <p id="password-help" class="small muted">{{ __('At least 8 characters.') }}</p>
        @error('password')<p role="alert" class="error-text">{{ $message }}</p>@enderror
        <label for="password_confirmation">{{ __('Confirm password') }}</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password">
        <label class="checkbox-label"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))><span>{{ __('I agree that AllerScan stores the allergens I select and the products I save with my account.') }} <a class="text-link" href="{{ route('privacy') }}">{{ __('Privacy & terms') }}</a></span></label>
        @error('consent')<p role="alert" class="error-text">{{ $message }}</p>@enderror
        <button class="button primary wide" type="submit">{{ __('Create account') }} <x-icon name="arrow"/></button>
    </form>
    <p>{{ __('Already have an account?') }} <a class="text-link" href="{{ route('login') }}">{{ __('Sign in') }}</a></p>
</div>
</x-layout>
