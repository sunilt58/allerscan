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
     * Products shoppers can see; archived products are hidden everywhere.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Match a Japanese or English name, or an exact barcode.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeMatching(Builder $query, string $search): void
    {
        $query->where(function (Builder $query) use ($search) {
            $query->where('name_ja', 'like', '%'.$search.'%')
                ->orWhere('name_en', 'like', '%'.$search.'%')
                ->orWhere('barcode', $search);
        });
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
