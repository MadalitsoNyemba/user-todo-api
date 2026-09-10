<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * OpenAPI operations for /api/v1/me. Kept off ProfileController so controllers
 * stay thin under review. No list-users operation — that is deferred.
 */
final class ProfileEndpoints
{
    #[OA\Get(
        path: '/api/v1/me',
        operationId: 'profileShow',
        description: 'Returns the authenticated user’s profile. Never returns another user.',
        summary: 'Get own profile',
        security: [['bearerAuth' => []]],
        tags: ['Profile'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current profile.',
                content: new OA\JsonContent(
                    required: ['success', 'message', 'data', 'errors', 'meta'],
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'OK.'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
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
    public function show(): void {}

    #[OA\Patch(
        path: '/api/v1/me',
        operationId: 'profileUpdate',
        description: 'Updates the authenticated user’s name and email. Password is optional; when sent it must include password_confirmation. Changing email or password requires current_password; those changes bump token_version and blacklist the current JWT so every prior access token stops working — the client must sign in again.',
        summary: 'Update own profile',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Alice Example'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'alice@example.com'),
                    new OA\Property(
                        property: 'current_password',
                        description: 'Required when email or password is changing.',
                        type: 'string',
                        format: 'password',
                    ),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'correct-horse-battery'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'correct-horse-battery'),
                ],
                type: 'object',
            ),
        ),
        tags: ['Profile'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile updated. After an email or password change the previous bearer token is no longer valid.',
                content: new OA\JsonContent(
                    required: ['success', 'message', 'data', 'errors', 'meta'],
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Profile updated.'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
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
            new OA\Response(
                response: 422,
                description: 'Validation failed (including email taken, or missing/wrong current_password).',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'),
            ),
        ],
    )]
    public function update(): void {}

    #[OA\Delete(
        path: '/api/v1/me',
        operationId: 'profileDestroy',
        description: 'Deletes the authenticated user’s account and blacklists the current JWT. Todo cascade behaviour is owned by the todos migration and is not asserted here yet.',
        summary: 'Delete own account',
        security: [['bearerAuth' => []]],
        tags: ['Profile'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Account deleted.',
                content: new OA\JsonContent(
                    required: ['success', 'message', 'data', 'errors', 'meta'],
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Account deleted.'),
                        new OA\Property(property: 'data', nullable: true, example: null),
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
    public function destroy(): void {}
}
