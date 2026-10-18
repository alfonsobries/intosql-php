<?php

declare(strict_types=1);

namespace IntoSQL;

use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

/**
 * Turns what `convert` and `upload` accept into a stream: a path, a stream resource or a PSR-7
 * stream.
 *
 * @internal
 */
final class Upload
{
    public static function stream(mixed $file): StreamInterface
    {
        if ($file instanceof StreamInterface) {
            return $file;
        }

        if (is_string($file)) {
            if (! is_file($file) || ! is_readable($file)) {
                throw new InvalidArgumentException("No readable file at {$file}.");
            }

            return Utils::streamFor(Utils::tryFopen($file, 'rb'));
        }

        if (is_resource($file)) {
            return Utils::streamFor($file);
        }

        throw new InvalidArgumentException(
            'A file is a path, a stream resource or a Psr\Http\Message\StreamInterface; got '.get_debug_type($file).'.',
        );
    }
}
