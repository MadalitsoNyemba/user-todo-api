<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\JobStatusState;
use App\Models\User;
use App\Services\JobStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_transitions_through_progress_to_completed(): void
    {
        $user = User::factory()->create();
        $jobs = app(JobStatusService::class);

        $job = $jobs->create($user->id, 'bulk_complete_todos', 3);

        $this->assertSame(JobStatusState::Queued, $job->status);
        $this->assertSame(3, $job->total);
        $this->assertSame(0, $job->processed);
        $this->assertNotEmpty($job->uuid);

        $job = $jobs->markProcessing($job);
        $this->assertSame(JobStatusState::Processing, $job->status);

        $job = $jobs->incrementProgress($job, 2, 1);
        $this->assertSame(2, $job->processed);
        $this->assertSame(1, $job->failed_count);

        $job = $jobs->markCompleted($job, ['completed_ids' => [1, 2]]);
        $this->assertSame(JobStatusState::Completed, $job->status);
        $this->assertSame(['completed_ids' => [1, 2]], $job->result);
        $this->assertNull($job->error);
    }

    public function test_mark_failed_records_the_error(): void
    {
        $user = User::factory()->create();
        $jobs = app(JobStatusService::class);

        $job = $jobs->create($user->id, 'bulk_complete_todos', 5);
        $job = $jobs->markProcessing($job);
        $job = $jobs->markFailed($job, 'Worker crashed mid-batch.');

        $this->assertSame(JobStatusState::Failed, $job->status);
        $this->assertSame('Worker crashed mid-batch.', $job->error);
    }
}
