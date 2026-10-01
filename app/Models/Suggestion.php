<?php

namespace App\Models;

use Database\Factories\SuggestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Suggestion extends Model
{
    /** @use HasFactory<SuggestionFactory> */
    use HasFactory;

    public const PENDING_LIMIT_PER_USER = 20;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['allergen_codes' => 'array', 'reviewed_at' => 'datetime'];
    }

    /**
     * The suggested name in the current display language, falling back to the other one.
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => (string) (app()->getLocale() === 'en'
            ? ($this->name_en ?: $this->name_ja)
            : ($this->name_ja ?: $this->name_en)));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @param  Builder<Suggestion>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    public function markReviewed(string $status, User $reviewer, ?Product $product = null): void
    {
        $this->update([
            'status' => $status, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'product_id' => $product?->id,
        ]);
    }
}
