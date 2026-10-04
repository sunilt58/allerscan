<?php

namespace App\Http\Resources;

use App\Models\Allergen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Allergen
 */
class AllergenResource extends JsonResource
{
    /**
     * @return array{code: string, name_ja: string, name_en: string}
     */
    public function toArray(Request $request): array
    {
        return ['code' => $this->code, 'name_ja' => $this->name_ja, 'name_en' => $this->name_en];
    }
}
