<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Authenticates the bearer token and lets the JWT exceptions escape.
 *
 * The stock `auth:api` middleware cannot do this. The JWT guard resolves the
 * user through check(true), which swallows TokenExpiredException and
 * TokenInvalidException and simply returns false, so Laravel raises a generic
 * AuthenticationException and the caller cannot tell an expired token from a
 * malformed one. Parsing here keeps that distinction, which matters because
 * the two demand different things of a client: refresh, or log in again.
 *
 * The resolved user is pushed into the `api` guard so a later auth()->user()
 * reuses it instead of the guard re-parsing and re-verifying the token itself.
 */
class AuthenticateWithJwt
{
    public function __construct(private AuthFactory $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Throws TokenExpiredException, TokenInvalidException or JWTException,
        // each mapped to its own message in the exception handler.
        $user = JWTAuth::parseToken()->authenticate();

        if (! $user) {
            throw new AuthenticationException;
        }

        $this->auth->guard('api')->setUser($user);

        return $next($request);
    }
}
