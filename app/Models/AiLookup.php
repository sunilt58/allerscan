<?php

namespace App\Models;

use Database\Factories\AiLookupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An AI research request for a barcode that is not in the catalog yet.
 */
class AiLookup extends Model
{
    /** @use HasFactory<AiLookupFactory> */
    use HasFactory;

    protected $fillable = ['barcode', 'status', 'label_status', 'user_id', 'product_id', 'error'];

    /**
     * Whether the web research or a label photo is still being worked on.
     */
    public function isWorking(): bool
    {
        return in_array($this->status, ['pending', 'searching'], true) || $this->label_status === 'reading';
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
