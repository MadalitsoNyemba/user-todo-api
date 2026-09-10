<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\JobStatusState;
use App\Models\JobStatus;
use App\Repositories\Contracts\JobStatusRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Persists async job progress so a client can poll instead of getting a
 * fire-and-forget response. Writers such as BulkCompleteTodosJob transition
 * rows through this service.
 */
class JobStatusService
{
    public function __construct(
        private readonly JobStatusRepositoryInterface $jobs,
    ) {}

    public function create(int $userId, string $type, int $total = 0): JobStatus
    {
        return $this->jobs->createForUser($userId, $type, $total);
    }

    public function markProcessing(JobStatus $job): JobStatus
    {
        $job->status = JobStatusState::Processing;

        return $this->jobs->save($job);
    }

    public function incrementProgress(JobStatus $job, int $by = 1, int $failedBy = 0): JobStatus
    {
        $job->processed += $by;
        $job->failed_count += $failedBy;

        return $this->jobs->save($job);
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    public function markCompleted(JobStatus $job, ?array $result = null): JobStatus
    {
        $job->status = JobStatusState::Completed;
        $job->result = $result;
        $job->error = null;

        return $this->jobs->save($job);
    }

    public function markFailed(JobStatus $job, string $error): JobStatus
    {
        $job->status = JobStatusState::Failed;
        $job->error = $error;

        return $this->jobs->save($job);
    }

    public function findForUser(int $userId, string $uuid): JobStatus
    {
        $job = $this->jobs->findForUser($userId, $uuid);

        if ($job === null) {
            throw (new ModelNotFoundException)->setModel(JobStatus::class, [$uuid]);
        }

        return $job;
    }
}
