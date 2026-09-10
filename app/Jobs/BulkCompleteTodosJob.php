<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\JobStatusState;
use App\Repositories\Contracts\JobStatusRepositoryInterface;
use App\Repositories\Contracts\TodoRepositoryInterface;
use App\Services\JobStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Completes many todos for one user on the redis queue.
 *
 * Ownership is re-checked in the repository; foreign ids are skipped. Already
 * completed todos are left alone so a retry after partial success does not
 * double-apply or double-count progress.
 */
class BulkCompleteTodosJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    /**
     * @param  list<int>  $todoIds
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $jobStatusUuid,
        public readonly array $todoIds,
    ) {
        $this->onConnection('redis');
    }

    public function handle(
        TodoRepositoryInterface $todos,
        JobStatusService $jobStatuses,
        JobStatusRepositoryInterface $jobStatusRepository,
    ): void {
        $status = $jobStatusRepository->findForUser($this->userId, $this->jobStatusUuid);

        if ($status === null) {
            return;
        }

        if ($status->status === JobStatusState::Completed) {
            return;
        }

        $status = $jobStatuses->markProcessing($status);

        $requestedIds = array_values(array_unique(array_map('intval', $this->todoIds)));
        $ownedIds = $todos->ownedIdsAmong($this->userId, $requestedIds);
        $skipped = count($requestedIds) - count($ownedIds);

        $todos->completeManyForUser($this->userId, $ownedIds);

        $completed = $todos->countCompletedAmong($this->userId, $ownedIds);

        $status->processed = $completed;
        $status->failed_count = $skipped;
        $status->total = count($requestedIds);

        $jobStatuses->markCompleted($status, [
            'completed' => $completed,
            'skipped' => $skipped,
            'requested' => count($requestedIds),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $status = app(JobStatusRepositoryInterface::class)
            ->findForUser($this->userId, $this->jobStatusUuid);

        if ($status === null || $status->status === JobStatusState::Completed) {
            return;
        }

        app(JobStatusService::class)->markFailed(
            $status,
            $exception?->getMessage() ?: 'Bulk complete failed.',
        );
    }
}
