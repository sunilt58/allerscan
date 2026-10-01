<?php

namespace Database\Factories;

use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Suggestion>
 */
class SuggestionFactory extends Factory
{
    /**
     * A pending product suggestion.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'product',
            'status' => 'pending',
            'barcode' => fake()->unique()->ean13(),
            'name_ja' => 'おすすめの商品',
            'name_en' => 'Suggested product',
            'size' => '100g',
            'category' => 'Food',
            'allergen_codes' => ['milk'],
        ];
    }

    public function allergen(): static
    {
        return $this->state(fn (): array => [
            'type' => 'allergen', 'barcode' => null, 'size' => null, 'category' => null, 'allergen_codes' => null,
            'name_ja' => 'セロリ', 'name_en' => 'Celery',
        ]);
    }
}
