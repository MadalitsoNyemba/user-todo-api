<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\JobStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class JobStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_read_job_status_and_progress(): void
    {
        [$user, $token] = $this->userWithToken('alice@example.com');

        $job = JobStatus::factory()->for($user)->processing()->create([
            'total' => 10,
            'processed' => 4,
            'failed_count' => 1,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/jobs/'.$job->uuid);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.uuid', $job->uuid);
        $response->assertJsonPath('data.status', 'processing');
        $response->assertJsonPath('data.total', 10);
        $response->assertJsonPath('data.processed', 4);
        $response->assertJsonPath('data.failed_count', 1);
        $response->assertJsonMissingPath('data.user_id');
        $response->assertJsonMissingPath('data.id');
    }

    public function test_another_users_job_returns_404_not_403(): void
    {
        [, $aliceToken] = $this->userWithToken('alice@example.com');
        [$bob] = $this->userWithToken('bob@example.com');

        $bobJob = JobStatus::factory()->for($bob)->queued()->create();

        $response = $this->withHeader('Authorization', "Bearer {$aliceToken}")
            ->getJson('/api/v1/jobs/'.$bobJob->uuid);

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'Resource not found.');
        $this->assertNotSame(403, $response->status());
    }

    public function test_an_unknown_uuid_returns_404(): void
    {
        [, $token] = $this->userWithToken('alice@example.com');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/jobs/'.Str::uuid());

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Resource not found.');
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/jobs/'.Str::uuid())->assertStatus(401);
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
