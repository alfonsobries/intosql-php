<?php

declare(strict_types=1);

namespace IntoSQL;

/** Where a conversion is. A status this version doesn't know reads as `Unknown`. */
enum ConversionStatus: string
{
    case AwaitingUpload = 'awaiting_upload';
    case Queued = 'queued';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';
    case Canceled = 'canceled';
    case Unknown = 'unknown';

    /** The status for a value the API answered, `Unknown` for one added after this version. */
    public static function fromApi(string $value): self
    {
        return self::tryFrom($value) ?? self::Unknown;
    }
}
