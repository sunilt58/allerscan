<?php

namespace App\Http\Resources;

use App\Models\Suggestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Suggestion
 */
class SuggestionResource extends JsonResource
{
    /**
     * `status` is "pending", "approved", or "dismissed". `product_id` is set once an approved product is published.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'barcode' => $this->barcode,
            'name_ja' => $this->name_ja,
            'name_en' => $this->name_en,
            'size' => $this->size,
            'category' => $this->category,
            'allergen_codes' => $this->allergen_codes ?? [],
            'note' => $this->note,
            'product_id' => $this->product?->is_active ? $this->product_id : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
