<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentView>
 */
class DocumentViewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'file_key' => fake()->uuid(),
            'procurement_id' => fake()->randomNumber(5),
            'procurement_title' => fake()->sentence(),
            'document_type' => fake()->randomElement(['specification', 'proposal', 'contract', 'report']),
            'stage' => fake()->randomElement(['submission', 'evaluation', 'award', 'completion']),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'view_duration' => fake()->numberBetween(0, 3600),
            'metadata' => [],
            'viewed_at' => now(),
        ];
    }
}
