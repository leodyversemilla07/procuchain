<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserLoginLog>
 */
class UserLoginLogFactory extends Factory
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
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'device_type' => fake()->randomElement(['desktop', 'mobile', 'tablet']),
            'browser' => fake()->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
            'platform' => fake()->randomElement(['Windows', 'macOS', 'Linux', 'iOS', 'Android']),
            'location' => fake()->city().', '.fake()->country(),
            'successful' => fake()->boolean(80), // 80% successful
            'login_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'logout_at' => null,
        ];
    }
}
