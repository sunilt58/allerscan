<?php

namespace App\Services;

use App\Models\Allergen;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Looks up a barcode in Open Food Facts (https://world.openfoodfacts.org), a crowd-sourced database
 * licensed under the ODbL. Results are only drafts for the team to check against the package label.
 */
class OpenFoodFacts
{
    /**
     * Open Food Facts tags that mean exactly one allergen on our list. Broader tags such as
     * "gluten" (wheat or barley) or "crustaceans" (shrimp or crab) are left for the team to decide.
     *
     * @var array<string, string>
     */
    private const TAG_TO_CODE = [
        'milk' => 'milk',
        'eggs' => 'egg',
        'peanuts' => 'peanut',
        'soybeans' => 'soy',
        'sesame-seeds' => 'sesame',
        'buckwheat' => 'buckwheat',
        'walnuts' => 'walnut',
        'almonds' => 'almond',
        'cashew-nuts' => 'cashew',
        'pistachio-nuts' => 'pistachio',
        'macadamia-nuts' => 'macadamia',
    ];

    /**
     * Null means Open Food Facts could not be reached.
     *
     * @return array{found: bool, name_ja: ?string, name_en: ?string, size: ?string, allergen_codes: list<string>, other_allergens: list<string>, traces: list<string>}|null
     */
    public function lookup(string $barcode): ?array
    {
        try {
            $response = Http::withUserAgent('AllerScan/1.0 ('.config('app.url').')')
                ->acceptJson()->timeout(8)
                ->get('https://world.openfoodfacts.org/api/v2/product/'.rawurlencode($barcode).'.json', [
                    'fields' => 'product_name,product_name_ja,product_name_en,quantity,allergens_tags,traces_tags',
                ]);
        } catch (ConnectionException) {
            return null;
        }
        if ($response->status() === 404 || ($response->ok() && (int) $response->json('status') === 0)) {
            return ['found' => false, 'name_ja' => null, 'name_en' => null, 'size' => null, 'allergen_codes' => [], 'other_allergens' => [], 'traces' => []];
        }
        if (! $response->ok()) {
            return null;
        }
        $product = (array) $response->json('product', []);
        [$codes, $others] = $this->mapAllergens((array) ($product['allergens_tags'] ?? []));
        [$generic, $japanese] = [trim((string) ($product['product_name'] ?? '')), trim((string) ($product['product_name_ja'] ?? ''))];
        $english = trim((string) ($product['product_name_en'] ?? ''));
        $genericIsJapanese = (bool) preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}]/u', $generic);

        return [
            'found' => true,
            'name_ja' => $japanese !== '' ? $japanese : ($genericIsJapanese ? $generic : null),
            'name_en' => $english !== '' ? $english : (! $genericIsJapanese && $generic !== '' ? $generic : null),
            'size' => trim((string) ($product['quantity'] ?? '')) ?: null,
            'allergen_codes' => $codes,
            'other_allergens' => $others,
            'traces' => array_map($this->label(...), (array) ($product['traces_tags'] ?? [])),
        ];
    }

    /**
     * Split Open Food Facts allergen tags into codes on our list and readable leftovers.
     *
     * @param  list<string>  $tags
     * @return array{0: list<string>, 1: list<string>}
     */
    private function mapAllergens(array $tags): array
    {
        $allergens = Allergen::all(['code', 'name_ja', 'name_en']);
        $codes = [];
        $others = [];
        foreach ($tags as $tag) {
            $value = $this->label($tag);
            $code = self::TAG_TO_CODE[mb_strtolower($value)] ?? $allergens->first(fn (Allergen $allergen): bool => in_array(
                mb_strtolower(str_replace('-', ' ', $value)),
                [$allergen->code, mb_strtolower($allergen->name_en), $allergen->name_ja],
                true,
            ))?->code;
            if ($code) {
                $codes[] = $code;
            } else {
                $others[] = $value;
            }
        }

        return [array_values(array_unique($codes)), array_values(array_unique($others))];
    }

    /**
     * "en:sesame-seeds" becomes "sesame-seeds".
     */
    private function label(string $tag): string
    {
        return str_contains($tag, ':') ? substr($tag, strpos($tag, ':') + 1) : $tag;
    }
}
