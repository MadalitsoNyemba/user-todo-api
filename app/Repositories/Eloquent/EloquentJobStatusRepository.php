<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\JobStatusState;
use App\Models\JobStatus;
use App\Repositories\Contracts\JobStatusRepositoryInterface;
use Illuminate\Support\Str;

class EloquentJobStatusRepository implements JobStatusRepositoryInterface
{
    public function createForUser(int $userId, string $type, int $total = 0): JobStatus
    {
        return JobStatus::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'type' => $type,
            'status' => JobStatusState::Queued,
            'total' => $total,
            'processed' => 0,
            'failed_count' => 0,
            'result' => null,
            'error' => null,
        ]);
    }

    public function findForUser(int $userId, string $uuid): ?JobStatus
    {
        return JobStatus::query()
            ->where('user_id', $userId)
            ->where('uuid', $uuid)
            ->first();
    }

    public function save(JobStatus $job): JobStatus
    {
        $job->save();

        return $job->refresh();
    }
}
