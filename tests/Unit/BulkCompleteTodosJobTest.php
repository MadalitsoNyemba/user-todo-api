<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\JobStatusState;
use App\Jobs\BulkCompleteTodosJob;
use App\Models\Todo;
use App\Models\User;
use App\Services\JobStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkCompleteTodosJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_completes_owned_todos_skips_others_and_is_idempotent(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $open = Todo::factory()->for($alice)->create(['title' => 'Open', 'is_completed' => false]);
        $alreadyDone = Todo::factory()->for($alice)->completed()->create(['title' => 'Done']);
        $bobs = Todo::factory()->for($bob)->create(['title' => 'Bob only', 'is_completed' => false]);

        $status = app(JobStatusService::class)->create($alice->id, 'bulk_complete_todos', 3);

        $job = new BulkCompleteTodosJob(
            $alice->id,
            $status->uuid,
            [$open->id, $alreadyDone->id, $bobs->id],
        );

        $this->app->call([$job, 'handle']);

        $open->refresh();
        $alreadyDone->refresh();
        $bobs->refresh();
        $status->refresh();

        $this->assertTrue($open->is_completed);
        $this->assertNotNull($open->completed_at);
        $this->assertTrue($alreadyDone->is_completed);
        $this->assertFalse($bobs->is_completed);

        $this->assertSame(JobStatusState::Completed, $status->status);
        $this->assertSame(2, $status->processed);
        $this->assertSame(1, $status->failed_count);
        $this->assertSame(2, $status->result['completed']);
        $this->assertSame(1, $status->result['skipped']);

        $completedAt = $open->completed_at->toIso8601String();

        $this->app->call([$job, 'handle']);

        $open->refresh();
        $status->refresh();

        $this->assertSame($completedAt, $open->completed_at->toIso8601String());
        $this->assertSame(JobStatusState::Completed, $status->status);
        $this->assertSame(2, $status->processed);
        $this->assertSame(2, $status->result['completed']);
    }

    public function test_failed_hook_records_the_error(): void
    {
        $user = User::factory()->create();
        $status = app(JobStatusService::class)->create($user->id, 'bulk_complete_todos', 1);
        $status = app(JobStatusService::class)->markProcessing($status);

        $job = new BulkCompleteTodosJob($user->id, $status->uuid, [1]);
        $job->failed(new \RuntimeException('Worker exploded.'));

        $status->refresh();
        $this->assertSame(JobStatusState::Failed, $status->status);
        $this->assertSame('Worker exploded.', $status->error);
    }
}
