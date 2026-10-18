<?php

declare(strict_types=1);

namespace IntoSQL;

/**
 * Reads fields out of decoded JSON without trusting their types, so an answer with a field
 * missing or of another kind never crashes the client.
 *
 * @internal
 */
final class Data
{
    /** @param array<mixed> $data */
    public static function string(array $data, string $key): string
    {
        return self::nullableString($data, $key) ?? '';
    }

    /** @param array<mixed> $data */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @param array<mixed> $data */
    public static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : 0;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public static function map(array $data, string $key): array
    {
        return self::nullableMap($data, $key) ?? [];
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>|null
     */
    public static function nullableMap(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : null;
    }

    /**
     * The arrays in a list field, skipping anything else.
     *
     * @param  array<mixed>  $data
     * @return list<array<mixed>>
     */
    public static function maps(array $data, string $key): array
    {
        return array_values(array_filter(self::map($data, $key), is_array(...)));
    }
}
