<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Todo;
use App\Repositories\Contracts\TodoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentTodoRepository implements TodoRepositoryInterface
{
    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Todo::query()
            ->where('user_id', $userId)
            ->latest('id')
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
