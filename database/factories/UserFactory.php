<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /** Plain-text factory password (meets Password::defaults() in non-production). */
    public const DEFAULT_PASSWORD = 'Password1!';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make(self::DEFAULT_PASSWORD),
            'remember_token' => Str::random(10),
            'tenant_id' => null,
            'role_id' => null,
            'two_factor_confirmed_at' => null,
            'firm_id' => null,
            'firm_role' => null,
            'privacy_accepted_at' => null,
            'privacy_accepted_version' => null,
            'data_exported_at' => null,
            'deletion_requested_at' => null,
            'welcomed_at' => null,
            'verify_reminder_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
