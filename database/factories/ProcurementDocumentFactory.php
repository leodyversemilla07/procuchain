<?php

namespace Database\Factories;

use App\Models\Procurement;
use App\Models\ProcurementDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProcurementDocument>
 */
class ProcurementDocumentFactory extends Factory
{
    protected $model = ProcurementDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'procurement_id' => Procurement::factory(),
            'file_key' => fake()->uuid().'/'.fake()->slug().'.pdf',
            'file_name' => fake()->words(3, true).'.pdf',
            'document_type' => fake()->randomElement([
                'Purchase Request',
                'PPMP',
                'Market Research',
                'Specifications',
                'Minutes',
                'Attendance Sheet',
                'Bidding Documents',
                'Bid Proposal',
                'Bid Summary',
                'Abstract',
                'Report',
                'Resolution',
                'Notice of Award',
                'Performance Bond',
                'Contract',
                'Purchase Order',
                'Notice to Proceed',
                'Monitoring Report',
                'Completion Certificate',
            ]),
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
            'metadata' => [
                'file_size' => fake()->numberBetween(10240, 10485760),
                'mime_type' => 'application/pdf',
                'hash' => fake()->sha256(),
                'upload_date' => fake()->date(),
            ],
            'blockchain_txid' => fake()->optional(0.8)->sha256(),
            'blockchain_status' => fake()->randomElement(['pending', 'confirmed', 'failed']),
            'blockchain_status_updated_at' => fake()->optional(0.8)->dateTimeBetween('-1 month', 'now'),
            'blockchain_error' => fake()->optional(0.1)->sentence(),
            'blockchain_retry_count' => fake()->numberBetween(0, 5),
            'is_corrected' => fake()->boolean(10),
            'correction_reason' => fake()->optional(0.1)->sentence(),
            'corrected_at' => fake()->optional(0.1)->dateTimeBetween('-1 month', 'now'),
            'corrected_by' => fake()->optional(0.1)->regexify('1[A-Z0-9]{33}'),
            'correction_txid' => fake()->optional(0.1)->sha256(),
        ];
    }

    /**
     * Indicate that the document has pending blockchain status.
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
     * Indicate that the document has confirmed blockchain status.
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
     * Indicate that the document has failed blockchain status.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'blockchain_status' => 'failed',
            'blockchain_error' => fake()->sentence(),
            'blockchain_retry_count' => fake()->numberBetween(1, 5),
        ]);
    }

    /**
     * Indicate that the document has been corrected.
     */
    public function corrected(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_corrected' => true,
            'correction_reason' => fake()->sentence(),
            'corrected_at' => now(),
            'corrected_by' => '1'.fake()->regexify('[A-Z0-9]{33}'),
            'correction_txid' => fake()->sha256(),
        ]);
    }
}
