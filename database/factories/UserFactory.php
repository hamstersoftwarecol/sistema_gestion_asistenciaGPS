<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
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
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => 'active',
            'pay_type' => 'monthly',
            'monthly_salary' => 2000000,
            'hourly_rate' => 0,
            'hire_date' => now()->subYear()->toDateString(),
        ];
    }

    /**
     * Asigna un rol del sistema (admin, hr, manager, employee), creándolo si no existe.
     */
    public function role(string $slug): static
    {
        return $this->state(fn () => [
            'role_id' => Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => ucfirst($slug), 'permissions' => RoleSeeder::PERMISSIONS[$slug] ?? [], 'is_system' => true],
            )->id,
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
