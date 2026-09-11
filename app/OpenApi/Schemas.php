<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Shared response and resource shapes. Mirrors ApiResponse and resources.
 */
#[OA\Schema(
    schema: 'SuccessEnvelope',
    description: 'Envelope returned for successful responses.',
    required: ['success', 'message', 'data', 'errors', 'meta'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'OK.'),
        new OA\Property(
            property: 'data',
            description: 'Payload for the operation. Shape varies by endpoint.',
            nullable: true,
        ),
        new OA\Property(
            property: 'errors',
            description: 'Always null on success.',
            nullable: true,
            example: null,
        ),
        new OA\Property(
            property: 'meta',
            description: 'Pagination meta when listing; otherwise null.',
            nullable: true,
            oneOf: [
                new OA\Schema(ref: '#/components/schemas/PaginationMeta'),
                new OA\Schema(type: 'null'),
            ],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ErrorEnvelope',
    description: 'Envelope returned for failed responses, including 422 validation.',
    required: ['success', 'message', 'data', 'errors', 'meta'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(
            property: 'data',
            description: 'Always null on failure.',
            nullable: true,
            example: null,
        ),
        new OA\Property(
            property: 'errors',
            description: 'Field errors keyed by attribute for 422; null for other failures.',
            type: 'object',
            nullable: true,
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string'),
            ),
            example: ['email' => ['The email has already been taken.']],
        ),
        new OA\Property(
            property: 'meta',
            description: 'Always null on failure.',
            nullable: true,
            example: null,
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    description: 'Meta attached by ApiResponse::paginated().',
    required: ['current_page', 'per_page', 'total', 'last_page'],
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'per_page', type: 'integer', example: 15),
        new OA\Property(property: 'total', type: 'integer', example: 42),
        new OA\Property(property: 'last_page', type: 'integer', example: 3),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'User',
    description: 'Public user representation (UserResource). Password is never included.',
    required: ['id', 'name', 'email', 'role', 'created_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Alice Example'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alice@example.com'),
        new OA\Property(property: 'role', type: 'string', enum: ['user', 'admin'], example: 'user'),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            example: '2026-09-10T19:00:00+00:00',
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Todo',
    description: 'TodoResource. user_id is never exposed. Cross-tenant access returns 404, not 403.',
    required: ['id', 'title', 'description', 'is_completed', 'completed_at', 'due_date', 'priority', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'title', type: 'string', example: 'Ship profile endpoints'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Keep controllers thin.'),
        new OA\Property(property: 'is_completed', type: 'boolean', example: false),
        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true, example: '2030-01-15'),
        new OA\Property(property: 'priority', type: 'string', nullable: true, enum: ['low', 'medium', 'high'], example: 'high'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'JobStatus',
    description: 'JobStatusResource. Polled after an async write. user_id and internal id are never exposed; UUIDs are not enumerable across tenants.',
    required: ['uuid', 'type', 'status', 'total', 'processed', 'failed_count', 'result', 'error', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
        new OA\Property(property: 'type', type: 'string', example: 'bulk_complete_todos'),
        new OA\Property(property: 'status', type: 'string', enum: ['queued', 'processing', 'completed', 'failed']),
        new OA\Property(property: 'total', type: 'integer', example: 10),
        new OA\Property(property: 'processed', type: 'integer', example: 4),
        new OA\Property(property: 'failed_count', type: 'integer', example: 0),
        new OA\Property(property: 'result', type: 'object', nullable: true),
        new OA\Property(property: 'error', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
final class Schemas {}
