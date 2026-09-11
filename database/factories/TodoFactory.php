<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TodoPriority;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Todo>
 */
class TodoFactory extends Factory
{
    protected $model = Todo::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional(0.7)->paragraph(),
            'is_completed' => false,
            'completed_at' => null,
            'due_date' => fake()->boolean(60)
                ? fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d')
                : null,
            'priority' => fake()->optional(0.8)->randomElement(TodoPriority::cases()),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_completed' => true,
            'completed_at' => now()->subDay(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_completed' => false,
            'completed_at' => null,
            'due_date' => now()->subDays(3)->toDateString(),
        ]);
    }

    public function low(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => TodoPriority::Low,
        ]);
    }

    public function medium(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => TodoPriority::Medium,
        ]);
    }

    public function high(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => TodoPriority::High,
        ]);
    }
}
