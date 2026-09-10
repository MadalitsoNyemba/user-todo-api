<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Callbacks are matched in registration order, so this method reads from
     * most specific to least: deliberate application failures first, then the
     * framework exceptions worth a tailored message, then a generic HTTP
     * catch, then everything else.
     *
     * - ApiException: anything the application raises on purpose.
     * - AccessDeniedHttpException / NotFoundHttpException: prepareException()
     *   rewrites AuthorizationException and ModelNotFoundException into these
     *   Symfony classes before any callback runs, so those are the ones to
     *   target; registering the originals would be dead code. The not-found
     *   message is deliberately generic — Laravel's own message names the
     *   model class and id, which tells a caller about internals they
     *   should not see, and it keeps another user's record indistinguishable
     *   from one that does not exist, which is what makes returning 404
     *   instead of 403 for someone else's todo worth anything.
     * - MethodNotAllowedHttpException: the Allow header is preserved so a
     *   client can discover what is valid.
     * - ThrottleRequestsException: Retry-After and the rate limit headers
     *   come from the exception.
     * - HttpExceptionInterface: abort() throws a plain HttpException for
     *   every status except 404, so without this an abort(403), abort(409)
     *   or abort(422) would match none of the callbacks above and fall to
     *   the catch-all, losing its status and message to a generic 500. This
     *   must stay below the specific handlers above: NotFoundHttpException,
     *   MethodNotAllowedHttpException and AccessDeniedHttpException all
     *   implement this interface and would otherwise be swallowed here,
     *   taking their tailored messages with them.
     * - Throwable: registered last. Anything reaching here is unplanned, so
     *   the client gets a fixed message and the detail is gated on debug.
     */
    public function register(): void
    {
        $this->renderable(function (ApiException $e, Request $request) {
            return $this->shouldRenderJson($request)
                ? ApiResponse::error($e->getMessage(), $e->status(), $e->errors())
                : null;
        });

        $this->renderable(function (ValidationException $e, Request $request) {
            return $this->shouldRenderJson($request)
                ? ApiResponse::validationError($e->errors())
                : null;
        });

        $this->renderable(function (AuthenticationException $e, Request $request) {
            return $this->shouldRenderJson($request)
                ? ApiResponse::error('Unauthenticated.', Response::HTTP_UNAUTHORIZED)
                : null;
        });

        $this->renderable(function (AccessDeniedHttpException $e, Request $request) {
            return $this->shouldRenderJson($request)
                ? ApiResponse::error('This action is unauthorized.', Response::HTTP_FORBIDDEN)
                : null;
        });

        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            return $this->shouldRenderJson($request)
                ? ApiResponse::error('Resource not found.', Response::HTTP_NOT_FOUND)
                : null;
        });

        $this->renderable(function (MethodNotAllowedHttpException $e, Request $request) {
            if (! $this->shouldRenderJson($request)) {
                return null;
            }

            return ApiResponse::error(
                'This method is not allowed for the requested route.',
                Response::HTTP_METHOD_NOT_ALLOWED,
            )->withHeaders($e->getHeaders());
        });

        $this->renderable(function (ThrottleRequestsException $e, Request $request) {
            if (! $this->shouldRenderJson($request)) {
                return null;
            }

            return ApiResponse::error(
                'Too many requests. Please slow down.',
                Response::HTTP_TOO_MANY_REQUESTS,
            )->withHeaders($e->getHeaders());
        });

        $this->renderable(function (HttpExceptionInterface $e, Request $request) {
            if (! $this->shouldRenderJson($request)) {
                return null;
            }

            $status = $e->getStatusCode();

            $message = $e->getMessage() !== ''
                ? $e->getMessage()
                : (Response::$statusTexts[$status] ?? 'The request could not be completed.');

            return ApiResponse::error($message, $status)->withHeaders($e->getHeaders());
        });

        $this->renderable(function (Throwable $e, Request $request) {
            if (! $this->shouldRenderJson($request)) {
                return null;
            }

            return ApiResponse::error(
                'An unexpected error occurred.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                config('app.debug') ? [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            );
        });
    }

    /**
     * Any request under /api is answered in JSON whether or not the caller
     * asked for it. expectsJson() alone is not enough: an unmatched route
     * throws before the api middleware group ever runs, so the ForceJsonResponse
     * middleware never gets the chance to set the header.
     */
    protected function shouldRenderJson(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
