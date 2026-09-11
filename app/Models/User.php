<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'token_version' => 'integer',
        'role' => UserRole::class,
    ];

    /**
     * Eloquent does not hydrate MySQL DEFAULT values onto a freshly created
     * model, so keep the application default here as well as in the migration.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'user',
    ];

    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * The value stored in the token's `sub` claim.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * `ver` is checked by AuthenticateWithJwt against users.token_version.
     * Bumping the column invalidates every outstanding access token for the
     * user (password/email change) without enumerating jtis. The claim is
     * not sensitive — it is an integer counter, not a secret.
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'ver' => $this->token_version,
        ];
    }
}
