<?php

declare(strict_types=1);

namespace IntoSQL;

/** Something that stopped the conversion, left a row out or needs a look. `code` is a string; new codes may be added within v1. */
final class Problem
{
    /**
     * @param  string  $code  Such as `invalid_date` or `not_converted_objects`.
     * @param  string  $severity  `error`, `rejected` or `warning`.
     * @param  array<string, string>  $args  Arguments for the problem's message and hint.
     */
    public function __construct(
        public readonly string $code,
        public readonly string $severity,
        public readonly ?string $column,
        public readonly ?int $row,
        public readonly array $args,
    ) {}

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::string($data, 'code'),
            Data::string($data, 'severity'),
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
