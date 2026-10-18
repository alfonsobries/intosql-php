<?php

declare(strict_types=1);

namespace IntoSQL;

/** Bytes of conversions the plan includes this month, used and left. */
final class Usage
{
    public function __construct(
        public readonly int $limit,
        public readonly int $used,
        public readonly int $left,
        public readonly string $periodStart,
    ) {}

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::int($data, 'limit'),
            Data::int($data, 'used'),
            Data::int($data, 'left'),
            Data::string($data, 'period_start'),
        );
    }
}
