<?php

declare(strict_types=1);

use IntoSQL\Conversion;
use IntoSQL\ConversionStatus;
use IntoSQL\Webhook;

function sign(string $body, int $time, string $secret): string
{
    return "t={$time},v1=".hash_hmac('sha256', "{$time}.{$body}", $secret);
}

it('accepts a fresh call signed with the secret, and nothing else', function (): void {
    $body = '{"type":"ping"}';
    $header = sign($body, 1_700_000_000, 'whsec_1');

    expect(Webhook::verify($body, $header, 'whsec_1', now: 1_700_000_100))->toBeTrue()
        ->and(Webhook::verify($body, ' t=1700000000 , v1='.substr($header, 16), 'whsec_1', now: 1_700_000_000))->toBeTrue()
        ->and(Webhook::verify("{$body} ", $header, 'whsec_1', now: 1_700_000_100))->toBeFalse()
        ->and(Webhook::verify($body, $header, 'whsec_2', now: 1_700_000_100))->toBeFalse()
        ->and(Webhook::verify($body, $header, 'whsec_1', now: 1_700_001_000))->toBeFalse()
        ->and(Webhook::verify($body, $header, 'whsec_1', now: 1_699_999_000))->toBeFalse()
        ->and(Webhook::verify($body, $header, 'whsec_1', toleranceSeconds: 1_000, now: 1_700_001_000))->toBeTrue()
        ->and(Webhook::verify($body, 't=abc', 'whsec_1'))->toBeFalse()
        ->and(Webhook::verify($body, 't=1700000000', 'whsec_1', now: 1_700_000_000))->toBeFalse()
        ->and(Webhook::verify($body, 'v1=abc', 'whsec_1'))->toBeFalse()
        ->and(Webhook::verify($body, '', 'whsec_1'))->toBeFalse()
        ->and(Webhook::verify($body, substr($header, 0, -4), 'whsec_1', now: 1_700_000_000))->toBeFalse()
        ->and(Webhook::verify($body, sign($body, time(), 'whsec_1'), 'whsec_1'))->toBeTrue();
});

it('parses the event of a call that checks out, and refuses one that does not', function (): void {
    $body = json_encode([
        'id' => 'evt_1',
        'type' => 'ping',
        'created_at' => '2026-10-16T14:03:27+00:00',
        'data' => ['sent_at' => '2026-10-16T14:03:27+00:00'],
    ], JSON_THROW_ON_ERROR);
    $header = sign($body, 1_700_000_000, 'whsec_1');
    $event = Webhook::parse($body, $header, 'whsec_1', now: 1_700_000_000);

    expect($event->id)->toBe('evt_1')
        ->and($event->type)->toBe('ping')
        ->and($event->createdAt)->toBe('2026-10-16T14:03:27+00:00')
        ->and($event->data)->toBe(['sent_at' => '2026-10-16T14:03:27+00:00'])
        ->and(fn () => Webhook::parse($body, $header, 'whsec_2', now: 1_700_000_000))
        ->toThrow(UnexpectedValueException::class, 'The IntoSQL-Signature does not match this body.')
        ->and(fn () => Webhook::parse('"ping"', sign('"ping"', 1_700_000_000, 'whsec_1'), 'whsec_1', now: 1_700_000_000))
        ->toThrow(UnexpectedValueException::class, 'The webhook body is not a JSON object.');
});

it('reads the conversion of a conversion event', function (): void {
    $body = json_encode([
        'id' => 'evt_2',
        'type' => 'conversion.failed',
        'created_at' => '2026-10-16T17:03:27+00:00',
        'data' => ['id' => '01abc', 'status' => 'failed', 'error' => ['code' => 'timed_out', 'message' => 'Too long.', 'problems' => []]],
    ], JSON_THROW_ON_ERROR);
    $event = Webhook::parse($body, sign($body, 1_700_000_000, 'whsec_1'), 'whsec_1', now: 1_700_000_000);

    expect($event->data)->toBeInstanceOf(Conversion::class);

    assert($event->data instanceof Conversion);
    expect($event->data->status)->toBe(ConversionStatus::Failed)
        ->and($event->data->error?->code)->toBe('timed_out');
});
