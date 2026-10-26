<?php

namespace App\Jobs;

use App\Models\AiLookup;
use App\Services\ProductResearcher;
use App\Services\UnregisteredProductLookup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Reads the allergen statement from a shopper's photo of a package label with Gemini, saves it as the
 * product's "ai_draft" allergens, and deletes the photo. The label wins over what the web search found.
 */
class ReadProductLabel implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 15;

    /**
     * @param  string  $path  The photo on the private "local" disk.
     */
    public function __construct(public AiLookup $lookup, public string $path) {}

    public function handle(ProductResearcher $researcher, UnregisteredProductLookup $lookups): void
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($this->path)) {
            $this->lookup->update(['label_status' => 'failed']);

            return;
        }
        $label = $researcher->readLabel($disk->get($this->path), (string) $disk->mimeType($this->path));
        if ($label === null) {
            // The photo is kept for the retry and deleted in failed() after the last attempt.
            throw new RuntimeException('Gemini could not read the label photo.');
        }
        $disk->delete($this->path);
        if (! $label['is_label']) {
            $this->lookup->update(['label_status' => 'not_label']);

            return;
        }
        $lookups->saveDraft($this->lookup, [
            'name_ja' => $label['name_ja'],
            'name_en' => $label['name_ja'],
            'allergen_codes' => $label['allergen_codes'],
            'source' => 'AI推定（Gemini・ラベル写真）'.($label['statement'] !== '' ? ': '.$label['statement'] : ''),
        ]);
        $this->lookup->update(['label_status' => 'found']);
    }

    public function failed(?Throwable $exception): void
    {
        Storage::disk('local')->delete($this->path);
        $this->lookup->update(['label_status' => 'failed', 'error' => mb_substr((string) $exception?->getMessage(), 0, 255)]);
    }
}
