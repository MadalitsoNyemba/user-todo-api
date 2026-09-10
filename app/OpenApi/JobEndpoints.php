<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * OpenAPI for job status polling. The job that writes these rows lands later.
 */
final class JobEndpoints
{
    #[OA\Get(
        path: '/api/v1/jobs/{uuid}',
        operationId: 'jobsShow',
        description: 'Returns status and progress for an async job owned by the caller. Another user’s uuid yields 404, not 403.',
        summary: 'Get job status',
        security: [['bearerAuth' => []]],
        tags: ['Jobs'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Job status.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'OK.'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/JobStatus'),
                        new OA\Property(property: 'errors', nullable: true, example: null),
                        new OA\Property(property: 'meta', nullable: true, example: null),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'),
            ),
            new OA\Response(
                response: 404,
                description: 'Missing or not owned by the caller.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'),
            ),
        ],
    )]
    public function show(): void {}
}
