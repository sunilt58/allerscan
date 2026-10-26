<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * An active product whose allergens the team has recorded from the package label.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barcode' => fake()->unique()->ean13(),
            'name_ja' => 'テスト商品',
            'name_en' => 'Test product',
            'size' => '100g',
            'category' => 'Food',
            'icon' => '📦',
            'price' => 0,
            'tax_rate' => 8,
            'information_status' => 'recorded',
            'source' => 'Package label',
            'verified_at' => now()->toDateString(),
            'is_demo' => false,
            'is_active' => true,
        ];
    }

    /**
     * Registered by AI and waiting for the team's check.
     */
    public function aiDraft(): static
    {
        return $this->state(['information_status' => 'ai_draft', 'source' => 'AI推定（Gemini・Web検索）', 'verified_at' => null]);
    }
}
