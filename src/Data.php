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

    /** @param array<mixed> $data */
    public static function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    /** @param array<mixed> $data */
    public static function bool(array $data, string $key): bool
    {
        return ($data[$key] ?? null) === true;
    }

    /**
     * The strings in a list field, skipping anything else.
     *
     * @param  array<mixed>  $data
     * @return list<string>
     */
    public static function strings(array $data, string $key): array
    {
        return array_values(array_filter(self::map($data, $key), is_string(...)));
    }

    /**
     * The integers in a list field, skipping anything else.
     *
     * @param  array<mixed>  $data
     * @return list<int>
     */
    public static function ints(array $data, string $key): array
    {
        return array_values(array_filter(self::map($data, $key), is_int(...)));
    }

    /**
     * The string values of an object field, keyed by name, skipping anything else.
     *
     * @param  array<mixed>  $data
     * @return array<string, string>
     */
    public static function stringMap(array $data, string $key): array
    {
        $strings = [];

        foreach (self::map($data, $key) as $name => $value) {
            if (is_string($value)) {
                $strings[(string) $name] = $value;
            }
        }

        return $strings;
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
