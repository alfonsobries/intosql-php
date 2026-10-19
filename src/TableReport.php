<?php

declare(strict_types=1);

namespace IntoSQL;

/** One table of a database dump, as read and as written. */
final class TableReport
{
    /**
     * @param  string  $sourceName  The name in the dump.
     * @param  string  $name  The name in the SQL.
     * @param  string|null  $schema  The schema it goes in, when not the default one.
     * @param  int  $rows  Rows in the dump; rows written when converted.
     * @param  list<ColumnReport>  $columns
     * @param  list<Fix>  $fixes  Fixes about this table and its columns, numbered across the whole report.
     * @param  list<Problem>  $problems
     */
    public function __construct(
        public readonly string $sourceName,
        public readonly string $name,
        public readonly ?string $schema,
        public readonly int $rows,
        public readonly array $columns,
        public readonly array $fixes,
        public readonly array $problems,
    ) {}

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            sourceName: Data::string($data, 'source_name'),
            name: Data::string($data, 'name'),
            schema: Data::nullableString($data, 'schema'),
            rows: Data::int($data, 'rows'),
            columns: ColumnReport::list($data, 'columns'),
            fixes: Fix::list($data, 'fixes'),
            problems: Problem::list($data, 'problems'),
        );
    }
}
