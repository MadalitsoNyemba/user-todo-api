<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * OpenAPI operations for auth routes. Kept off AuthController so controllers
 * stay thin under review.
 */
final class AuthEndpoints
{
    #[OA\Post(
        path: '/api/v1/auth/register',
        operationId: 'authRegister',
        description: 'Creates a user and returns a JWT. Duplicate emails and invalid input both yield 422 with the error envelope.',
        summary: 'Register a new account',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Alice Example'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'alice@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'correct-horse-battery'),
                ],
                type: 'object',
            ),
        ),
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Account created.',
                content: new OA\JsonContent(
                    required: ['success', 'message', 'data', 'errors', 'meta'],
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Account created.'),
                        new OA\Property(
                            property: 'data',
                            required: ['user', 'token', 'token_type', 'expires_in'],
                            properties: [
                                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                                new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'),
                                new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                                new OA\Property(
                                    property: 'expires_in',
                                    description: 'Seconds until the token expires (JWT_TTL minutes × 60).',
                                    type: 'integer',
                                    example: 3600,
                                ),
                            ],
                            type: 'object',
                        ),
                        new OA\Property(property: 'errors', nullable: true, example: null),
                        new OA\Property(property: 'meta', nullable: true, example: null),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed (including duplicate email).',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'),
            ),
            new OA\Response(
                response: 429,
                description: 'Too many attempts for this email and IP within one minute. Includes a Retry-After header.',
                headers: [
                    new OA\Header(
                        header: 'Retry-After',
                        description: 'Seconds until another attempt is allowed.',
                        schema: new OA\Schema(type: 'integer', example: 60),
                    ),
                ],
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'),
            ),
        ],
    )]
    public function register(): void {}

    #[OA\Post(
        path: '/api/v1/auth/login',
        operationId: 'authLogin',
        description: 'Issues a JWT for valid credentials. Unknown emails and wrong passwords return the same 401 so the endpoint cannot be used to discover registered addresses.',
        summary: 'Sign in',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'alice@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'correct-horse-battery'),
                ],
                type: 'object',
            ),
        ),
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Signed in.',
                content: new OA\JsonContent(
                    required: ['success', 'message', 'data', 'errors', 'meta'],
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Signed in.'),
                        new OA\Property(
                            property: 'data',
                            required: ['user', 'token', 'token_type', 'expires_in'],
                            properties: [
                                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                                new OA\Property(property: 'token', type: 'string'),
                                new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                                new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                            ],
                            type: 'object',
                        ),
                        new OA\Property(property: 'errors', nullable: true, example: null),
                        new OA\Property(property: 'meta', nullable: true, example: null),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Credentials do not match our records.',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ],
                    example: [
                        'success' => false,
                        'message' => 'These credentials do not match our records.',
                        'data' => null,
                        'errors' => null,
                        'meta' => null,
                    ],
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'),
            ),
            new OA\Response(
                response: 429,
                description: 'Too many attempts for this email and IP within one minute. Includes a Retry-After header.',
                headers: [
                    new OA\Header(
                        header: 'Retry-After',
                        description: 'Seconds until another attempt is allowed.',
                        schema: new OA\Schema(type: 'integer', example: 60),
                    ),
                ],
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'),
            ),
        ],
    )]
    public function login(): void {}

    #[OA\Post(
        path: '/api/v1/auth/logout',
        operationId: 'authLogout',
        description: 'Blacklists the current JWT. The same bearer token cannot authenticate again afterwards.',
        summary: 'Sign out',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Signed out.',
                content: new OA\JsonContent(
                    required: ['success', 'message', 'data', 'errors', 'meta'],
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Signed out.'),
                        new OA\Property(property: 'data', nullable: true, example: null),
                        new OA\Property(property: 'errors', nullable: true, example: null),
                        new OA\Property(property: 'meta', nullable: true, example: null),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Missing, invalid, expired or already blacklisted token.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'),
            ),
        ],
    )]
    public function logout(): void {}

    #[OA\Post(
        path: '/api/v1/auth/refresh',
        operationId: 'authRefresh',
        description: 'Issues a new JWT and blacklists the one that was presented. The access token must still be valid (not expired).',
        summary: 'Refresh access token',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token refreshed.',
                content: new OA\JsonContent(
                    required: ['success', 'message', 'data', 'errors', 'meta'],
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Token refreshed.'),
                        new OA\Property(
                            property: 'data',
                            required: ['user', 'token', 'token_type', 'expires_in'],
                            properties: [
                                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                                new OA\Property(property: 'token', type: 'string'),
                                new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                                new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                            ],
                            type: 'object',
                        ),
                        new OA\Property(property: 'errors', nullable: true, example: null),
                        new OA\Property(property: 'meta', nullable: true, example: null),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Missing, invalid, expired or blacklisted token.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'),
            ),
        ],
    )]
    public function refresh(): void {}
}
