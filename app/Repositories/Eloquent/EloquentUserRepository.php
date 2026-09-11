<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function create(string $name, string $email, string $password): User
    {
        // The model casts password to hashed, so a plain value is correct here.
        // role defaults to user in the migration / model cast.
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => UserRole::User,
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

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return User::query()
            ->orderBy('id')
            ->paginate($perPage);
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
