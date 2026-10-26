<?php

namespace App\Services;

use App\Jobs\ResearchUnregisteredProduct;
use App\Models\AiLookup;
use App\Models\Allergen;
use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Starts AI research for barcodes that are not in the catalog, within the daily limits, and saves what it
 * finds as "ai_draft" products. A product the team has already recorded is never overwritten.
 */
class UnregisteredProductLookup
{
    private const ICONS = ['Food' => '📦', 'Drinks' => '🧃', 'Snacks' => '🍪', 'Daily' => '🧴'];

    public function __construct(private Gemini $gemini) {}

    public function isAvailable(): bool
    {
        return (bool) config('allerscan.ai.enabled') && $this->gemini->isConfigured();
    }

    /**
     * The barcode's lookup, started now if there is none yet and the limits allow it. Null means AI
     * research is off or today's limit for this person or for everyone has been reached.
     */
    public function lookupFor(string $barcode, Request $request): ?AiLookup
    {
        if (! $this->isAvailable() || ! static::isRetailBarcode($barcode)) {
            return null;
        }
        $existing = AiLookup::where('barcode', $barcode)->first();
        if ($existing) {
            return $existing;
        }
        if (! $this->takeDailyAllowance($request)) {
            return null;
        }
        try {
            $lookup = AiLookup::create(['barcode' => $barcode, 'user_id' => $request->user()?->id]);
        } catch (UniqueConstraintViolationException) {
            // Two scans of the same new barcode at once: the other request started the research.
            return AiLookup::where('barcode', $barcode)->first();
        }
        ResearchUnregisteredProduct::dispatch($lookup);

        return $lookup;
    }

    /**
     * A JAN/EAN-13, EAN-8 or UPC-A code with a valid check digit: only these name a product to look up.
     */
    public static function isRetailBarcode(string $barcode): bool
    {
        if (! preg_match('/^(\d{8}|\d{12}|\d{13})$/', $barcode)) {
            return false;
        }
        $digits = array_map('intval', str_split(str_pad($barcode, 13, '0', STR_PAD_LEFT)));
        $sum = 0;
        foreach (array_slice($digits, 0, 12) as $index => $digit) {
            $sum += $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10 === $digits[12];
    }

    /**
     * Counts one AI request against the person's and everyone's daily limit.
     */
    public function takeDailyAllowance(Request $request): bool
    {
        $person = 'ai-lookup:'.($request->user() ? 'user:'.$request->user()->id : 'ip:'.hash('sha256', (string) $request->ip()));
        if (RateLimiter::tooManyAttempts($person, (int) config('allerscan.ai.per_person_daily_limit'))
            || RateLimiter::tooManyAttempts('ai-lookup:everyone', (int) config('allerscan.ai.daily_limit'))) {
            return false;
        }
        RateLimiter::hit($person, 86400);
        RateLimiter::hit('ai-lookup:everyone', 86400);

        return true;
    }

    /**
     * Saves an AI estimate as an active "ai_draft" product, or updates the lookup's existing draft.
     *
     * @param  array{name_ja?: string, name_en?: string, size?: string, category?: string, allergen_codes: list<string>, source: string, sources?: list<array{title: string, url: string}>}  $draft
     */
    public function saveDraft(AiLookup $lookup, array $draft): Product
    {
        return DB::transaction(function () use ($lookup, $draft): Product {
            $product = Product::lockForUpdate()->where('barcode', $lookup->barcode)->first();
            if ($product && ! $product->isAiDraft()) {
                $lookup->update(['product_id' => $product->id]);

                return $product;
            }
            $product ??= new Product(['barcode' => $lookup->barcode, 'price' => 0, 'tax_rate' => 8, 'is_demo' => false]);
            $category = $draft['category'] ?? $product->category ?? 'Food';
            $product->fill([
                'name_ja' => filled($draft['name_ja'] ?? null) ? $draft['name_ja'] : ($product->name_ja ?? "商品（{$lookup->barcode}）"),
                'name_en' => filled($draft['name_en'] ?? null) ? $draft['name_en'] : ($product->name_en ?? "Product {$lookup->barcode}"),
                'size' => filled($draft['size'] ?? null) ? $draft['size'] : ($product->size ?? ''),
                'category' => $category,
                'icon' => $product->exists ? $product->icon : self::ICONS[$category],
                'information_status' => 'ai_draft',
                'source' => mb_substr($draft['source'], 0, 255),
                'ai_sources' => $draft['sources'] ?? $product->ai_sources,
                'verified_at' => null,
                'is_active' => true,
            ]);
            $product->save();
            $product->allergens()->sync(Allergen::whereIn('code', $draft['allergen_codes'])->pluck('id'));
            $lookup->update(['product_id' => $product->id]);

            return $product;
        });
    }
}
