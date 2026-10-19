<?php

declare(strict_types=1);

use IntoSQL\ColumnReport;
use IntoSQL\Conversion;
use IntoSQL\Fix;
use IntoSQL\NotConverted;
use IntoSQL\Problem;
use IntoSQL\TableReport;

/** @return array<mixed> A real engine report: a MySQL dump converted to PostgreSQL. */
function dumpReport(): array
{
    $report = json_decode((string) file_get_contents(__DIR__.'/fixtures/dump_report.json'), true, flags: JSON_THROW_ON_ERROR);

    return is_array($report) ? $report : [];
}

it('reads the report of a database dump', function (): void {
    $report = Conversion::fromArray(conversion(['status' => 'done', 'report' => dumpReport()]))->jobReport();

    expect($report?->dialect)->toBe('postgresql')
        ->and($report?->table)->toBe('')
        ->and($report?->columns)->toBe([])
        ->and($report?->fixes)->toBe([])
        ->and($report?->problems)->toHaveCount(1)
        ->and($report?->problems[0]->code)->toBe('not_converted_objects')
        ->and($report?->problems[0]->args)->toBe(['count' => '2']);

    $source = $report?->source;
    expect($source?->dialect)->toBe('mysql')
        ->and($source?->version)->toBe('8.0.46')
        ->and($source?->producer)->toBe('mysqldump')
        ->and($source?->charset)->toBe('utf8mb4')
        ->and($source?->databases)->toBe([]);

    $tables = $report->tables ?? [];
    expect(array_map(fn (TableReport $table): array => [$table->sourceName, $table->name, $table->schema, $table->rows], $tables))
        ->toBe([['customers', 'customers', null, 8], ['orders', 'orders', null, 10]]);

    $customers = $tables[0];
    expect($customers->columns)->toHaveCount(7)
        ->and(array_map(fn (ColumnReport $column): string => $column->name, $customers->columns))
        ->toBe(['id', 'name', 'email', 'country', 'is_active', 'signed_up', 'created_at']);

    $id = $customers->columns[0];
    expect([$id->sourceName, $id->sqlType, $id->kind, $id->nullable, $id->fixes, $id->excluded, $id->asText])
        ->toBe(['id', 'bigint', 'bigint', false, [1], false, false])
        ->and([$id->precision, $id->scale, $id->maxChars])->toBe([null, null, null])
        ->and($customers->columns[1]->maxChars)->toBe(16)
        ->and($customers->columns[5]->nullable)->toBeTrue()
        ->and($customers->columns[5]->fixes)->toBe([2, 3]);

    expect(array_map(fn (Fix $fix): array => [$fix->number, $fix->code, $fix->column, $fix->row], $customers->fixes))
        ->toBe([[1, 'unsigned_widened', 'id', null], [2, 'not_null_relaxed', 'signed_up', null], [3, 'zero_date_default', 'signed_up', null]]);

    $problem = $customers->problems[0];
    expect([$problem->code, $problem->severity, $problem->column, $problem->row])
        ->toBe(['invalid_date_null', 'warning', 'signed_up', null])
        ->and($problem->args['value'])->toBe('0000-00-00');

    $orders = $tables[1];
    expect($orders->columns)->toHaveCount(9)
        ->and(array_map(fn (Fix $fix): string => $fix->code, $orders->fixes))
        ->toBe(['unsigned_widened', 'unsigned_widened', 'unsigned_widened', 'enum_invalid_value', 'on_update_trigger'])
        ->and($orders->fixes[1]->args['declared'])->toBe('int unsigned')
        ->and(array_map(fn (Problem $problem): string => $problem->code, $orders->problems))->toBe(['invalid_date_null']);

    expect(array_map(fn (NotConverted $object): array => [$object->kind, $object->name], $report->notConverted ?? []))
        ->toBe([['view', 'active_customers'], ['trigger', 'orders_bi']]);
});

it('reads a report without dump fields as null and empty lists', function (): void {
    $report = Conversion::fromArray(conversion(['status' => 'done', 'report' => [
        'version' => 1,
        'dialect' => 'mysql',
        'table' => 'ventas',
        'columns' => [['source_name' => 'Total', 'name' => 'total', 'sql_type' => 'decimal(12,2)', 'kind' => 'decimal', 'precision' => 12, 'scale' => 2, 'nullable' => true, 'fixes' => [1]]],
        'rows' => ['read' => 2, 'converted' => 2, 'rejected' => 0],
        'fixes' => [['number' => 1, 'code' => 'decimal_comma_read', 'column' => 'total', 'args' => []]],
        'problems' => [['code' => 'invalid_number', 'severity' => 'rejected', 'column' => 'total', 'row' => 3, 'args' => ['value' => 'n/a']]],
    ]]))->jobReport();

    expect($report?->source)->toBeNull()
        ->and($report?->tables)->toBe([])
        ->and($report?->notConverted)->toBe([])
        ->and($report?->table)->toBe('ventas')
        ->and($report?->columns[0]->precision)->toBe(12)
        ->and($report?->columns[0]->scale)->toBe(2)
        ->and($report?->columns[0]->excluded)->toBeFalse()
        ->and($report?->fixes[0]->code)->toBe('decimal_comma_read')
        ->and($report?->problems[0]->row)->toBe(3);

    expect(Conversion::fromArray(conversion())->jobReport())->toBeNull();
});

it('keeps kinds and codes it does not know, and skips values of the wrong type', function (): void {
    $report = Conversion::fromArray(conversion(['report' => [
        'source' => ['dialect' => 'cockroachdb', 'version' => 8, 'databases' => ['shop', 7, null]],
        'tables' => [[
            'source_name' => 'items',
            'name' => 'items',
            'schema' => 'shop',
            'rows' => '5',
            'columns' => [['name' => 'shape', 'kind' => 'hologram', 'fixes' => [1, 'two'], 'nullable' => 'yes'], 'not a column'],
            'fixes' => [['number' => 1, 'code' => 'fix_from_the_future', 'args' => ['count' => '1', 'n' => 2]]],
            'problems' => [['code' => 'problem_from_the_future', 'severity' => 'notice']],
        ]],
        'not_converted' => [['kind' => 'sequence', 'name' => 'items_seq'], 'not an object'],
    ]]))->jobReport();

    expect($report?->dialect)->toBe('')
        ->and($report?->source?->dialect)->toBe('cockroachdb')
        ->and($report?->source?->version)->toBeNull()
        ->and($report?->source?->databases)->toBe(['shop']);

    $table = $report->tables[0] ?? null;
    expect($table?->schema)->toBe('shop')
        ->and($table?->rows)->toBe(0)
        ->and($table?->columns)->toHaveCount(1)
        ->and($table?->columns[0]->kind)->toBe('hologram')
        ->and($table?->columns[0]->fixes)->toBe([1])
        ->and($table?->columns[0]->nullable)->toBeFalse()
        ->and($table?->fixes[0]->code)->toBe('fix_from_the_future')
        ->and($table?->fixes[0]->args)->toBe(['count' => '1'])
        ->and($table?->problems[0]->code)->toBe('problem_from_the_future')
        ->and($table?->problems[0]->severity)->toBe('notice')
        ->and($table?->problems[0]->args)->toBe([]);

    expect($report?->notConverted)->toHaveCount(1)
        ->and($report?->notConverted[0]->kind)->toBe('sequence');
});
