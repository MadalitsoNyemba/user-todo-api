<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\JobStatusState;
use App\Models\JobStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobStatus>
 */
class JobStatusFactory extends Factory
{
    protected $model = JobStatus::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'type' => 'bulk_complete_todos',
            'status' => JobStatusState::Queued,
            'total' => 0,
            'processed' => 0,
            'failed_count' => 0,
            'result' => null,
            'error' => null,
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobStatusState::Queued,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobStatusState::Processing,
            'total' => $attributes['total'] ?: 10,
            'processed' => 3,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobStatusState::Completed,
            'total' => 5,
            'processed' => 5,
            'failed_count' => 0,
            'result' => ['completed_ids' => [1, 2, 3, 4, 5]],
            'error' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobStatusState::Failed,
            'error' => 'Something went wrong.',
            'result' => null,
        ]);
    }
}
