<?php

declare(strict_types=1);

namespace IntoSQL;

/** A correction the engine made, numbered like a proofreader's mark. `code` is a string; new codes may be added within v1. */
final class Fix
{
    /**
     * @param  string  $code  Such as `leading_zeros_kept` or `unsigned_widened`; see the Fixes docs.
     * @param  int|null  $row  1-based row in the file, when the fix is about one row.
     * @param  array<string, string>  $args  Arguments for the fix's message.
     */
    public function __construct(
        public readonly int $number,
        public readonly string $code,
        public readonly ?string $column,
        public readonly ?int $row,
        public readonly array $args,
    ) {}

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::int($data, 'number'),
            Data::string($data, 'code'),
            Data::nullableString($data, 'column'),
            Data::nullableInt($data, 'row'),
            Data::stringMap($data, 'args'),
        );
    }

    /**
     * @param  array<mixed>  $data
     * @return list<self>
     */
    public static function list(array $data, string $key): array
    {
        return array_map(self::fromArray(...), Data::maps($data, $key));
    }
}
