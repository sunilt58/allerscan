<?php

namespace App\Livewire;

use App\Models\AiLookup;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Shows the progress of AI research for an unregistered barcode, checking every few seconds while it
 * runs, and opens the product page once a draft is ready.
 */
class AiLookupStatus extends Component
{
    #[Locked]
    public string $barcode = '';

    #[Locked]
    public bool $addingLabel = false;

    public function render(): View
    {
        $lookup = AiLookup::with('product')->where('barcode', $this->barcode)->firstOrFail();
        if (! $lookup->isWorking() && $lookup->product?->is_active && ($lookup->label_status === 'found' || ! $this->addingLabel)) {
            $this->redirectRoute('catalog.show', $lookup->product);
        }

        return view('livewire.ai-lookup-status', ['lookup' => $lookup]);
    }
}
