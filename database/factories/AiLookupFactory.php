<?php

namespace Database\Factories;

use App\Models\AiLookup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiLookup>
 */
class AiLookupFactory extends Factory
{
    /**
     * A lookup waiting for the research job.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barcode' => fake()->unique()->ean13(),
            'status' => 'pending',
        ];
    }

    /**
     * The research finished without finding the product.
     */
    public function notFound(): static
    {
        return $this->state(['status' => 'not_found']);
    }
}
