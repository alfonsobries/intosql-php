<?php

declare(strict_types=1);

namespace IntoSQL;

use UnexpectedValueException;

/** Checks and reads the calls IntoSQL makes to your webhook address. */
final class Webhook
{
    /**
     * Whether a webhook call came from IntoSQL: `$header` is the `IntoSQL-Signature` header,
     * `$body` the raw request body, `$secret` the signing secret from Settings, API keys.
     *
     * @param  int  $toleranceSeconds  How far the call's time may be from now.
     * @param  int|null  $now  The current Unix time, to fix it in tests.
     */
    public static function verify(
        string $body,
        string $header,
        string $secret,
        int $toleranceSeconds = 300,
        ?int $now = null,
    ): bool {
        $parts = [];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            $parts[$pair[0]] = $pair[1] ?? '';
        }

        $time = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? null;

        if (
            preg_match('/^-?\d+$/', $time) !== 1
            || $signature === null
            || abs(($now ?? time()) - (int) $time) > $toleranceSeconds
        ) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', "{$time}.{$body}", $secret), $signature);
    }

    /**
     * The event of a webhook call, once its signature checks out. Throws when it doesn't, so a
     * forged or replayed call never reaches your code.
     *
     * @throws UnexpectedValueException
     */
    public static function parse(
        string $body,
        string $header,
        string $secret,
        int $toleranceSeconds = 300,
        ?int $now = null,
    ): WebhookEvent {
        if (! self::verify($body, $header, $secret, $toleranceSeconds, $now)) {
            throw new UnexpectedValueException('The IntoSQL-Signature does not match this body.');
        }

        $event = json_decode($body, true);

        if (! is_array($event)) {
            throw new UnexpectedValueException('The webhook body is not a JSON object.');
        }

        return WebhookEvent::fromArray($event);
    }
}
