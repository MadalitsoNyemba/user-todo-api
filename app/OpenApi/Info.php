<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'User Todo API',
    description: 'Versioned REST API for user accounts and per-user todos. Authenticate with a JWT bearer token issued by the auth endpoints.',
)]
#[OA\Server(
    url: '/',
    description: 'Current host (Compose maps the API to http://localhost:8080 by default).',
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    description: 'JWT access token returned by register or login. Send as `Authorization: Bearer {token}`.',
    bearerFormat: 'JWT',
    scheme: 'bearer',
)]
#[OA\Tag(name: 'Auth', description: 'Registration, sign-in, token refresh and sign-out.')]
#[OA\Tag(name: 'Profile', description: 'The authenticated user’s own account. No user index — listing others is admin-only.')]
#[OA\Tag(name: 'Todos', description: 'Per-user todos. Cross-tenant access returns 404, not 403.')]
final class Info {}
