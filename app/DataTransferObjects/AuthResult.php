<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\User;

/**
 * What AuthService hands back. A typed object rather than a loose array, so
 * the controller cannot misspell a key and the shape is enforced by PHP.
 */
final readonly class AuthResult
{
    public function __construct(
        public User $user,
        public string $token,
        public int $expiresIn,
    ) {}
}
