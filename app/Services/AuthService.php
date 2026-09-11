<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\AuthResult;
use App\Exceptions\DuplicateEmailException;
use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Registration, credential checking, token issuance and invalidation.
 *
 * Takes scalars (or an already-authenticated User) and returns an AuthResult
 * or void. It never reads the request and never builds a response, so it can
 * be called from a controller, a console command or a test with equal ease.
 *
 * Logout writes the token's jti into the JWT blacklist (Redis cache store in
 * the running stack). Refresh issues a new token and blacklists the previous
 * one; refresh-token rotation and sliding sessions are left for later work.
 */
class AuthService
{
    private static ?string $dummyHash = null;

    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function register(string $name, string $email, string $password): AuthResult
    {
        try {
            $user = $this->users->create($name, $email, $password);
        } catch (UniqueConstraintViolationException) {
            // Two requests can both pass FormRequest uniqueness and collide on
            // the DB unique index. Map that to the same 422 shape as validation.
            throw new DuplicateEmailException;
        }

        return $this->issueToken($user);
    }

    /**
     * @throws InvalidCredentialsException
     */
    public function login(string $email, string $password): AuthResult
    {
        $user = $this->users->findByEmail($email);

        // The hash comparison runs whether or not a user was found, so an
        // unknown address costs the same as a wrong password. Skipping it
        // would make response time alone reveal which addresses are
        // registered. The dummy must be a real bcrypt hash, or Hash::check
        // throws rather than returning false.
        $hash = $user?->password ?? $this->dummyHash();

        $passwordMatches = Hash::check($password, $hash);

        if ($user === null || ! $passwordMatches) {
            throw new InvalidCredentialsException;
        }

        return $this->issueToken($user);
    }

    /**
     * Blacklist the current bearer token immediately.
     *
     * forceForever bypasses the blacklist grace period so a logged-out token
     * cannot be reused for JWT_BLACKLIST_GRACE_PERIOD seconds. That grace
     * window exists for concurrent refresh races, not for sign-out.
     */
    public function logout(): void
    {
        JWTAuth::parseToken()->invalidate(true);
    }

    /**
     * Issue a new access token for the authenticated subject and blacklist
     * the one that was presented.
     */
    public function refresh(User $user): AuthResult
    {
        $token = JWTAuth::parseToken()->refresh();

        return $this->tokenResult($user, $token);
    }

    private function issueToken(User $user): AuthResult
    {
        return $this->tokenResult($user, JWTAuth::fromUser($user));
    }

    private function tokenResult(User $user, string $token): AuthResult
    {
        return new AuthResult(
            user: $user,
            token: $token,
            expiresIn: JWTAuth::factory()->getTTL() * 60,
        );
    }

    /**
     * Built once per process at the configured bcrypt cost so an unknown email
     * matches a wrong-password check. A fixed $2y$12$… hash would diverge
     * whenever BCRYPT_ROUNDS is not 12 (phpunit sets it to 4).
     */
    private function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make(str_repeat('0', 32));
    }
}
