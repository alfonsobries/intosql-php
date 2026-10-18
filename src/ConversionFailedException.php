<?php

declare(strict_types=1);

namespace IntoSQL;

use RuntimeException;

/** A conversion that ended as `failed`, with the stable code and the engine's problems. */
class ConversionFailedException extends RuntimeException
{
    public readonly string $errorCode;

    /** @var list<array<mixed>> */
    public readonly array $problems;

    public function __construct(public readonly string $id, ?ConversionFailure $failure)
    {
        parent::__construct($failure->message ?? "Conversion {$id} failed.");
        $this->errorCode = $failure->code ?? 'internal_error';
        $this->problems = $failure->problems ?? [];
    }
}
