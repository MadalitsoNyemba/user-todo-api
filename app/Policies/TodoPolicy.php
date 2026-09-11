<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Todo;
use App\Models\User;

/**
 * Ownership checks layered on top of repository scoping. Controllers and
 * services still load todos via findForUser (cross-tenant → 404); the policy
 * is a second gate so a future unbound lookup cannot slip through as an
 * update on someone else's row.
 */
class TodoPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Todo $todo): bool
    {
        return $this->owns($user, $todo);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Todo $todo): bool
    {
        return $this->owns($user, $todo);
    }

    public function delete(User $user, Todo $todo): bool
    {
        return $this->owns($user, $todo);
    }

    private function owns(User $user, Todo $todo): bool
    {
        return (int) $todo->user_id === (int) $user->id;
    }
}
