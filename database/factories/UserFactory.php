<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::User,
            'department' => fake()->randomElement(['Finance', 'Registry', 'Human Resources', 'Library']),
            'is_active' => true,
            'failed_logins' => 0,
            'locked_until' => null,
            'remember_token' => fake()->regexify('[A-Za-z0-9]{10}'),
        ];
    }

    public function agent(): static
    {
        return $this->state(['role' => UserRole::Agent, 'department' => 'ICT']);
    }

    public function admin(): static
    {
        return $this->state(['role' => UserRole::Admin, 'department' => 'ICT']);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function locked(): static
    {
        return $this->state(fn (): array => [
            'failed_logins' => 5,
            'locked_until' => now()->addMinutes(15),
        ]);
    }
}
