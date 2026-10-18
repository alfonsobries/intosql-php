<?php

declare(strict_types=1);

namespace IntoSQL;

/** Why a conversion failed. `code` is stable; new codes may be added within v1. */
final class ConversionFailure
{
    /**
     * @param  list<array<mixed>>  $problems  What the engine found that stopped it, such as a spec value out of range.
     */
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly array $problems = [],
    ) {}

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::string($data, 'code'),
            Data::string($data, 'message'),
            Data::maps($data, 'problems'),
        );
    }
}
