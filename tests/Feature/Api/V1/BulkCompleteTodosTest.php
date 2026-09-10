<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\JobStatusState;
use App\Jobs\BulkCompleteTodosJob;
use App\Models\User;
use App\Services\TodoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BulkCompleteTodosTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_complete_returns_202_pushes_the_job_and_queues_status(): void
    {
        Queue::fake();

        [$user, $token] = $this->userWithToken('alice@example.com');

        $todoIds = [
            $user->todos()->create(['title' => 'One'])->id,
            $user->todos()->create(['title' => 'Two'])->id,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/todos/bulk-complete', [
                'ids' => $todoIds,
            ]);

        $response->assertStatus(202);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', JobStatusState::Queued->value);
        $response->assertJsonStructure(['data' => ['job_id', 'status', 'status_url']]);

        $jobId = $response->json('data.job_id');
        $this->assertIsString($jobId);
        $this->assertStringContainsString('/api/v1/jobs/'.$jobId, $response->json('data.status_url'));

        Queue::assertPushed(BulkCompleteTodosJob::class, function (BulkCompleteTodosJob $job) use ($user, $todoIds, $jobId): bool {
            return $job->userId === $user->id
                && $job->jobStatusUuid === $jobId
                && $job->todoIds === $todoIds;
        });

        $this->assertDatabaseHas('job_statuses', [
            'uuid' => $jobId,
            'user_id' => $user->id,
            'type' => TodoService::BULK_COMPLETE_TYPE,
            'status' => JobStatusState::Queued->value,
            'total' => 2,
        ]);
    }

    public function test_bulk_complete_rejects_an_empty_ids_list(): void
    {
        Queue::fake();

        [, $token] = $this->userWithToken('alice@example.com');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/todos/bulk-complete', ['ids' => []])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['ids']]);
    }

    public function test_unauthenticated_bulk_complete_is_rejected(): void
    {
        $this->postJson('/api/v1/todos/bulk-complete', ['ids' => [1]])
            ->assertStatus(401);
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function userWithToken(string $email): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => 'correct-horse-battery',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'correct-horse-battery',
        ]);

        $login->assertOk();
        $token = $login->json('data.token');
        $this->assertIsString($token);

        return [$user, $token];
    }
}
