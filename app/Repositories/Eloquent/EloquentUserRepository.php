<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function create(string $name, string $email, string $password): User
    {
        // The model casts password to hashed, so a plain value is correct here.
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function update(User $user, string $name, string $email, ?string $password = null): User
    {
        $attributes = [
            'name' => $name,
            'email' => $email,
        ];

        if ($password !== null) {
            // The model casts password to hashed, so a plain value is correct here.
            $attributes['password'] = $password;
        }

        $user->update($attributes);

        return $user->refresh();
    }

    public function bumpTokenVersion(User $user): User
    {
        // Not fillable on purpose — only the service may bump this after a
        // sensitive profile change.
        $user->forceFill([
            'token_version' => $user->token_version + 1,
        ])->save();

        return $user->refresh();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
