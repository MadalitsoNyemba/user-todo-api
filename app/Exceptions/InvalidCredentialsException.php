<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * Deliberately says nothing about which half was wrong. Distinguishing an
 * unknown email from a bad password tells an attacker which addresses are
 * registered.
 */
class InvalidCredentialsException extends ApiException
{
    public function __construct()
    {
        parent::__construct('These credentials do not match our records.', Response::HTTP_UNAUTHORIZED);
    }
}
