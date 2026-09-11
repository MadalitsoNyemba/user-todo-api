<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * OpenAPI operations for /api/v1/todos. Kept off TodoController so controllers
 * stay thin.
 */
final class TodoEndpoints
{
    #[OA\Get(
        path: '/api/v1/todos',
        operationId: 'todosIndex',
        description: 'Lists the authenticated user’s todos. per_page defaults to 15 and is hard-capped at 100.',
        summary: 'List todos',
        security: [['bearerAuth' => []]],
        tags: ['Todos'],
        parameters: [
            new OA\Parameter(name: 'is_completed', in: 'query', required: false, description: 'Accepts 1 or 0.', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'priority', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['low', 'medium', 'high'])),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['due_date', 'created_at'], default: 'created_at')),
            new OA\Parameter(name: 'order', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'desc')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated todo list.',
                content: new OA\JsonContent(
                    required: ['success', 'message', 'data', 'errors', 'meta'],
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'OK.'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Todo')),
                        new OA\Property(property: 'errors', nullable: true, example: null),
                        new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
            new OA\Response(response: 422, description: 'Invalid query parameters.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function index(): void {}

    #[OA\Post(
        path: '/api/v1/todos',
        operationId: 'todosStore',
        description: 'Creates a todo synchronously and returns 201 with the resource.',
        summary: 'Create todo',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'is_completed', type: 'boolean'),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'priority', type: 'string', nullable: true, enum: ['low', 'medium', 'high']),
                ],
                type: 'object',
            ),
        ),
        tags: ['Todos'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Todo created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Todo created.'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Todo'),
                        new OA\Property(property: 'errors', nullable: true, example: null),
                        new OA\Property(property: 'meta', nullable: true, example: null),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
            new OA\Response(response: 422, description: 'Validation failed.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function store(): void {}

    #[OA\Post(
        path: '/api/v1/todos/bulk-complete',
        operationId: 'todosBulkComplete',
        description: 'Accepts up to 500 todo ids for async completion on the redis queue. Returns 202 with a job_id to poll at status_url. Foreign ids are skipped; already-completed todos are left alone so retries are idempotent.',
        summary: 'Bulk-complete todos',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ids'],
                properties: [
                    new OA\Property(
                        property: 'ids',
                        type: 'array',
                        maxItems: 500,
                        minItems: 1,
                        items: new OA\Items(type: 'integer'),
                    ),
                ],
                type: 'object',
            ),
        ),
        tags: ['Todos'],
        responses: [
            new OA\Response(
                response: 202,
                description: 'Accepted for processing.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Accepted for processing.'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'job_id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'status', type: 'string', example: 'queued'),
                                new OA\Property(property: 'status_url', type: 'string', format: 'uri'),
                            ],
                            type: 'object',
                        ),
                        new OA\Property(property: 'errors', nullable: true, example: null),
                        new OA\Property(property: 'meta', nullable: true, example: null),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
            new OA\Response(response: 422, description: 'Validation failed.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function bulkComplete(): void {}

    #[OA\Get(
        path: '/api/v1/todos/{id}',
        operationId: 'todosShow',
        description: 'Returns one todo owned by the caller. Another user’s id yields 404, not 403.',
        summary: 'Get todo',
        security: [['bearerAuth' => []]],
        tags: ['Todos'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Todo found.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'OK.'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Todo'),
                        new OA\Property(property: 'errors', nullable: true, example: null),
                        new OA\Property(property: 'meta', nullable: true, example: null),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
            new OA\Response(response: 404, description: 'Missing or not owned by the caller.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function show(): void {}

    #[OA\Patch(
        path: '/api/v1/todos/{id}',
        operationId: 'todosUpdate',
        description: 'Updates a todo. Toggling is_completed sets or clears completed_at.',
        summary: 'Update todo',
        security: [['bearerAuth' => []]],
        tags: ['Todos'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'is_completed', type: 'boolean'),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'priority', type: 'string', nullable: true, enum: ['low', 'medium', 'high']),
                ],
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Todo updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Todo updated.'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Todo'),
                        new OA\Property(property: 'errors', nullable: true, example: null),
                        new OA\Property(property: 'meta', nullable: true, example: null),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
            new OA\Response(response: 404, description: 'Missing or not owned by the caller.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
            new OA\Response(response: 422, description: 'Validation failed.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function update(): void {}

    #[OA\Delete(
        path: '/api/v1/todos/{id}',
        operationId: 'todosDestroy',
        description: 'Deletes a todo. Returns 200 with data null for envelope consistency.',
        summary: 'Delete todo',
        security: [['bearerAuth' => []]],
        tags: ['Todos'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Todo deleted.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Todo deleted.'),
                        new OA\Property(property: 'data', nullable: true, example: null),
                        new OA\Property(property: 'errors', nullable: true, example: null),
                        new OA\Property(property: 'meta', nullable: true, example: null),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
            new OA\Response(response: 404, description: 'Missing or not owned by the caller.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function destroy(): void {}
}
