<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\JobStatus;

/**
 * Job status rows are scoped to a user. Another user’s uuid is not visible.
 */
interface JobStatusRepositoryInterface
{
    public function createForUser(int $userId, string $type, int $total = 0): JobStatus;

    public function findForUser(int $userId, string $uuid): ?JobStatus;

    public function save(JobStatus $job): JobStatus;
}
