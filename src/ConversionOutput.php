<?php

declare(strict_types=1);

namespace IntoSQL;

/** A done conversion's result: `url` downloads it for ten minutes. */
final class ConversionOutput
{
    public function __construct(
        public readonly int $bytes,
        public readonly string $url,
    ) {}

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(Data::int($data, 'bytes'), Data::string($data, 'url'));
    }
}
