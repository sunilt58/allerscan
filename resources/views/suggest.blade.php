<x-layout :title="__('Suggest an addition')">
<div class="page-heading"><span class="section-kicker">{{ __('HELP US GROW') }}</span><h1>{{ __('Something missing') }}<span class="heading-dot">?</span></h1><p>{{ __('Tell us about a product we don’t have yet, or an allergen that isn’t on our list. Our team checks every suggestion before adding it.') }}</p></div>
@guest
<section class="preference-section">
    <h2>{{ __('Sign in to suggest.') }}</h2>
    <p>{{ __('Suggestions need a free account, so our team can keep the catalog trustworthy and you can follow what happens to yours.') }}</p>
    <div class="account-actions"><a class="button primary" href="{{ route('register') }}">{{ __('Create account') }}</a><a class="button secondary" href="{{ route('login') }}">{{ __('Sign in') }}</a></div>
</section>
@else
@error('type')<p role="alert" class="notice danger">{{ $message }}</p>@enderror
<div class="suggest-layout">
<section class="preference-section" id="suggest-product">
    <span class="section-kicker">{{ __('01 / A PRODUCT') }}</span>
    <h2>{{ __('Suggest a product') }}</h2>
    <p>{{ __('Copy the details from the package if you can. Only the barcode and one name are required.') }}</p>
    <form class="suggest-form" method="POST" action="{{ route('suggest.store') }}">
        @csrf
        <input type="hidden" name="type" value="product">
        @php($productErrors = old('type') === 'product')
        <label for="suggest-barcode">{{ __('Product barcode') }}</label>
        <input id="suggest-barcode" name="barcode" value="{{ old('barcode', $barcode) }}" required maxlength="50" pattern="[A-Za-z0-9\-]+" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="{{ __('e.g. 4901234567894') }}">
        @error('barcode')<p role="alert" class="error-text">{{ $message }}</p>@enderror
        <div class="suggest-pair">
            <div><label for="suggest-name-ja">{{ __('Japanese name') }}</label><input id="suggest-name-ja" name="name_ja" value="{{ $productErrors ? old('name_ja') : '' }}" maxlength="255" lang="ja"></div>
            <div><label for="suggest-name-en">{{ __('English name') }}</label><input id="suggest-name-en" name="name_en" value="{{ $productErrors ? old('name_en') : '' }}" maxlength="255" lang="en"></div>
        </div>
        @if($productErrors) @error('name_ja')<p role="alert" class="error-text">{{ $message }}</p>@enderror @endif
        <div class="suggest-pair">
            <div><label for="suggest-size">{{ __('Size') }}</label><input id="suggest-size" name="size" value="{{ old('size') }}" maxlength="100" placeholder="{{ __('e.g. 120g') }}"></div>
            <div><label for="suggest-category">{{ __('Category') }}</label><select id="suggest-category" name="category"><option value="">{{ __('Not sure') }}</option>@foreach(['Food', 'Drinks', 'Snacks', 'Daily'] as $category)<option value="{{ $category }}" @selected(old('category') === $category)>{{ __($category) }}</option>@endforeach</select></div>
        </div>
        <fieldset class="suggest-allergens"><legend>{{ __('Allergens shown on the package (optional)') }}</legend>
            @foreach($allergens as $allergen)<label><input type="checkbox" name="allergen_codes[]" value="{{ $allergen->code }}" @checked(in_array($allergen->code, old('allergen_codes', []), true))>{{ $allergen->name }}</label>@endforeach
        </fieldset>
        <label for="suggest-product-note">{{ __('Anything else we should know? (optional)') }}</label>
        <textarea id="suggest-product-note" name="note" maxlength="500" rows="3">{{ $productErrors ? old('note') : '' }}</textarea>
        <button class="button primary" type="submit">{{ __('Send product suggestion') }} <x-icon name="arrow"/></button>
    </form>
</section>
<section class="preference-section" id="suggest-allergen">
    <span class="section-kicker">{{ __('02 / AN ALLERGEN') }}</span>
    <h2>{{ __('Suggest an allergen') }}</h2>
    <p>{{ __('Is an allergen you look for missing from our list? Tell us its name in either language.') }}</p>
    <form class="suggest-form" method="POST" action="{{ route('suggest.store') }}">
        @csrf
        <input type="hidden" name="type" value="allergen">
        @php($allergenErrors = old('type') === 'allergen')
        <label for="suggest-allergen-ja">{{ __('Japanese name') }}</label>
        <input id="suggest-allergen-ja" name="name_ja" value="{{ $allergenErrors ? old('name_ja') : '' }}" maxlength="100" lang="ja">
        <label for="suggest-allergen-en">{{ __('English name') }}</label>
        <input id="suggest-allergen-en" name="name_en" value="{{ $allergenErrors ? old('name_en') : '' }}" maxlength="100" lang="en">
        @if($allergenErrors) @error('name_ja')<p role="alert" class="error-text">{{ $message }}</p>@enderror @endif
        <label for="suggest-allergen-note">{{ __('Anything else we should know? (optional)') }}</label>
        <textarea id="suggest-allergen-note" name="note" maxlength="500" rows="3">{{ $allergenErrors ? old('note') : '' }}</textarea>
        <button class="button primary" type="submit">{{ __('Send allergen suggestion') }} <x-icon name="arrow"/></button>
    </form>
</section>
</div>
<section class="preference-section" id="my-suggestions">
    <span class="section-kicker">{{ __('03 / WHAT HAPPENED NEXT') }}</span>
    <h2>{{ __('Your suggestions') }}</h2>
    @forelse($suggestions as $suggestion)
        <div class="my-suggestion">
            <div><strong>{{ $suggestion->name }}</strong><small>{{ $suggestion->type === 'allergen' ? __('New allergen') : $suggestion->barcode }} · {{ $suggestion->created_at->format('Y/m/d') }}</small></div>
            @if($suggestion->status === 'approved')
                <span class="suggestion-status approved">{{ __('Added') }}</span>
                @if($suggestion->product?->is_active)<a class="text-link" href="{{ route('catalog.show', $suggestion->product) }}">{{ __('View details') }}</a>@endif
            @elseif($suggestion->status === 'dismissed')
                <span class="suggestion-status">{{ __('Not added') }}</span>
            @else
                <span class="suggestion-status waiting">{{ __('Waiting for review') }}</span>
            @endif
        </div>
    @empty
        <p>{{ __('You haven’t suggested anything yet.') }}</p>
    @endforelse
</section>
@endguest
</x-layout>
