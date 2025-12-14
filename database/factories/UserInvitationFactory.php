<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserInvitation>
 */
class UserInvitationFactory extends Factory
{
    protected $model = UserInvitation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'role' => fake()->randomElement(['bac_secretariat', 'bac_chairman', 'hope']),
            'invited_by' => User::factory(),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'user_id' => null,
            'revoked' => false,
            'revoked_at' => null,
            'revoked_by' => null,
        ];
    }

    /**
     * Indicate that the invitation has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDays(1),
        ]);
    }

    /**
     * Indicate that the invitation has been accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => now(),
            'user_id' => User::factory(),
        ]);
    }

    /**
     * Indicate that the invitation has been revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked' => true,
            'revoked_at' => now(),
            'revoked_by' => User::factory(),
        ]);
    }

    /**
     * Set a specific role for the invitation.
     */
    public function role(string $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
        ]);
    }
}
