<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $empNumber = fake()->unique()->numberBetween(10, 999);

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'user_code' => str_pad((string) $empNumber, 3, '0', STR_PAD_LEFT),
            'role' => 'user',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (User $user): void {
            $initial = (string) config('library.initial_password', 'password');
            $user->must_change_password = $initial !== ''
                && Hash::check($initial, (string) $user->password);
        });
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'user_code' => $attributes['user_code'] ?? '001',
        ]);
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
