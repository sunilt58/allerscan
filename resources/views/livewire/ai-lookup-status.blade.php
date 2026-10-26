<div class="ai-status" @if($lookup->isWorking()) wire:poll.3s @endif role="status" aria-live="polite">
    @if($lookup->label_status === 'reading')
        <span class="ai-spinner" aria-hidden="true"></span><div><strong>{{ __('AI is reading the label photo…') }}</strong><p>{{ __('This usually takes less than a minute. The page moves on by itself.') }}</p></div>
    @elseif($lookup->isWorking())
        <span class="ai-spinner" aria-hidden="true"></span><div><strong>{{ __('AI is looking up this product…') }}</strong><p>{{ __('This usually takes less than a minute. The page moves on by itself.') }}</p></div>
    @elseif($lookup->label_status === 'not_label')
        <x-icon name="info"/><div><strong>{{ __('The label couldn’t be read.') }}</strong><p>{{ __('Take the photo again so the ingredients and allergen text fill the frame and are in focus.') }}</p></div>
    @elseif($lookup->label_status === 'failed' || $lookup->status === 'failed')
        <x-icon name="info"/><div><strong>{{ __('AI research didn’t work this time.') }}</strong><p>{{ __('Please try again later, or check the package label yourself.') }}</p></div>
    @elseif($lookup->product)
        <x-icon name="info"/><div><strong>{{ __('AI found this product.') }}</strong><p><a class="text-link" href="{{ route('catalog.show', $lookup->product) }}">{{ __('View the AI estimate') }} ↗</a></p></div>
    @else
        <x-icon name="info"/><div><strong>{{ __('AI couldn’t find this product on the web.') }}</strong><p>{{ __('A photo of the ingredients label lets AI read its allergens.') }}</p></div>
    @endif
</div>
