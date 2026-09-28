<x-layout :title="__('Privacy & terms')">
<div class="page-heading"><span class="section-kicker">{{ __('PLAIN AND SIMPLE') }}</span><h1>{{ __('Privacy & terms') }}<span class="heading-dot">.</span></h1><p>{{ __('What AllerScan keeps, and what it doesn’t.') }}</p></div>
<div class="preferences-sections">
<section class="preference-section" id="privacy">
    <h2>{{ __('Your information') }}</h2>
    <p><strong>{{ __('No shopper account.') }}</strong> {{ __('You can browse, scan, and save products without signing up. Your chosen allergens, theme, text size, voice choices, and saved products are stored only in this browser. They are not sent to our database and do not sync between devices. Clearing your browser data removes them.') }}</p>
    <p><strong>{{ __('Saved products.') }}</strong> {{ __('When you open Saved, your browser sends the saved product numbers to our server so it can return current product information. The numbers are not stored with your identity.') }}</p>
    <p><strong>{{ __('Camera.') }}</strong> {{ __('Barcode scanning runs in your browser. Camera images are not uploaded or recorded. You can deny or revoke camera permission at any time and enter a barcode by hand instead.') }}</p>
    <p><strong>{{ __('Voice.') }}</strong> {{ __('Reading aloud uses your device’s speech voices. Voices marked “online” are provided by your browser or operating system vendor and may send the text being read to that vendor.') }}</p>
    <p><strong>{{ __('Cookies and logs.') }}</strong> {{ __('We use a language cookie, a session cookie, and a security (CSRF) cookie needed for the site and team sign-in to work. We do not use advertising or analytics cookies. Like most websites, our server records technical logs (such as IP address, time, and page requested) to keep the service secure. Logs are kept only as long as needed for that purpose.') }}</p>
    <p><strong>{{ __('Team accounts.') }}</strong> {{ __('Catalog staff accounts store a name, email address, and a securely hashed password.') }}</p>
</section>
<section class="preference-section" id="terms">
    <h2>{{ __('Terms of use') }}</h2>
    <p><strong>{{ __('Not medical or dietary advice.') }}</strong> {{ __('AllerScan is a graduation project demo. Product and allergen records may be fictional, incomplete, or out of date. A product without a highlighted match, or with no recorded allergens, is not necessarily allergen-free. Always check the actual package label and manufacturer information, and consult a medical professional about your allergies.') }}</p>
    <p>{{ __('The service is provided “as is” and may change or stop without notice. We are not liable for decisions made using information shown here.') }}</p>
</section>
@if(config('demo.contact_email'))
<section class="preference-section" id="contact">
    <h2>{{ __('Contact') }}</h2>
    <p>{{ __('Questions about privacy or this service:') }} <a href="mailto:{{ config('demo.contact_email') }}">{{ config('demo.contact_email') }}</a></p>
</section>
@endif
</div>
</x-layout>
