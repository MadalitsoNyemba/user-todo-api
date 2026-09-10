<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\TodoPriority;
use App\Models\Todo;
use App\Repositories\Contracts\TodoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentTodoRepository implements TodoRepositoryInterface
{
    public function paginateForUser(
        int $userId,
        int $perPage = 15,
        ?bool $isCompleted = null,
        ?TodoPriority $priority = null,
        string $sort = 'created_at',
        string $order = 'desc',
    ): LengthAwarePaginator {
        $query = Todo::query()->where('user_id', $userId);

        if ($isCompleted !== null) {
            $query->where('is_completed', $isCompleted);
        }

        if ($priority !== null) {
            $query->where('priority', $priority->value);
        }

        $direction = strtolower($order) === 'asc' ? 'asc' : 'desc';
        $column = $sort === 'due_date' ? 'due_date' : 'created_at';

        return $query
            ->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->paginate($perPage);
    }

    public function findForUser(int $userId, int $todoId): ?Todo
    {
        return Todo::query()
            ->where('user_id', $userId)
            ->whereKey($todoId)
            ->first();
    }

    public function createForUser(int $userId, array $attributes): Todo
    {
        unset($attributes['user_id']);

        return Todo::query()->create([
            ...$attributes,
            'user_id' => $userId,
        ]);
    }

    public function updateForUser(int $userId, int $todoId, array $attributes): ?Todo
    {
        $todo = $this->findForUser($userId, $todoId);

        if ($todo === null) {
            return null;
        }

        unset($attributes['user_id']);
        $todo->update($attributes);

        return $todo->refresh();
    }

    public function deleteForUser(int $userId, int $todoId): bool
    {
        $todo = $this->findForUser($userId, $todoId);

        if ($todo === null) {
            return false;
        }

        return (bool) $todo->delete();
    }

    public function completeManyForUser(int $userId, array $todoIds): int
    {
        if ($todoIds === []) {
            return 0;
        }

        return Todo::query()
            ->where('user_id', $userId)
            ->whereIn('id', $todoIds)
            ->where('is_completed', false)
            ->update([
                'is_completed' => true,
                'completed_at' => now(),
            ]);
    }
}
