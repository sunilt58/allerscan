<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * Both language names are included so the app can switch language without another request.
     * `information_status` is "recorded" or "unknown": an unknown record must never be shown as allergen-free.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'barcode' => $this->barcode,
            'name_ja' => $this->name_ja,
            'name_en' => $this->name_en,
            'size' => $this->size,
            'category' => $this->category,
            'icon' => $this->icon,
            'information_status' => $this->information_status,
            'allergens' => AllergenResource::collection($this->whenLoaded('allergens')),
            'source' => $this->source,
            'verified_at' => $this->verified_at?->toDateString(),
            'is_sample' => $this->is_demo,
            'url' => route('catalog.show', $this->resource),
        ];
    }
}
