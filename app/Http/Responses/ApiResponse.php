<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every response this API emits, success or failure, is built here.
 *
 * The envelope keys are identical in both cases so a client can parse one
 * shape and branch on `success` rather than guessing at the payload.
 */
final class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'OK.',
        int $status = Response::HTTP_OK,
        ?array $meta = null,
    ): JsonResponse {
        return self::envelope(true, $message, $data, null, $meta, $status);
    }

    public static function created(mixed $data = null, string $message = 'Created.'): JsonResponse
    {
        return self::success($data, $message, Response::HTTP_CREATED);
    }

    /**
     * For work handed to the queue. The caller gets a job reference to poll
     * rather than a result, so the response is deliberately not 200.
     */
    public static function accepted(mixed $data = null, string $message = 'Accepted for processing.'): JsonResponse
    {
        return self::success($data, $message, Response::HTTP_ACCEPTED);
    }

    public static function paginated(
        LengthAwarePaginator $paginator,
        string $message = 'OK.',
        ?callable $transform = null,
    ): JsonResponse {
        $items = $paginator->items();

        return self::success(
            $transform ? array_map($transform, $items) : $items,
            $message,
            Response::HTTP_OK,
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public static function error(
        string $message,
        int $status = Response::HTTP_INTERNAL_SERVER_ERROR,
        ?array $errors = null,
    ): JsonResponse {
        return self::envelope(false, $message, null, $errors, null, $status);
    }

    public static function validationError(
        array $errors,
        string $message = 'The given data was invalid.',
    ): JsonResponse {
        return self::error($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    private static function envelope(
        bool $success,
        string $message,
        mixed $data,
        ?array $errors,
        ?array $meta,
        int $status,
    ): JsonResponse {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
            'meta' => $meta,
        ], $status);
    }
}
