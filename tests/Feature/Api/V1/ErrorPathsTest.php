<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\JobStatusState;
use App\Jobs\BulkCompleteTodosJob;
use App\Models\JobStatus;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Abuse and error paths that only make sense once the full API surface exists.
 * Happy paths live in the per-slice suites; this file targets auth failure modes,
 * cross-tenant isolation, query abuse, bulk-complete limits, and the generic 500.
 */
class ErrorPathsTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_routes_reject_missing_malformed_and_expired_tokens(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Token not provided or could not be parsed.');

        $this->withHeader('Authorization', 'Bearer not-a-real-token')
            ->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Token is invalid.');

        $this->travel(-2)->hours();
        $expired = JWTAuth::fromUser($user);
        $this->travelBack();

        $this->withHeader('Authorization', "Bearer {$expired}")
            ->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Token has expired. Refresh it or log in again.');
    }

    public function test_a_token_for_a_deleted_user_is_rejected_on_a_real_route(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);
        $user->delete();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_cross_user_access_is_404_on_todos_bulk_complete_and_jobs(): void
    {
        [$alice, $aliceToken] = $this->userWithToken('alice@example.com');
        [$bob, $bobToken] = $this->userWithToken('bob@example.com');

        $bobTodo = Todo::factory()->for($bob)->create(['title' => 'Bob only', 'is_completed' => false]);
        $bobJob = JobStatus::factory()->for($bob)->queued()->create([
            'type' => 'bulk_complete_todos',
            'total' => 1,
        ]);

        foreach (['GET', 'PATCH', 'DELETE'] as $method) {
            $this->withHeader('Authorization', "Bearer {$aliceToken}")
                ->json($method, "/api/v1/todos/{$bobTodo->id}", ['title' => 'hijack'])
                ->assertStatus(404)
                ->assertJsonPath('success', false)
                ->assertJsonPath('message', 'Resource not found.');
        }

        $this->withHeader('Authorization', "Bearer {$aliceToken}")
            ->getJson('/api/v1/jobs/'.$bobJob->uuid)
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Resource not found.');

        Queue::fake();

        $aliceTodo = Todo::factory()->for($alice)->create(['title' => 'Alice open', 'is_completed' => false]);

        $response = $this->withHeader('Authorization', "Bearer {$aliceToken}")
            ->postJson('/api/v1/todos/bulk-complete', [
                'ids' => [$aliceTodo->id, $bobTodo->id],
            ]);

        $response->assertStatus(202);
        $jobId = $response->json('data.job_id');
        $this->assertIsString($jobId);

        Queue::assertPushed(BulkCompleteTodosJob::class, function (BulkCompleteTodosJob $job) use ($alice, $jobId, $aliceTodo, $bobTodo): bool {
            if ($job->userId !== $alice->id || $job->jobStatusUuid !== $jobId) {
                return false;
            }

            $this->app->call([$job, 'handle']);

            return $job->todoIds === [$aliceTodo->id, $bobTodo->id];
        });

        $this->assertTrue($aliceTodo->fresh()->is_completed);
        $this->assertFalse($bobTodo->fresh()->is_completed);

        $status = $this->withHeader('Authorization', "Bearer {$aliceToken}")
            ->getJson('/api/v1/jobs/'.$jobId);

        $status->assertOk();
        $status->assertJsonPath('data.status', JobStatusState::Completed->value);
        $status->assertJsonPath('data.result.completed', 1);
        $status->assertJsonPath('data.result.skipped', 1);

        // Bob still owns his todo and his job; Alice cannot see Bob's job.
        $this->withHeader('Authorization', "Bearer {$bobToken}")
            ->getJson("/api/v1/todos/{$bobTodo->id}")
            ->assertOk()
            ->assertJsonPath('data.is_completed', false);
    }

    public function test_list_query_abuse_is_rejected_or_capped(): void
    {
        [, $token] = $this->userWithToken('alice@example.com');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/todos?sort=password')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['sort']]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/todos?page=-1')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['page']]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/todos?per_page=-5')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['per_page']]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/todos?per_page=100000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_invalid_priority_returns_422_through_the_envelope(): void
    {
        [, $token] = $this->userWithToken('alice@example.com');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/todos', [
                'title' => 'Bad priority',
                'priority' => 'urgent',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'The given data was invalid.');
        $response->assertJsonPath('data', null);
        $response->assertJsonStructure(['errors' => ['priority']]);
    }

    public function test_bulk_complete_rejects_empty_and_oversized_id_lists(): void
    {
        [, $token] = $this->userWithToken('alice@example.com');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/todos/bulk-complete', ['ids' => []])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['ids']]);

        $tooMany = range(1, 501);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/todos/bulk-complete', ['ids' => $tooMany])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['ids']]);
    }

    public function test_unhandled_exception_returns_generic_500_without_trace_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        Route::middleware('api')->get('/api/v1/_error-paths-boom', function (): void {
            throw new \RuntimeException('secret internals must not leak');
        });

        $response = $this->getJson('/api/v1/_error-paths-boom');

        $response->assertStatus(500);
        $response->assertExactJson([
            'success' => false,
            'message' => 'An unexpected error occurred.',
            'data' => null,
            'errors' => null,
            'meta' => null,
        ]);
        $response->assertJsonMissing(['secret internals must not leak']);
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
