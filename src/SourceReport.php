<?php

declare(strict_types=1);

namespace IntoSQL;

/** Where a database dump comes from. */
final class SourceReport
{
    /**
     * @param  string  $dialect  The database that wrote the dump, such as `mysql` or `mariadb`.
     * @param  string|null  $version  The server version the dump states, such as `8.0.40`.
     * @param  string|null  $producer  The program that wrote it, such as `mysqldump` or `phpMyAdmin`.
     * @param  string|null  $charset  The character set the dump declares, or the one read from its bytes.
     * @param  list<string>  $databases  The databases the dump holds, in order.
     */
    public function __construct(
        public readonly string $dialect,
        public readonly ?string $version,
        public readonly ?string $producer,
        public readonly ?string $charset,
        public readonly array $databases,
    ) {}

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::string($data, 'dialect'),
            Data::nullableString($data, 'version'),
            Data::nullableString($data, 'producer'),
            Data::nullableString($data, 'charset'),
            Data::strings($data, 'databases'),
        );
    }
}
