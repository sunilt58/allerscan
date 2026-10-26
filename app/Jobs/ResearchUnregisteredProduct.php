<?php

namespace App\Jobs;

use App\Models\AiLookup;
use App\Services\OpenFoodFacts;
use App\Services\ProductResearcher;
use App\Services\UnregisteredProductLookup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Researches a scanned barcode that is not in the catalog: Gemini with Google Search first, then Open Food
 * Facts if Gemini can't identify it. A match is saved as an "ai_draft" product for the team to check.
 */
class ResearchUnregisteredProduct implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(public AiLookup $lookup) {}

    public function handle(ProductResearcher $researcher, OpenFoodFacts $openFoodFacts, UnregisteredProductLookup $lookups): void
    {
        if (! in_array($this->lookup->status, ['pending', 'searching'], true)) {
            return;
        }
        $this->lookup->update(['status' => 'searching', 'error' => null]);

        $found = $researcher->researchBarcode($this->lookup->barcode);
        if ($found['found'] ?? false) {
            $lookups->saveDraft($this->lookup, [
                ...$found,
                'source' => 'AI推定（Gemini・Web検索）'.($found['evidence'] !== '' ? ': '.$found['evidence'] : ''),
            ]);
            $this->lookup->update(['status' => 'found']);

            return;
        }

        $openFood = $openFoodFacts->lookup($this->lookup->barcode);
        if ($openFood['found'] ?? false) {
            $lookups->saveDraft($this->lookup, [
                'name_ja' => (string) ($openFood['name_ja'] ?: $openFood['name_en']),
                'name_en' => (string) ($openFood['name_en'] ?: $openFood['name_ja']),
                'size' => (string) $openFood['size'],
                'allergen_codes' => $openFood['allergen_codes'],
                'source' => 'AI推定（Open Food Facts の下書き）',
                'sources' => [['title' => 'Open Food Facts', 'url' => 'https://world.openfoodfacts.org/product/'.rawurlencode($this->lookup->barcode)]],
            ]);
            $this->lookup->update(['status' => 'found']);

            return;
        }

        // Both sources answered and neither knows the product; when neither answered, retry later.
        if ($found === null && $openFood === null) {
            throw new RuntimeException('Gemini and Open Food Facts could not be reached.');
        }
        $this->lookup->update(['status' => 'not_found']);
    }

    public function failed(?Throwable $exception): void
    {
        $this->lookup->update(['status' => 'failed', 'error' => mb_substr((string) $exception?->getMessage(), 0, 255)]);
    }
}
