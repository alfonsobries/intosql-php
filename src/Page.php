<?php

declare(strict_types=1);

namespace IntoSQL;

/** One page of conversions; `next` is the address of the following one, or null on the last. */
final class Page
{
    /** @param list<Conversion> $data */
    public function __construct(
        public readonly array $data,
        public readonly ?string $next,
        public readonly int $perPage,
        public readonly ?string $nextCursor,
        public readonly ?string $prevCursor,
    ) {}

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        $meta = Data::map($data, 'meta');

        return new self(
            array_map(Conversion::fromArray(...), Data::maps($data, 'data')),
            Data::nullableString(Data::map($data, 'links'), 'next'),
            Data::int($meta, 'per_page'),
            Data::nullableString($meta, 'next_cursor'),
            Data::nullableString($meta, 'prev_cursor'),
        );
    }
}
