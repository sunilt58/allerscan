<?php

namespace App\Services;

use App\Models\Allergen;

/**
 * Asks Gemini about an unregistered barcode, either by searching the web or by reading a photo of the
 * package's ingredients label. Everything it returns is an AI estimate: the caller saves it as an
 * "ai_draft" product for the team to check, never as a recorded one.
 */
class ProductResearcher
{
    private const CATEGORIES = ['Food', 'Drinks', 'Snacks', 'Daily'];

    public function __construct(private Gemini $gemini) {}

    /**
     * Null means Gemini could not be reached or answered in an unusable way.
     *
     * @return array{found: bool, name_ja: string, name_en: string, size: string, category: string, allergen_codes: list<string>, evidence: string, sources: list<array{title: string, url: string}>}|null
     */
    public function researchBarcode(string $barcode): ?array
    {
        $answer = $this->gemini->generate(
            (string) config('services.gemini.search_model'),
            [['text' => $this->searchPrompt($barcode)]],
            googleSearch: true,
        );
        $data = $answer ? Gemini::decodeJson($answer['text']) : null;
        if ($data === null) {
            return null;
        }
        $nameJa = $this->clean($data['name_ja'] ?? '', 255);
        $nameEn = $this->clean($data['name_en'] ?? '', 255);
        // The model can name a confident but wrong product, so it must quote the exact barcode it matched
        // and Google Search must have grounded the answer on at least one page.
        $matched = preg_replace('/\D/', '', (string) ($data['matched_barcode'] ?? '')) === preg_replace('/\D/', '', $barcode);
        $found = ($data['found'] ?? false) === true && ($nameJa !== '' || $nameEn !== '') && $matched && $answer['sources'] !== [];

        return [
            'found' => $found,
            'name_ja' => $nameJa !== '' ? $nameJa : $nameEn,
            'name_en' => $nameEn !== '' ? $nameEn : $nameJa,
            'size' => $this->clean($data['size'] ?? '', 100),
            'category' => in_array($data['category'] ?? null, self::CATEGORIES, true) ? $data['category'] : 'Food',
            'allergen_codes' => $this->allergenCodes($data['allergen_codes'] ?? []),
            'evidence' => $this->clean($data['evidence'] ?? '', 255),
            'sources' => $answer['sources'],
        ];
    }

    /**
     * Null means Gemini could not be reached or answered in an unusable way.
     *
     * @return array{is_label: bool, name_ja: string, allergen_codes: list<string>, statement: string}|null
     */
    public function readLabel(string $imageContents, string $mimeType): ?array
    {
        $answer = $this->gemini->generate(
            (string) config('services.gemini.label_model'),
            [
                ['inline_data' => ['mime_type' => $mimeType, 'data' => base64_encode($imageContents)]],
                ['text' => $this->labelPrompt()],
            ],
            json: true,
        );
        $data = $answer ? Gemini::decodeJson($answer['text']) : null;
        if ($data === null) {
            return null;
        }

        return [
            'is_label' => ($data['is_label'] ?? false) === true && ($data['readable'] ?? false) === true,
            'name_ja' => $this->clean($data['name_ja'] ?? '', 255),
            'allergen_codes' => $this->allergenCodes($data['allergen_codes'] ?? []),
            'statement' => $this->clean($data['statement'] ?? '', 255),
        ];
    }

    private function searchPrompt(string $barcode): string
    {
        return <<<PROMPT
        You help a Japanese allergen app. Use Google Search to find the retail product sold in Japan with the JAN/EAN barcode {$barcode}.
        Only accept a product when a page shows this exact barcode number, or the manufacturer's page clearly matches it.
        Then look for its allergen labeling (特定原材料 and 特定原材料に準ずるもの, for example 「一部に小麦・乳成分・大豆を含む」) on the manufacturer's page or a reliable retailer page.

        Allowed allergen codes: {$this->allergenList()}

        Reply with only one JSON object and nothing else:
        {"found": true, "matched_barcode": "the barcode exactly as shown on the page you used", "name_ja": "Japanese product name", "name_en": "English product name", "size": "e.g. 500ml", "category": "Food|Drinks|Snacks|Daily", "allergen_codes": ["wheat"], "evidence": "the allergen statement you found, quoted"}
        If no page shows this exact barcode for the product, or you cannot identify it with confidence, reply {"found": false}.
        Never guess allergens: list only allergens a page states for this product. If you found no allergen statement, use an empty list and say so in "evidence".
        PROMPT;
    }

    private function labelPrompt(): string
    {
        return <<<PROMPT
        This photo should show the ingredients label (原材料名) of a product sold in Japan.
        Read the allergen information printed on it: the statement in brackets such as 「（一部に小麦・乳成分・大豆を含む）」 and any allergens named in the ingredient list (乳成分 means milk).

        Allowed allergen codes: {$this->allergenList()}

        Reply with one JSON object:
        {"is_label": true, "readable": true, "name_ja": "product name if it is printed, else empty", "allergen_codes": ["wheat", "milk"], "statement": "the allergen text exactly as printed"}
        Use "is_label": false if the photo is not a product label, and "readable": false if the text can't be read clearly.
        List only allergens that are printed on the label. Do not guess.
        PROMPT;
    }

    /**
     * "milk=乳 (Milk), wheat=小麦 (Wheat), …" from the allergen table, so new allergens are included.
     */
    private function allergenList(): string
    {
        return Allergen::orderBy('id')->get(['code', 'name_ja', 'name_en'])
            ->map(fn (Allergen $allergen): string => "{$allergen->code}={$allergen->name_ja} ({$allergen->name_en})")
            ->implode(', ');
    }

    /**
     * Only codes on our allergen list; anything the model made up is dropped.
     *
     * @return list<string>
     */
    private function allergenCodes(mixed $codes): array
    {
        $known = Allergen::pluck('code')->all();

        return array_values(array_unique(array_filter(
            is_array($codes) ? $codes : [],
            fn ($code): bool => is_string($code) && in_array($code, $known, true),
        )));
    }

    private function clean(mixed $value, int $length): string
    {
        return is_string($value) ? mb_substr(trim($value), 0, $length) : '';
    }
}
