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
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Callbacks are matched in registration order, so this method reads from
     * most specific to least. Reordering it changes behaviour, in two places
     * that fail silently:
     *
     * - TokenExpiredException and TokenInvalidException both extend
     *   JWTException, so the general one must stay last of the three or the
     *   distinct messages are lost. An expired token and a malformed one
     *   demand different things of a client, refresh or log in again, so they
     *   are worth telling apart.
     * - NotFoundHttpException, MethodNotAllowedHttpException and
     *   AccessDeniedHttpException all implement HttpExceptionInterface, so
     *   they must stay above it or their tailored messages are swallowed.
     */
    public function register(): void
    {
        $this->renderable(function (TokenExpiredException $e, Request $request) {
            return $this->shouldRenderJson($request)
                ? ApiResponse::error('Token has expired. Refresh it or log in again.', Response::HTTP_UNAUTHORIZED)
                : null;
        });

        $this->renderable(function (TokenInvalidException $e, Request $request) {
            return $this->shouldRenderJson($request)
                ? ApiResponse::error('Token is invalid.', Response::HTTP_UNAUTHORIZED)
                : null;
        });

        // Also covers a missing or unparseable token, and a blacklisted one.
        $this->renderable(function (JWTException $e, Request $request) {
            return $this->shouldRenderJson($request)
                ? ApiResponse::error('Token not provided or could not be parsed.', Response::HTTP_UNAUTHORIZED)
                : null;
        });

        // Anything the application raises on purpose.
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

        // prepareException() rewrites several exceptions before any callback
        // runs, so the Symfony classes are the ones to target here:
        // AuthorizationException arrives as AccessDeniedHttpException, and
        // ModelNotFoundException arrives as NotFoundHttpException. Registering
        // the originals would be dead code.
        $this->renderable(function (AccessDeniedHttpException $e, Request $request) {
            return $this->shouldRenderJson($request)
                ? ApiResponse::error('This action is unauthorized.', Response::HTTP_FORBIDDEN)
                : null;
        });

        // The generic message is deliberate. Laravel's own not-found message
        // names the model class and id, which tells a caller about internals
        // they should not see. It also keeps another user's record
        // indistinguishable from one that does not exist, which is what makes
        // returning 404 instead of 403 for someone else's todo worth anything.
        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            return $this->shouldRenderJson($request)
                ? ApiResponse::error('Resource not found.', Response::HTTP_NOT_FOUND)
                : null;
        });

        $this->renderable(function (MethodNotAllowedHttpException $e, Request $request) {
            if (! $this->shouldRenderJson($request)) {
                return null;
            }

            // Preserve the Allow header so a client can discover what is valid.
            return ApiResponse::error(
                'This method is not allowed for the requested route.',
                Response::HTTP_METHOD_NOT_ALLOWED,
            )->withHeaders($e->getHeaders());
        });

        $this->renderable(function (ThrottleRequestsException $e, Request $request) {
            if (! $this->shouldRenderJson($request)) {
                return null;
            }

            // Retry-After and the rate limit headers come from the exception.
            return ApiResponse::error(
                'Too many requests. Please slow down.',
                Response::HTTP_TOO_MANY_REQUESTS,
            )->withHeaders($e->getHeaders());
        });

        // abort() throws a plain HttpException for every status except 404, so
        // without this an abort(403), abort(409) or abort(422) would match none
        // of the callbacks above and fall to the catch-all, losing its status
        // and message to a generic 500.
        //
        // This must stay below the specific handlers: NotFoundHttpException,
        // MethodNotAllowedHttpException and AccessDeniedHttpException all
        // implement this interface and would otherwise be swallowed here,
        // taking their tailored messages with them.
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

        // Registered last. Anything reaching here is unplanned, so the client
        // gets a fixed message and the detail is gated on debug.
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
