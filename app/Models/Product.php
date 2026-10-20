<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['price' => 'integer', 'tax_rate' => 'integer', 'is_demo' => 'boolean', 'is_active' => 'boolean', 'verified_at' => 'date'];
    }

    /**
     * The product name in the current display language.
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => app()->getLocale() === 'en' ? $this->name_en : $this->name_ja);
    }

    /**
     * The product name in the other language, shown as a secondary line.
     *
     * @return Attribute<array{name: string, lang: string}, never>
     */
    protected function alternateName(): Attribute
    {
        return Attribute::get(fn (): array => app()->getLocale() === 'en'
            ? ['name' => $this->name_ja, 'lang' => 'ja']
            : ['name' => $this->name_en, 'lang' => 'en']);
    }

    /**
     * The product photo's address, relative to the current host so it also works on the local network.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->image_path ? asset('storage/'.$this->image_path) : null);
    }

    /**
     * Products shoppers can see; archived products are hidden everywhere.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            $product->search_text = static::searchTextFor($product->name_ja, $product->name_en, $product->keywords, $product->barcode);
        });
    }

    /**
     * Writes text the same way however it was typed: hiragana as katakana, half-width kana and
     * full-width letters and digits as their usual width, lower case, and without spaces, symbols or ー.
     */
    public static function normalizeForSearch(string $text): string
    {
        $text = mb_strtolower(mb_convert_kana($text, 'KVasC'));

        return preg_replace('/[\s\p{P}\p{S}ー]+/u', '', $text) ?? '';
    }

    /**
     * The stored search key for a product's names, search keywords and barcode.
     */
    public static function searchTextFor(?string $nameJa, ?string $nameEn, ?string $keywords, ?string $barcode): string
    {
        return static::normalizeForSearch(implode(' ', array_filter([$nameJa, $nameEn, $keywords, $barcode])));
    }

    /**
     * Match products whose names, keywords or barcode contain every word of the search, in any order and
     * however each word is written (see normalizeForSearch).
     *
     * @param  Builder<Product>  $query
     */
    public function scopeMatching(Builder $query, string $search): void
    {
        $words = array_filter(array_map(
            static::normalizeForSearch(...),
            preg_split('/[\s　]+/u', $search) ?: [],
        ));
        foreach ($words as $word) {
            $query->where('search_text', 'like', '%'.$word.'%');
        }
    }

    public function allergens(): BelongsToMany
    {
        return $this->belongsToMany(Allergen::class)->orderBy('allergens.id');
    }

    public function displayData(): array
    {
        return $this->only(['id', 'barcode', 'name_ja', 'name_en', 'size', 'category', 'icon', 'price', 'tax_rate', 'information_status', 'is_demo', 'is_active']) + [
            'allergens' => $this->allergens->map->only(['code', 'name_ja', 'name_en'])->values()->all(),
            'source' => $this->source,
            'verified_at' => $this->verified_at?->format('Y-m-d'),
        ];
    }
}
