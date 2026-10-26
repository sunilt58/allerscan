<x-layout :title="__('Looking up a new product')">
<div class="page-heading"><span class="section-kicker">{{ __('NOT IN OUR CATALOG YET') }}</span><h1>{{ __('Looking it up for you') }}<span class="heading-dot">.</span></h1><p>{{ __('Barcode') }}: <span class="mono">{{ $lookup->barcode }}</span></p></div>
<div class="ai-pending">
    <livewire:ai-lookup-status :barcode="$lookup->barcode" :adding-label="$addingLabel"/>
    <p class="notice warning" role="note">{{ __('Information found by AI is an estimate. Our team checks it later; until then, always check the actual package label.') }}</p>
    @if(! $lookup->isWorking() && (! $lookup->product || $lookup->product->isAiDraft()))
        <section class="ai-label">
            <h2>{{ __('Read the label with AI') }}</h2>
            @auth
                <p>{{ __('Take a photo of the ingredients (原材料名) and allergen text on the package.') }}</p>
                <form method="POST" action="{{ route('scan.label', ['barcode' => $lookup->barcode]) }}" enctype="multipart/form-data">
                    @csrf
                    <label for="label-photo">{{ __('Ingredients label photo') }}</label>
                    <input id="label-photo" name="photo" type="file" accept="image/*" capture="environment" required aria-describedby="label-photo-help {{ $errors->has('photo') ? 'label-photo-error' : '' }}">
                    @error('photo')<p class="error-text" id="label-photo-error" role="alert">{{ $message }}</p>@enderror
                    <p id="label-photo-help" class="small muted">{{ __('Only the label, please: no people or personal information. The photo is sent to Google Gemini to be read and deleted afterwards.') }}</p>
                    <button class="button primary" type="submit"><x-icon name="camera"/>{{ __('Send the label photo') }}</button>
                </form>
            @else
                <p>{{ __('Sign in to send a photo of the ingredients label for AI to read.') }}</p>
                <a class="button secondary" href="{{ route('login') }}">{{ __('Sign in') }}</a>
            @endauth
        </section>
    @endif
    <p><a class="text-link" href="{{ route('discover') }}#catalog">{{ __('Or search by product name') }} ↗</a>@can('manage-catalog') <a class="text-link" href="{{ route('products', ['barcode' => $lookup->barcode]) }}">{{ __('Register this product') }} ↗</a>@endcan</p>
</div>
</x-layout>
