<?php

declare(strict_types=1);

namespace IntoSQL;

use RuntimeException;

/** A conversion that was canceled while it was awaited. */
class ConversionCanceledException extends RuntimeException
{
    public function __construct(public readonly string $id)
    {
        parent::__construct("Conversion {$id} was canceled.");
    }
}
