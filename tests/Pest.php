<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use IntoSQL\Spec;

/**
 * A conversion as the API answers it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function conversion(array $overrides = []): array
{
    return [
        'id' => '01abc',
        'status' => 'awaiting_upload',
        'name' => 'ventas.csv',
        'bytes' => 3,
        'spec' => Spec::make(dialect: 'postgresql'),
        'report' => null,
        'error' => null,
        'output' => null,
        'upload' => null,
        'created_at' => null,
        'started_at' => null,
        'finished_at' => null,
        'expires_at' => '2026-10-16T00:00:00+00:00',
        ...$overrides,
    ];
}

/** @param array<string, string> $headers */
function json(mixed $body, int $status = 200, array $headers = []): Response
{
    return new Response(
        $status,
        ['Content-Type' => 'application/json', ...$headers],
        $status === 204 ? null : json_encode($body, JSON_THROW_ON_ERROR),
    );
}

/**
 * A conversion just opened, with its upload address.
 *
 * @param  array<string, string|list<string>>  $headers
 * @return array<string, mixed>
 */
function withUpload(array $headers = [], int $bytes = 3): array
{
    return conversion([
        'bytes' => $bytes,
        'upload' => ['method' => 'PUT', 'url' => 'https://storage.test/put', 'headers' => $headers],
    ]);
}
