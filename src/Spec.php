<?php

declare(strict_types=1);

namespace IntoSQL;

/** Builds conversion requests (`JobSpec`) with IntoSQL's defaults. */
final class Spec
{
    /**
     * A conversion request with IntoSQL's defaults: types inferred, names in snake_case,
     * batched inserts. Change the array it returns for anything else.
     *
     * @param  string  $dialect  `postgresql`, `mysql`, `mariadb`, `sqlite`, `sql_server` or `oracle`.
     * @param  string  $format  `sql`, `csv` or `json`.
     * @return array{
     *     version: int,
     *     input: array{sheet: string|null, header_row: int|null, delimiter: string|null, encoding: string|null},
     *     output: array{format: string, dialect: string, table: string|null, schema: string|null, create_table: bool, column_names: string, style: string, batch_rows: int},
     *     inference: array{sample_rows: int, date_order: string, keep_leading_zeros: bool, locale: string|null},
     *     columns: list<array<string, mixed>>
     * }
     */
    public static function make(
        string $dialect,
        string $format = 'sql',
        ?string $table = null,
        ?string $schema = null,
        ?string $sheet = null,
        bool $createTable = true,
        int $batchRows = 500,
    ): array {
        return [
            'version' => 1,
            'input' => [
                'sheet' => $sheet,
                'header_row' => null,
                'delimiter' => null,
                'encoding' => null,
            ],
            'output' => [
                'format' => $format,
                'dialect' => $dialect,
                'table' => $table,
                'schema' => $schema,
                'create_table' => $createTable,
                'column_names' => 'snake_case',
                'style' => 'inserts',
                'batch_rows' => $batchRows,
            ],
            'inference' => [
                'sample_rows' => 10_000,
                'date_order' => 'auto',
                'keep_leading_zeros' => true,
                'locale' => null,
            ],
            'columns' => [],
        ];
    }
}
