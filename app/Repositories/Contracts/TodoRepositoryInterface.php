<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\TodoPriority;
use App\Models\Todo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Every method takes the authenticated user id and scopes by it. Another
 * user’s todo is not visible, not “forbidden” — find/update/delete miss.
 */
interface TodoRepositoryInterface
{
    /**
     * @param  'due_date'|'created_at'  $sort
     * @param  'asc'|'desc'  $order
     */
    public function paginateForUser(
        int $userId,
        int $perPage = 15,
        ?bool $isCompleted = null,
        ?TodoPriority $priority = null,
        string $sort = 'created_at',
        string $order = 'desc',
    ): LengthAwarePaginator;

    public function findForUser(int $userId, int $todoId): ?Todo;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createForUser(int $userId, array $attributes): Todo;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateForUser(int $userId, int $todoId, array $attributes): ?Todo;

    public function deleteForUser(int $userId, int $todoId): bool;

    /**
     * Mark incomplete todos owned by the user as complete.
     *
     * @param  list<int>  $todoIds
     * @return int Number of rows updated
     */
    public function completeManyForUser(int $userId, array $todoIds): int;
}
