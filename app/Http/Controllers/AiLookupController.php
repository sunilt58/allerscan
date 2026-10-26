<?php

namespace App\Http\Controllers;

use App\Jobs\ReadProductLabel;
use App\Models\AiLookup;
use App\Services\UnregisteredProductLookup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AiLookupController extends Controller
{
    /**
     * The "AI is looking this up" page for a barcode that is not in the catalog. It moves on to the
     * product by itself once a draft is ready, unless the shopper came to add a label photo.
     */
    public function show(Request $request, string $barcode): View|RedirectResponse
    {
        $lookup = AiLookup::with('product')->where('barcode', $barcode)->first();
        if (! $lookup) {
            return redirect()->route('scan');
        }
        $addingLabel = $request->boolean('label');
        if (! $addingLabel && ! $lookup->isWorking() && $lookup->product?->is_active) {
            return redirect()->route('catalog.show', $lookup->product);
        }

        return view('scan-pending', ['lookup' => $lookup, 'addingLabel' => $addingLabel]);
    }

    /**
     * A signed-in shopper's photo of the ingredients label, read by AI in the background. Products the team
     * has already checked are not changed.
     */
    public function storeLabel(Request $request, string $barcode, UnregisteredProductLookup $lookups): RedirectResponse
    {
        $lookup = AiLookup::with('product')->where('barcode', $barcode)->firstOrFail();
        $request->validate([
            // HEIC is what iPhones save; SVG is not accepted.
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:8192'],
        ]);
        $back = redirect()->route('scan.pending', ['barcode' => $barcode]);
        if ($lookup->product && ! $lookup->product->isAiDraft()) {
            return redirect()->route('catalog.show', $lookup->product);
        }
        if ($lookup->label_status === 'reading') {
            return $back;
        }
        if (! $lookups->isAvailable() || ! $lookups->takeDailyAllowance($request)) {
            return $back->withErrors(['photo' => __('AI research has reached today’s limit. Please try again tomorrow.')]);
        }
        $path = $request->file('photo')->store('label-photos', 'local');
        $lookup->update(['label_status' => 'reading', 'error' => null]);
        ReadProductLabel::dispatch($lookup, $path);

        return $back;
    }
}
