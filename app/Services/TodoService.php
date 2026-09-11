<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TodoPriority;
use App\Jobs\BulkCompleteTodosJob;
use App\Models\JobStatus;
use App\Models\Todo;
use App\Repositories\Contracts\TodoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

/**
 * Todo CRUD for the authenticated user. Filter, sort and pagination arguments
 * are assembled here so the controller stays a thin HTTP adapter.
 *
 * Missing or cross-tenant todos raise ModelNotFoundException → 404. A 403
 * would confirm the record exists and enable id enumeration. TodoPolicy runs
 * after the repository scopes the row, so ownership is enforced twice.
 *
 * Bulk complete is accepted as a redis job; the client polls JobStatus by uuid.
 */
class TodoService
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    public const BULK_COMPLETE_TYPE = 'bulk_complete_todos';

    public function __construct(
        private readonly TodoRepositoryInterface $todos,
        private readonly JobStatusService $jobStatuses,
    ) {}

    public function list(
        int $userId,
        ?bool $isCompleted = null,
        ?TodoPriority $priority = null,
        string $sort = 'created_at',
        string $order = 'desc',
        ?int $perPage = null,
    ): LengthAwarePaginator {
        Gate::authorize('viewAny', Todo::class);

        return $this->todos->paginateForUser(
            $userId,
            $this->capPerPage($perPage),
            $isCompleted,
            $priority,
            $sort,
            $order,
        );
    }

    public function find(int $userId, int $todoId): Todo
    {
        $todo = $this->findOwnedOrFail($userId, $todoId);
        Gate::authorize('view', $todo);

        return $todo;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $userId, array $attributes): Todo
    {
        Gate::authorize('create', Todo::class);

        $attributes = $this->withCompletionTimestamps($attributes, previousCompleted: false);

        return $this->todos->createForUser($userId, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $userId, int $todoId, array $attributes): Todo
    {
        $todo = $this->findOwnedOrFail($userId, $todoId);
        Gate::authorize('update', $todo);

        $attributes = $this->withCompletionTimestamps(
            $attributes,
            previousCompleted: $todo->is_completed,
        );

        $updated = $this->todos->updateForUser($userId, $todoId, $attributes);

        if ($updated === null) {
            throw (new ModelNotFoundException)->setModel(Todo::class, [$todoId]);
        }

        return $updated;
    }

    public function delete(int $userId, int $todoId): void
    {
        $todo = $this->findOwnedOrFail($userId, $todoId);
        Gate::authorize('delete', $todo);

        if (! $this->todos->deleteForUser($userId, $todoId)) {
            throw (new ModelNotFoundException)->setModel(Todo::class, [$todoId]);
        }
    }

    /**
     * @param  list<int>  $todoIds
     */
    public function bulkComplete(int $userId, array $todoIds): JobStatus
    {
        $ids = array_values(array_unique(array_map('intval', $todoIds)));

        $status = $this->jobStatuses->create(
            $userId,
            self::BULK_COMPLETE_TYPE,
            count($ids),
        );

        BulkCompleteTodosJob::dispatch($userId, $status->uuid, $ids);

        return $status;
    }

    private function findOwnedOrFail(int $userId, int $todoId): Todo
    {
        $todo = $this->todos->findForUser($userId, $todoId);

        if ($todo === null) {
            throw (new ModelNotFoundException)->setModel(Todo::class, [$todoId]);
        }

        return $todo;
    }

    private function capPerPage(?int $perPage): int
    {
        if ($perPage === null || $perPage < 1) {
            return self::DEFAULT_PER_PAGE;
        }

        return min($perPage, self::MAX_PER_PAGE);
    }

    /**
     * Keep completed_at aligned with is_completed when that flag is present.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withCompletionTimestamps(array $attributes, bool $previousCompleted): array
    {
        if (! array_key_exists('is_completed', $attributes)) {
            return $attributes;
        }

        $completed = filter_var($attributes['is_completed'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($completed === true) {
            $attributes['is_completed'] = true;
            if (! $previousCompleted) {
                $attributes['completed_at'] = now();
            }
        } elseif ($completed === false) {
            $attributes['is_completed'] = false;
            $attributes['completed_at'] = null;
        }

        return $attributes;
    }
}
