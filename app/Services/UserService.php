<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DuplicateEmailException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Authenticated profile read, update and account deletion, plus the admin
 * user index authorised via UserPolicy::viewAny.
 *
 * Profile routes stay scoped to the caller. Listing every account is admin
 * only and returns 403 for a normal user — a genuine forbidden path, unlike
 * cross-tenant todos which stay 404 to avoid id enumeration.
 *
 * Email or password changes bump users.token_version (checked against the JWT
 * `ver` claim) and blacklist the current jti, so a stolen bearer cannot keep
 * a durable foothold after a credentials change.
 */
class UserService
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

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

    /**
     * Paginated directory of all users. Caller must already be authorised
     * (UserPolicy::viewAny); this method does not re-check the role so the
     * controller / gate remains the single authorisation point.
     */
    public function list(?int $perPage = null): LengthAwarePaginator
    {
        return $this->users->paginate($this->capPerPage($perPage));
    }

    private function capPerPage(?int $perPage): int
    {
        if ($perPage === null || $perPage < 1) {
            return self::DEFAULT_PER_PAGE;
        }

        return min($perPage, self::MAX_PER_PAGE);
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
