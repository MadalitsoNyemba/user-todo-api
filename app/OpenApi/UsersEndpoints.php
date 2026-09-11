<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Admin user directory. Kept off UserController so controllers stay thin.
 */
final class UsersEndpoints
{
    #[OA\Get(
        path: '/api/v1/users',
        operationId: 'usersIndex',
        description: 'Paginated list of every user. Restricted to the admin role via UserPolicy::viewAny. A normal authenticated user receives 403 (unlike cross-tenant todos, which stay 404).',
        summary: 'List users (admin)',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'per_page',
                description: 'Page size (capped at 100).',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 15),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated users.',
                content: new OA\JsonContent(
                    required: ['success', 'message', 'data', 'errors', 'meta'],
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'OK.'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/User'),
                        ),
                        new OA\Property(property: 'errors', nullable: true, example: null),
                        new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
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
                response: 403,
                description: 'Authenticated but not an admin.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'),
            ),
        ],
    )]
    public function index(): void {}
}
