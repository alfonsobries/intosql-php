<?php

declare(strict_types=1);

namespace IntoSQL;

/** One column as it was read and as it was written. */
final class ColumnReport
{
    /**
     * @param  string  $sourceName  The name in the file.
     * @param  string  $name  The name in the SQL.
     * @param  string  $sqlType  The SQL type in the target dialect, such as `numeric(12,2)`.
     * @param  string  $kind  Such as `integer`, `decimal`, `date`, `text` or `boolean`; new kinds may be added within v1.
     * @param  int|null  $precision  Total digits, for decimals.
     * @param  int|null  $scale  Digits after the point, for decimals.
     * @param  int|null  $maxChars  The longest value in characters, for text.
     * @param  list<int>  $fixes  Numbers of the fixes that touched this column.
     * @param  bool  $excluded  Left out of the output by the request.
     * @param  bool  $asText  Kept as text by the request.
     */
    public function __construct(
        public readonly string $sourceName,
        public readonly string $name,
        public readonly string $sqlType,
        public readonly string $kind,
        public readonly ?int $precision,
        public readonly ?int $scale,
        public readonly ?int $maxChars,
        public readonly bool $nullable,
        public readonly array $fixes,
        public readonly bool $excluded,
        public readonly bool $asText,
    ) {}

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            sourceName: Data::string($data, 'source_name'),
            name: Data::string($data, 'name'),
            sqlType: Data::string($data, 'sql_type'),
            kind: Data::string($data, 'kind'),
            precision: Data::nullableInt($data, 'precision'),
            scale: Data::nullableInt($data, 'scale'),
            maxChars: Data::nullableInt($data, 'max_chars'),
            nullable: Data::bool($data, 'nullable'),
            fixes: Data::ints($data, 'fixes'),
            excluded: Data::bool($data, 'excluded'),
            asText: Data::bool($data, 'as_text'),
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
