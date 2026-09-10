<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Shared response and resource shapes. Mirrors ApiResponse and UserResource.
 *
 * Todo and JobStatus schemas are intentionally absent — those models land in
 * later issues; documenting a shape that does not exist yet would mislead.
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
    required: ['id', 'name', 'email', 'created_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Alice Example'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alice@example.com'),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            example: '2026-09-10T19:00:00+00:00',
        ),
    ],
    type: 'object',
)]
final class Schemas {}
