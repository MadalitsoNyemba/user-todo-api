<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clients of this API do not have to remember to send an Accept header.
 *
 * Note this cannot help with an unmatched route: group middleware only runs
 * after a route matches, and the router throws NotFoundHttpException before
 * that. The exception handler covers that case unconditionally instead, since
 * this application has no web routes for a non-JSON response to be correct for.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
