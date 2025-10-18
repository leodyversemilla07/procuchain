<?php

namespace Database\Factories;

use App\Models\Procurement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Procurement>
 */
class ProcurementFactory extends Factory
{
    protected $model = Procurement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'PR-'.fake()->year().'-'.fake()->numberBetween(1000, 9999).'-'.fake()->numberBetween(1000, 9999),
            'title' => fake()->sentence(6),
            'stage' => fake()->randomElement([
                'Procurement Initiation',
                'Pre-Procurement Conference',
                'Pre-Bid Conference',
                'Bidding Documents',
                'Bid Opening',
                'Bid Evaluation',
                'Post-Qualification',
                'BAC Resolution',
                'Notice of Award',
                'Performance Bond, Contract and PO',
                'Notice to Proceed',
                'Monitoring',
                'Completion',
            ]),
            'current_status' => fake()->randomElement([
                'Submitted',
                'Approved',
                'Conference Held',
                'Published',
                'Opened',
                'Evaluated',
                'Qualified',
                'Resolved',
                'Awarded',
                'Recorded',
                'Completed',
            ]),
            'user_address' => '1'.fake()->regexify('[A-Z0-9]{33}'),
            'document_count' => fake()->numberBetween(1, 50),
            'last_updated' => now(),
            'blockchain_txid' => fake()->optional(0.8)->sha256(),
            'blockchain_status' => fake()->randomElement(['pending', 'confirmed', 'failed']),
            'blockchain_status_updated_at' => fake()->optional(0.8)->dateTimeBetween('-1 month', 'now'),
            'blockchain_error' => fake()->optional(0.1)->sentence(),
            'blockchain_retry_count' => fake()->numberBetween(0, 5),
        ];
    }

    /**
     * Indicate that the procurement has pending blockchain status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'blockchain_status' => 'pending',
            'blockchain_txid' => null,
            'blockchain_error' => null,
            'blockchain_retry_count' => 0,
        ]);
    }

    /**
     * Indicate that the procurement has confirmed blockchain status.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'blockchain_status' => 'confirmed',
            'blockchain_txid' => fake()->sha256(),
            'blockchain_status_updated_at' => now(),
            'blockchain_error' => null,
        ]);
    }

    /**
     * Indicate that the procurement has failed blockchain status.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'blockchain_status' => 'failed',
            'blockchain_error' => fake()->sentence(),
            'blockchain_retry_count' => fake()->numberBetween(1, 5),
        ]);
    }
}
