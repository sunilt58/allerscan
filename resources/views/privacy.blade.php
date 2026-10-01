<x-layout :title="__('Privacy & terms')">
<div class="page-heading"><span class="section-kicker">{{ __('PLAIN AND SIMPLE') }}</span><h1>{{ __('Privacy & terms') }}<span class="heading-dot">.</span></h1><p>{{ __('What AllerScan keeps, and what it doesn’t.') }}</p></div>
<div class="preferences-sections">
<section class="preference-section" id="privacy">
    <h2>{{ __('Your information') }}</h2>
    <p><strong>{{ __('No account needed.') }}</strong> {{ __('You can browse, scan, and save products without signing up. Without an account, your chosen allergens and saved products are stored only in this browser and are not sent to our database. Clearing your browser data removes them.') }}</p>
    <p><strong>{{ __('Optional accounts.') }}</strong> {{ __('If you create an account, we store your name, email address, a securely hashed password, the allergens you select, and the products you save, so they are available on every device you sign in on. Your allergen selections may reveal health information; we use them only to highlight matches for you and never share or sell them. We ask for your agreement when you create the account.') }}</p>
    <p><strong>{{ __('Suggestions.') }}</strong> {{ __('If you suggest a product or allergen, we store what you entered with your account so our team can review it and show you the result. Suggestions are deleted when you delete your account.') }}</p>
    <p><strong>{{ __('Deleting your account.') }}</strong> {{ __('You can delete your account at any time in Settings. This permanently removes your account, your selected allergens, and your saved products from our database.') }}</p>
    <p><strong>{{ __('This browser.') }}</strong> {{ __('Theme, text size, and voice choices always stay in this browser. When you sign out, your allergens and saved products are removed from this browser and stay in your account.') }}</p>
    <p><strong>{{ __('Saved products.') }}</strong> {{ __('When you open Saved, your browser sends the saved product numbers to our server so it can return current product information.') }}</p>
    <p><strong>{{ __('Camera.') }}</strong> {{ __('Barcode scanning runs in your browser. Camera images are not uploaded or recorded. You can deny or revoke camera permission at any time and enter a barcode by hand instead.') }}</p>
    <p><strong>{{ __('Voice.') }}</strong> {{ __('Reading aloud uses your device’s speech voices. Voices marked “online” are provided by your browser or operating system vendor and may send the text being read to that vendor.') }}</p>
    <p><strong>{{ __('Cookies and logs.') }}</strong> {{ __('We use a language cookie, a session cookie, a sign-in cookie if you choose to stay signed in, and a security (CSRF) cookie. We do not use advertising or analytics cookies. Like most websites, our server records technical logs (such as IP address, time, and page requested) to keep the service secure. Logs are kept only as long as needed for that purpose.') }}</p>
</section>
<section class="preference-section" id="terms">
    <h2>{{ __('Terms of use') }}</h2>
    <p><strong>{{ __('Not medical or dietary advice.') }}</strong> {{ __('AllerScan shows allergen information that our team recorded from product packaging. Records may be incomplete or out of date, and manufacturers change ingredients. A product without a highlighted match, or with no recorded allergens, is not necessarily allergen-free. Always check the actual package label and manufacturer information, and consult a medical professional about your allergies.') }}</p>
    <p>{{ __('The service is provided “as is” and may change or stop without notice. We are not liable for decisions made using information shown here.') }}</p>
</section>
@if(config('allerscan.contact_email'))
<section class="preference-section" id="contact">
    <h2>{{ __('Contact') }}</h2>
    <p>{{ __('Questions about privacy or this service:') }} <a href="mailto:{{ config('allerscan.contact_email') }}">{{ config('allerscan.contact_email') }}</a></p>
</section>
@endif
</div>
</x-layout>
