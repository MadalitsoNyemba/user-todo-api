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
 * Registration, credential checking and token issuance.
 *
 * Takes scalars and returns an AuthResult. It never reads the request and
 * never builds a response, so it can be called from a controller, a console
 * command or a test with equal ease.
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

    private function issueToken(User $user): AuthResult
    {
        return new AuthResult(
            user: $user,
            token: JWTAuth::fromUser($user),
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
