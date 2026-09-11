<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a raced register hits the users.email unique index after
 * FormRequest uniqueness has already passed. Shape matches ValidationException
 * so the client sees the same 422 as the validation path.
 */
class DuplicateEmailException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            'The given data was invalid.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
            ['email' => ['The email has already been taken.']],
        );
    }
}
