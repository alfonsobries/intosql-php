<?php

declare(strict_types=1);

namespace IntoSQL;

/**
 * What a conversion did, read from `Conversion::$report`. For a database dump, `source`,
 * `tables` and `notConverted` are set, and `columns`, `fixes` and `problems` hold what
 * concerns the whole dump. For any other file, `source` is null and the lists are empty.
 */
final class JobReport
{
    /**
     * @param  string  $dialect  The target dialect, such as `postgresql`.
     * @param  list<ColumnReport>  $columns
     * @param  list<Fix>  $fixes
     * @param  list<Problem>  $problems
     * @param  SourceReport|null  $source  For a database dump: where it comes from.
     * @param  list<TableReport>  $tables  For a database dump: every table, with its own columns, fixes and problems.
     * @param  list<NotConverted>  $notConverted  For a database dump: the views, triggers, routines and events it did not carry.
     */
    public function __construct(
        public readonly string $dialect,
        public readonly string $table,
        public readonly array $columns,
        public readonly array $fixes,
        public readonly array $problems,
        public readonly ?SourceReport $source,
        public readonly array $tables,
        public readonly array $notConverted,
    ) {}

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        $source = Data::nullableMap($data, 'source');

        return new self(
            dialect: Data::string($data, 'dialect'),
            table: Data::string($data, 'table'),
            columns: ColumnReport::list($data, 'columns'),
            fixes: Fix::list($data, 'fixes'),
            problems: Problem::list($data, 'problems'),
            source: $source === null ? null : SourceReport::fromArray($source),
            tables: array_map(TableReport::fromArray(...), Data::maps($data, 'tables')),
            notConverted: array_map(NotConverted::fromArray(...), Data::maps($data, 'not_converted')),
        );
    }
}
