<?php

declare(strict_types=1);

namespace IntoSQL;

use RuntimeException;

/**
 * An answer from the IntoSQL API that wasn't a success. `errorCode` doesn't change between
 * versions; `getCode()` is the HTTP status.
 */
class IntoSQLException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $fields  Per field messages when the code is `invalid_request`.
     * @param  string|null  $requestId  The answer's `X-Request-Id`; quote it when you write to IntoSQL.
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $fields = [],
        public readonly ?string $requestId = null,
    ) {
        parent::__construct($message, $status);
    }
}
