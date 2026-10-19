# IntoSQL for PHP

Convert spreadsheets (XLSX, XLS, ODS), CSV and JSON into SQL for PostgreSQL, MySQL, MariaDB, SQLite, SQL Server or Oracle with the [IntoSQL API](https://intosql.com/developers). Column types are inferred and dirty values fixed, so the script loads on the first try.

```sh
composer require intosql/intosql
```

PHP 8.2 or newer, with Guzzle 7. The API is part of IntoSQL Pro; make a key in Settings, API keys.

## Convert a file

```php
use IntoSQL\IntoSQL;
use IntoSQL\Spec;

$intosql = new IntoSQL(apiKey: getenv('INTOSQL_API_KEY'));

$conversion = $intosql->convert('ventas.xlsx', Spec::make(dialect: 'postgresql', table: 'ventas'));

echo $conversion->report['rows']['converted'] ?? 0, ' ', $conversion->output?->url;
```

The file streams to storage as it is; the output URL downloads the script for ten minutes. Read the conversion again (`$intosql->conversions->retrieve($id)`) for a fresh one.

A MySQL or MariaDB dump (`.sql`, `.sql.gz`) converts to PostgreSQL like any file. `$conversion->jobReport()` reads the report into classes; for a dump, `tables` lists each table with its rows, columns, fixes and problems:

```php
foreach ($conversion->jobReport()?->tables ?? [] as $table) {
    echo $table->name, ': ', $table->rows, " rows\n";
}
```

## Convert a file by URL

```php
$conversion = $intosql->convertUrl('https://example.com/exports/ventas.csv', Spec::make(dialect: 'mysql'));
```

## Check a webhook

Save a webhook address in Settings, API keys, and IntoSQL calls it when a conversion is done, failed or canceled.

```php
use IntoSQL\Webhook;

// Throws UnexpectedValueException when the signature doesn't match.
$event = Webhook::parse(
    file_get_contents('php://input'),
    $_SERVER['HTTP_INTOSQL_SIGNATURE'] ?? '',
    getenv('INTOSQL_WEBHOOK_SECRET'),
);
```

## Errors and retries

Failed requests throw `IntoSQLException` with the HTTP `status`, an `errorCode` that doesn't change (`needs_pro`, `over_quota`, `too_big`, `invalid_spec`, `invalid_url` and more) and the `requestId`. A conversion that ends as failed throws `ConversionFailedException` with its `errorCode` and the engine's `problems`. Rate limits, a full account, server errors and lost connections are retried twice with the same idempotency key (`maxRetries` changes it); `already_started` never is.

Every endpoint: [intosql.com/docs/sdks/php](https://intosql.com/docs/sdks/php).
