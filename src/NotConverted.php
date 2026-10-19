<?php

declare(strict_types=1);

namespace IntoSQL;

/** An object of a dump that the conversion did not carry: it is recreated by hand. */
final class NotConverted
{
    /**
     * @param  string  $kind  `view`, `trigger`, `procedure`, `function` or `event`; new kinds may be added within v1.
     * @param  string  $name  The name in the dump, with its database when the dump names more than one.
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $name,
    ) {}

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(Data::string($data, 'kind'), Data::string($data, 'name'));
    }
}
