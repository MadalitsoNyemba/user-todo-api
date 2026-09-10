<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    public function create(string $name, string $email, string $password): User;

    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;

    public function update(User $user, string $name, string $email, ?string $password = null): User;

    public function bumpTokenVersion(User $user): User;

    public function delete(User $user): void;
}
