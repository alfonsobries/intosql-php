<?php

declare(strict_types=1);

use IntoSQL\Spec;

it("fills IntoSQL's defaults", function (): void {
    expect(Spec::make(dialect: 'postgresql'))->toBe([
        'version' => 1,
        'input' => ['sheet' => null, 'header_row' => null, 'delimiter' => null, 'encoding' => null],
        'output' => [
            'format' => 'sql',
            'dialect' => 'postgresql',
            'table' => null,
            'schema' => null,
            'create_table' => true,
            'column_names' => 'snake_case',
            'style' => 'inserts',
            'batch_rows' => 500,
        ],
        'inference' => ['sample_rows' => 10_000, 'date_order' => 'auto', 'keep_leading_zeros' => true, 'locale' => null],
        'columns' => [],
    ]);
});

it('takes the options it has', function (): void {
    $spec = Spec::make(dialect: 'mariadb', format: 'json', table: 'ventas', schema: 'sales', sheet: 'Q3', createTable: false, batchRows: 50);

    expect($spec['input']['sheet'])->toBe('Q3')
        ->and($spec['output'])->toMatchArray([
            'format' => 'json',
            'dialect' => 'mariadb',
            'table' => 'ventas',
            'schema' => 'sales',
            'create_table' => false,
            'batch_rows' => 50,
        ]);
});
