<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'token_version' => 'integer',
    ];

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
