<?php

namespace Demo;

/**
 * An error that is reported to the front-end as a JSON error object.
 * The message and detail must never contain secrets.
 */
final class ApiException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly mixed $detail = null,
    ) {
        parent::__construct($message);
    }
}
