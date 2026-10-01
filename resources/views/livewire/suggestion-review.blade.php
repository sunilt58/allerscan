<div>
    <div class="page-intro"><div><h2>{{ __('What shoppers are asking for.') }}</h2><p>{{ __('Review each suggestion against the real package label. Nothing a shopper suggests is shown publicly until you add it.') }}</p></div></div>
    @if(session('message'))<div class="notice" role="status">{{ session('message') }}</div>@endif
    <div class="panel">
        <div class="table-toolbar"><h3>{{ __('Waiting for review') }} <span class="count-tag">{{ $pending->count() }}</span></h3></div>
        @forelse($pending as $suggestion)
            <article class="suggestion-row" wire:key="pending-{{ $suggestion->id }}">
                <div class="suggestion-body">
                    <span class="badge {{ $suggestion->type === 'allergen' ? 'amber' : '' }}">{{ $suggestion->type === 'allergen' ? __('New allergen') : __('New product') }}</span>
                    <h4>{{ $suggestion->name_ja ?: '—' }} <small>{{ $suggestion->name_en }}</small></h4>
                    @if($suggestion->type === 'product')
                        <p class="mono">{{ $suggestion->barcode }}@if($suggestion->size) · {{ $suggestion->size }}@endif @if($suggestion->category) · {{ __($suggestion->category) }}@endif</p>
                        <p class="muted">{{ __('Suggested allergens (unverified):') }} @forelse($suggestion->allergen_codes ?? [] as $code)<span class="badge">{{ $allergenNames[$code]->name ?? $code }}</span>@empty{{ __('None given') }}@endforelse</p>
                    @endif
                    @if($suggestion->note)<p class="suggestion-note">“{{ $suggestion->note }}”</p>@endif
                    <small class="muted">{{ __('From :name · :date', ['name' => $suggestion->user->name, 'date' => $suggestion->created_at->format('Y/m/d H:i')]) }}</small>
                </div>
                <div class="table-actions">
                    @if($suggestion->type === 'product')
                        <a class="button primary" href="{{ route('products', ['suggestion' => $suggestion->id]) }}">{{ __('Check and add product') }}</a>
                    @else
                        <button type="button" class="button primary" wire:click="startAllergen({{ $suggestion->id }})">{{ __('Add to allergen list') }}</button>
                    @endif
                    <button type="button" class="text-button" wire:click="dismiss({{ $suggestion->id }})" wire:confirm="{{ __('Dismiss this suggestion?') }}">{{ __('Dismiss') }}</button>
                </div>
                @if($allergenSuggestionId === $suggestion->id)
                    <form class="allergen-form" wire:submit="addAllergen">
                        @if($errors->any())<div class="notice danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                        <div class="form-grid">
                            <label>{{ __('Code') }}<input wire:model="allergenForm.code" required maxlength="50" pattern="[a-z][a-z0-9_]*" autocapitalize="off" spellcheck="false"></label>
                            <label>{{ __('Japanese name') }}<input wire:model="allergenForm.name_ja" required maxlength="100" lang="ja"></label>
                            <label>{{ __('English name') }}<input wire:model="allergenForm.name_en" required maxlength="100" lang="en"></label>
                        </div>
                        <p class="small muted">{{ __('The code is a short lowercase id such as “celery”. Shoppers will be able to select this allergen, and you can record it on products.') }}</p>
                        <div class="modal-actions"><button type="button" class="button secondary" wire:click="$set('allergenSuggestionId', null)">{{ __('Cancel') }}</button><button type="submit" class="button primary" wire:loading.attr="disabled">{{ __('Add allergen') }}</button></div>
                    </form>
                @endif
            </article>
        @empty
            <p class="empty-state">{{ __('Nothing waiting. New suggestions from shoppers will appear here.') }}</p>
        @endforelse
    </div>
    @if($reviewed->isNotEmpty())
    <div class="panel">
        <div class="table-toolbar"><h3>{{ __('Recently reviewed') }}</h3></div>
        <div class="table-scroll"><table><thead><tr><th>{{ __('Suggestion') }}</th><th>{{ __('From') }}</th><th>{{ __('Result') }}</th><th>{{ __('Reviewed') }}</th></tr></thead><tbody>
        @foreach($reviewed as $suggestion)
            <tr wire:key="reviewed-{{ $suggestion->id }}"><td><strong>{{ $suggestion->name }}</strong> <small class="muted">{{ $suggestion->type === 'allergen' ? __('New allergen') : $suggestion->barcode }}</small></td><td>{{ $suggestion->user->name }}</td><td><span class="status-pill {{ $suggestion->status === 'approved' ? '' : 'inactive' }}"><span></span>{{ $suggestion->status === 'approved' ? __('Added') : __('Dismissed') }}</span></td><td>{{ $suggestion->reviewed_at?->format('Y/m/d') }}</td></tr>
        @endforeach
        </tbody></table></div>
    </div>
    @endif
</div>
