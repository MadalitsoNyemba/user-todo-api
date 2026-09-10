<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DuplicateEmailException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Authenticated profile read, update and account deletion.
 *
 * Scoped to the caller’s own account — there is no user index. Listing other
 * users is deferred to the admin-only route. Todo cascade on delete is owned
 * by the todos migration and asserted once that table exists.
 *
 * Email or password changes bump users.token_version (checked against the JWT
 * `ver` claim) and blacklist the current jti, so a stolen bearer cannot keep
 * a durable foothold after a credentials change.
 */
class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AuthService $auth,
    ) {}

    public function profile(int $userId): User
    {
        $user = $this->users->findById($userId);

        if ($user === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$userId]);
        }

        return $user;
    }

    public function update(User $user, string $name, string $email, ?string $password = null): User
    {
        $emailChanged = $email !== $user->email;
        $passwordChanging = $password !== null;

        try {
            $updated = $this->users->update($user, $name, $email, $password);
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateEmailException;
        }

        if ($emailChanged || $passwordChanging) {
            $updated = $this->users->bumpTokenVersion($updated);
            // Blacklist this jti as well; other sessions die via token_version.
            $this->auth->logout();
        }

        return $updated;
    }

    /**
     * Blacklist the current bearer token, then remove the account.
     *
     * Invalidation runs first while the JWT is still parseable; deletion
     * follows so a deleted subject cannot keep using the same token.
     */
    public function deleteAccount(User $user): void
    {
        $this->auth->logout();
        $this->users->delete($user);
    }
}
