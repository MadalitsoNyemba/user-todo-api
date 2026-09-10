<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for failures this application raises on purpose, as opposed to
 * ones it suffers. Carries the status and any field-level detail so services
 * can throw without knowing anything about HTTP responses.
 */
class ApiException extends Exception
{
    public function __construct(
        string $message = 'The request could not be completed.',
        protected int $status = Response::HTTP_BAD_REQUEST,
        protected ?array $errors = null,
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errors(): ?array
    {
        return $this->errors;
    }
}
