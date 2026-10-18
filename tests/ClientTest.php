<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\PumpStream;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use IntoSQL\Conversion;
use IntoSQL\ConversionCanceledException;
use IntoSQL\ConversionFailedException;
use IntoSQL\ConversionStatus;
use IntoSQL\IntoSQL;
use IntoSQL\IntoSQLException;
use IntoSQL\Spec;
use IntoSQL\Tests\Fake;
use IntoSQL\Upload;

const BASE = Fake::BASE;

function offline(): ConnectException
{
    return new ConnectException('Could not resolve host', new Request('GET', BASE));
}

/** @return string A temporary file holding `$contents`, deleted when the test ends. */
function temporaryFile(string $contents, string $name = 'ventas.csv'): string
{
    $directory = sys_get_temp_dir().'/intosql-'.bin2hex(random_bytes(4));
    mkdir($directory);
    $path = "{$directory}/{$name}";
    file_put_contents($path, $contents);

    return $path;
}

it('needs an API key', function (): void {
    expect(fn () => new IntoSQL(apiKey: ''))->toThrow(InvalidArgumentException::class, 'An API key is required');
    expect((new IntoSQL(apiKey: 'k'))->conversions)->toBeObject();
});

it('uploads a file from a path, starts it and waits for the result', function (): void {
    $fake = new Fake(
        json(['data' => withUpload(['Content-Type' => 'text/csv', 'X-Multi' => ['a', 'b']])], 201),
        new Response(200),
        json(['data' => conversion(['status' => 'queued'])], 202),
        json(['data' => conversion(['status' => 'running'])]),
        json(['data' => conversion(['status' => 'done', 'output' => ['bytes' => 30, 'url' => 'https://storage.test/out']])]),
    );

    $done = $fake->client->convert(
        temporaryFile('a,b'),
        Spec::make(dialect: 'mysql', table: 'ventas'),
    );

    expect($done->status)->toBe(ConversionStatus::Done)
        ->and($done->output?->url)->toBe('https://storage.test/out')
        ->and($done->output?->bytes)->toBe(30)
        ->and($fake->calls())->toBe([
            'POST '.BASE.'/conversions',
            'PUT https://storage.test/put',
            'POST '.BASE.'/conversions/01abc/start',
            'GET '.BASE.'/conversions/01abc',
            'GET '.BASE.'/conversions/01abc',
        ])
        ->and($fake->sleeps)->toBe([0.5]);

    $opened = $fake->request(0);
    expect($opened->getHeaderLine('Authorization'))->toBe('Bearer key_1')
        ->and($opened->getHeaderLine('Accept'))->toBe('application/json')
        ->and($opened->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and($opened->getHeaderLine('User-Agent'))->toBe('intosql-php/'.IntoSQL::VERSION)
        ->and($opened->getHeaderLine('Idempotency-Key'))->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
        ->and($fake->history[0]['options']['timeout'])->toBe(60.0)
        ->and($fake->sent(0))->toMatchArray(['name' => 'ventas.csv', 'bytes' => 3])
        ->and($fake->sentOutput(0))->toMatchArray(['dialect' => 'mysql', 'table' => 'ventas']);

    $upload = $fake->request(1);
    expect($upload->getHeaderLine('X-Multi'))->toBe('a, b')
        ->and($upload->getHeaderLine('Content-Type'))->toBe('text/csv')
        ->and($upload->getHeaderLine('Content-Length'))->toBe('3')
        ->and($upload->hasHeader('Authorization'))->toBeFalse()
        ->and($upload->getBody()->getSize())->toBeNull('The file opened from the path is closed.')
        ->and($fake->request(2)->hasHeader('Content-Type'))->toBeFalse()
        ->and($fake->request(3)->hasHeader('Idempotency-Key'))->toBeFalse();
});

it('declares the size of PSR-7 streams, stream resources and streams that need it given', function (): void {
    $resource = Utils::tryFopen('php://memory', 'r+b');
    fwrite($resource, 'abcde');
    rewind($resource);
    $unsized = new PumpStream(fn (): bool => false);

    foreach ([
        [Utils::streamFor('abcd'), null, 4],
        [$resource, null, 5],
        [$unsized, 7, 7],
    ] as [$file, $bytes, $declared]) {
        $fake = new Fake(
            json(['data' => withUpload()], 201),
            new Response(200),
            json(['data' => conversion(['status' => 'queued'])], 202),
            json(['data' => conversion(['status' => 'done'])]),
        );

        $fake->client->convert($file, Spec::make(dialect: 'sqlite'), name: 'a.csv', bytes: $bytes);

        expect($fake->sent(0)['bytes'])->toBe($declared)
            ->and($fake->sent(0)['name'])->toBe('a.csv');
    }
});

it('refuses files it cannot read or size before calling the API', function (): void {
    $fake = new Fake;
    $spec = Spec::make(dialect: 'sqlite');

    expect(fn () => $fake->client->convert(Utils::streamFor('a'), $spec))
        ->toThrow(InvalidArgumentException::class, 'A name is required when the file is a stream.')
        ->and(fn () => $fake->client->convert(new PumpStream(fn (): bool => false), $spec, name: 'a.csv'))
        ->toThrow(InvalidArgumentException::class, 'The stream has no known size: pass bytes.')
        ->and(fn () => $fake->client->convert('/no/such/ventas.csv', $spec))
        ->toThrow(InvalidArgumentException::class, 'No readable file at /no/such/ventas.csv.')
        ->and(fn () => Upload::stream(42))
        ->toThrow(InvalidArgumentException::class, 'A file is a path, a stream resource or a Psr\Http\Message\StreamInterface; got int.')
        ->and($fake->history)->toBe([]);
});

it('uploads on its own, keeps the storage headers and never retries a failed upload', function (): void {
    $fake = new Fake(
        new Response(200),
        new Response(403),
    );

    expect(fn () => $fake->client->conversions->upload(Conversion::fromArray(conversion()), Utils::streamFor('abc')))
        ->toThrow(LogicException::class, 'Conversion 01abc has no upload address: open it with create().');

    $opened = Conversion::fromArray(withUpload(['content-length' => '3']));
    $fake->client->conversions->upload($opened, temporaryFile('abc'));

    expect($fake->request(0)->getHeader('Content-Length'))->toBe(['3']);

    try {
        $fake->client->conversions->upload($opened, Utils::streamFor('ab'));
        $this->fail('The upload should have failed.');
    } catch (IntoSQLException $error) {
        expect($error->status)->toBe(403)
            ->and($error->errorCode)->toBe('upload_failed')
            ->and($error->getMessage())->toBe('The upload answered 403.')
            ->and($error->getCode())->toBe(403);
    }

    expect($fake->history)->toHaveCount(2)
        ->and((string) $fake->request(1)->getBody())->toBe('ab')
        ->and($fake->request(1)->getHeaderLine('Content-Length'))->toBe('3')
        ->and($fake->sleeps)->toBe([]);
});

it('converts a file by URL', function (): void {
    $fake = new Fake(
        json(['data' => conversion(['status' => 'queued', 'bytes' => 0])], 202),
        json(['data' => conversion([
            'status' => 'failed',
            'error' => [
                'code' => 'unsupported_format',
                'message' => "ventas.csv isn't a spreadsheet",
                'problems' => [['code' => 'unsupported_format', 'severity' => 'error', 'args' => []], 'not a problem'],
            ],
        ])]),
        json(['data' => conversion(['status' => 'queued'])], 202),
        json(['data' => conversion(['status' => 'done'])]),
    );

    try {
        $fake->client->convertUrl('https://files.test/ventas.csv', Spec::make(dialect: 'sql_server', format: 'csv'), name: 'ventas.csv');
        $this->fail('The conversion should have failed.');
    } catch (ConversionFailedException $error) {
        expect($error->id)->toBe('01abc')
            ->and($error->errorCode)->toBe('unsupported_format')
            ->and($error->problems)->toBe([['code' => 'unsupported_format', 'severity' => 'error', 'args' => []]])
            ->and($error->getMessage())->toBe("ventas.csv isn't a spreadsheet");
    }

    $done = $fake->client->convertUrl('https://files.test/b.csv', Spec::make(dialect: 'oracle'), interval: 0.1);

    expect($done->status)->toBe(ConversionStatus::Done)
        ->and($fake->sent(0))->toMatchArray(['url' => 'https://files.test/ventas.csv', 'name' => 'ventas.csv'])
        ->and($fake->sentOutput(0))->toMatchArray(['format' => 'csv', 'dialect' => 'sql_server'])
        ->and($fake->sent(2))->not->toHaveKey('name');

    $unexplained = new ConversionFailedException('x', null);
    expect($unexplained->getMessage())->toBe('Conversion x failed.')
        ->and($unexplained->errorCode)->toBe('internal_error')
        ->and($unexplained->problems)->toBe([]);
});

it('waits with growing pauses or a fixed interval, and gives up after the timeout', function (): void {
    $running = json(['data' => conversion(['status' => 'running'])]);
    $fake = new Fake(
        $running,
        json(['data' => conversion(['status' => 'some_new_status'])]),
        json(['data' => conversion(['status' => 'queued'])]),
        json(['data' => conversion(['status' => 'running'])]),
        json(['data' => conversion(['status' => 'running'])]),
        json(['data' => conversion(['status' => 'running'])]),
        json(['data' => conversion(['status' => 'done'])]),
        json(['data' => conversion(['status' => 'running'])]),
        json(['data' => conversion(['status' => 'running'])]),
        json(['data' => conversion(['status' => 'done'])]),
        json(['data' => conversion(['status' => 'running'])]),
    );

    expect($fake->client->conversions->wait('01abc')->status)->toBe(ConversionStatus::Done)
        ->and($fake->sleeps)->toBe([0.5, 1.0, 2.0, 4.0, 5.0, 5.0]);

    $fake->sleeps = [];
    $fake->client->conversions->wait('01abc', interval: 2.5);
    expect($fake->sleeps)->toBe([2.5, 2.5]);

    expect(fn () => $fake->client->conversions->wait('01abc', timeout: 0))
        ->toThrow(RuntimeException::class, 'Conversion 01abc is still running.');
});

it('pauses for real when no sleep is given', function (): void {
    $history = [];
    $client = new IntoSQL(apiKey: 'key_1', baseUrl: BASE, httpClient: Fake::http([
        json(['data' => conversion(['status' => 'running'])]),
        json(['data' => conversion(['status' => 'done'])]),
    ], $history));

    $started = microtime(true);
    $client->conversions->wait('01abc', interval: 0.01);

    expect(microtime(true) - $started)->toBeGreaterThanOrEqual(0.01);
});

it('retries rate limits, server errors and lost connections, with the same idempotency key', function (): void {
    $fake = new Fake(
        json(['error' => ['code' => 'rate_limited', 'message' => 'Slow down.']], 429, ['Retry-After' => '2']),
        offline(),
        json(['data' => ['limit' => 10, 'used' => 1, 'left' => 9, 'period_start' => '2026-10-01T00:00:00+00:00']]),
    );

    $usage = $fake->client->usage();

    expect($usage->left)->toBe(9)
        ->and($usage->limit)->toBe(10)
        ->and($usage->used)->toBe(1)
        ->and($usage->periodStart)->toBe('2026-10-01T00:00:00+00:00')
        ->and($fake->history)->toHaveCount(3)
        ->and($fake->sleeps)->toBe([2.0, 1.0]);

    $full = new Fake(
        json(['error' => ['code' => 'too_many_active', 'message' => 'Full.']], 429, ['Retry-After' => '30']),
        json(['error' => ['code' => 'request_in_progress', 'message' => 'Wait.']], 409),
        json(['data' => conversion(['status' => 'queued'])], 202),
    );

    expect($full->client->conversions->start('01abc')->status)->toBe(ConversionStatus::Queued)
        ->and($full->history)->toHaveCount(3)
        ->and($full->sleeps)->toBe([30.0, 1.0]);

    $posting = new Fake(
        json([], 503),
        new Response(408),
        json(['data' => conversion()], 201),
    );
    $posting->client->conversions->create('a.csv', 1, Spec::make(dialect: 'oracle'));

    $keys = array_map(fn (int $index): string => $posting->request($index)->getHeaderLine('Idempotency-Key'), [0, 1, 2]);
    expect(array_unique($keys))->toHaveCount(1)
        ->and($posting->sleeps)->toBe([0.5, 1.0]);

    $backingOff = new Fake(
        json([], 500, ['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT']),
        json([], 500),
        json(['data' => conversion()]),
    );
    $backingOff->client->conversions->retrieve('01abc');

    expect($backingOff->sleeps)->toBe([0.5, 1.0]);
});

it('waits at most 8 seconds between retries', function (): void {
    $history = [];
    $sleeps = [];
    $client = new IntoSQL(
        apiKey: 'key_1',
        baseUrl: BASE,
        maxRetries: 5,
        httpClient: Fake::http([offline(), offline(), offline(), offline(), offline(), json(['data' => conversion()])], $history),
        sleep: function (float $seconds) use (&$sleeps): void {
            $sleeps[] = $seconds;
        },
    );

    $client->conversions->retrieve('01abc');

    expect($sleeps)->toBe([0.5, 1.0, 2.0, 4.0, 8.0]);
});

it('throws typed errors with the stable code', function (): void {
    $fake = new Fake(
        json(['error' => ['code' => 'invalid_spec', 'message' => 'Bad spec.', 'fields' => ['spec' => ['wrong', 3], 'name' => 'not a list']]], 422),
        new Response(500, [], 'not json'),
        new Response(500, [], 'not json'),
        new Response(500, [], 'not json'),
        offline(),
        offline(),
        offline(),
    );

    try {
        $fake->client->conversions->retrieve('x');
        $this->fail('Expected an IntoSQLException.');
    } catch (IntoSQLException $error) {
        expect($error->status)->toBe(422)
            ->and($error->errorCode)->toBe('invalid_spec')
            ->and($error->getMessage())->toBe('Bad spec.')
            ->and($error->fields)->toBe(['spec' => ['wrong'], 'name' => []])
            ->and($error->requestId)->toBeNull();
    }

    $started = new Fake(json(['error' => ['code' => 'already_started', 'message' => 'Started.', 'request_id' => 'req_1']], 409));

    try {
        $started->client->conversions->start('x');
        $this->fail('Expected an IntoSQLException.');
    } catch (IntoSQLException $error) {
        expect($error->errorCode)->toBe('already_started')
            ->and($error->requestId)->toBe('req_1')
            ->and($started->history)->toHaveCount(1);
    }

    try {
        $fake->client->conversions->retrieve('x');
        $this->fail('Expected an IntoSQLException.');
    } catch (IntoSQLException $error) {
        expect($error->status)->toBe(500)
            ->and($error->errorCode)->toBe('http_error')
            ->and($error->getMessage())->toBe('The API answered 500.')
            ->and($error->fields)->toBe([]);
    }

    $history = [];
    $gateway = new IntoSQL(
        apiKey: 'key_1',
        baseUrl: BASE,
        maxRetries: 0,
        httpClient: Fake::http([new Response(502, ['X-Request-Id' => 'req_2'], 'bad gateway')], $history),
    );

    try {
        $gateway->usage();
        $this->fail('Expected an IntoSQLException.');
    } catch (IntoSQLException $error) {
        expect($error->status)->toBe(502)
            ->and($error->requestId)->toBe('req_2')
            ->and($history)->toHaveCount(1);
    }

    expect(fn () => $fake->client->usage())->toThrow(ConnectException::class, 'Could not resolve host');
});

it('lists every page, deletes, and keeps the key on its own origin', function (): void {
    $fake = new Fake(
        json(['data' => [conversion(['id' => 'a'])], 'links' => ['next' => BASE.'/conversions?page=2'], 'meta' => ['per_page' => 1, 'next_cursor' => 'c2', 'prev_cursor' => null]]),
        json(['data' => [conversion(['id' => 'b'])], 'links' => ['next' => 'https://evil.test/steal']]),
        json(null, 204),
    );
    $seen = [];

    expect(function () use ($fake, &$seen): void {
        foreach ($fake->client->conversions->list() as $conversion) {
            $seen[] = $conversion->id;
        }
    })->toThrow(RuntimeException::class, 'Refusing to send the API key to https://evil.test.');

    $fake->client->conversions->delete('a b');

    expect($seen)->toBe(['a', 'b'])
        ->and($fake->calls())->toBe([
            'GET '.BASE.'/conversions',
            'GET '.BASE.'/conversions?page=2',
            'DELETE '.BASE.'/conversions/a%20b',
        ]);

    $port = new Fake(json(['data' => [], 'links' => ['next' => 'https://intosql.test:8443/api/v1/conversions?page=2']]));

    expect(fn () => iterator_to_array($port->client->conversions->list()))
        ->toThrow(RuntimeException::class, 'Refusing to send the API key to https://intosql.test:8443.');

    $filtered = new Fake(
        json(['data' => [conversion(['status' => 'done'])], 'links' => ['next' => null]]),
        json(['data' => [], 'links' => ['next' => null]]),
        json(['data' => [], 'links' => ['next' => null]]),
    );
    $statuses = [];

    foreach ($filtered->client->conversions->list(limit: 10, status: ConversionStatus::Done) as $conversion) {
        $statuses[] = $conversion->status;
    }

    iterator_to_array($filtered->client->conversions->list(limit: 5));
    iterator_to_array($filtered->client->conversions->list(status: 'failed'));

    expect($statuses)->toBe([ConversionStatus::Done])
        ->and($filtered->calls())->toBe([
            'GET '.BASE.'/conversions?limit=10&status=done',
            'GET '.BASE.'/conversions?limit=5',
            'GET '.BASE.'/conversions?status=failed',
        ]);
});

it('fetches no page until the list is read, and none after it stops', function (): void {
    $fake = new Fake(
        json(['data' => [conversion(['id' => 'a']), conversion(['id' => 'b'])], 'links' => ['next' => BASE.'/conversions?page=2']]),
    );

    $list = $fake->client->conversions->list();
    expect($fake->history)->toBe([]);

    foreach ($list as $conversion) {
        break;
    }

    expect($fake->history)->toHaveCount(1);
});

it('cancels a conversion, and stops waiting for one that was canceled', function (): void {
    $fake = new Fake(
        json(['data' => conversion(['status' => 'canceled'])]),
        json(['data' => conversion(['status' => 'canceled'])]),
    );

    expect($fake->client->conversions->cancel('01abc')->status)->toBe(ConversionStatus::Canceled);

    try {
        $fake->client->conversions->wait('01abc');
        $this->fail('Expected a ConversionCanceledException.');
    } catch (ConversionCanceledException $error) {
        expect($error->id)->toBe('01abc')
            ->and($error->getMessage())->toBe('Conversion 01abc was canceled.');
    }

    expect($fake->calls()[0])->toBe('POST '.BASE.'/conversions/01abc/cancel')
        ->and($fake->request(0)->hasHeader('Idempotency-Key'))->toBeTrue();
});

it('sends a conversion as a case, with consent', function (): void {
    $fake = new Fake(json(['data' => [
        'id' => '01case',
        'conversion_id' => '01abc',
        'share' => 'file',
        'status' => 'open',
        'created_at' => null,
        'expires_at' => '2027-01-15T00:00:00+00:00',
    ]], 201));

    $case = $fake->client->conversions->createCase('01abc', consent: true, share: 'file', note: 'Dates come out wrong.');

    expect($case->id)->toBe('01case')
        ->and($case->conversionId)->toBe('01abc')
        ->and($case->share)->toBe('file')
        ->and($case->status)->toBe('open')
        ->and($case->createdAt)->toBeNull()
        ->and($case->expiresAt)->toBe('2027-01-15T00:00:00+00:00')
        ->and($fake->calls())->toBe(['POST '.BASE.'/conversions/01abc/cases'])
        ->and($fake->sent(0))->toBe(['consent' => true, 'share' => 'file', 'note' => 'Dates come out wrong.']);
});

it('reads answers with fields missing or of another type without crashing', function (): void {
    $fake = new Fake(
        json('not an object'),
        json(['data' => ['id' => 7, 'status' => 'archived', 'upload' => ['headers' => ['X-A' => ['a', 1], 'X-B' => 'b']], 'error' => ['code' => 'timed_out']]]),
    );

    $empty = $fake->client->conversions->retrieve('x');
    $odd = $fake->client->conversions->retrieve('x');

    expect($empty->id)->toBe('')
        ->and($empty->status)->toBe(ConversionStatus::Unknown)
        ->and($empty->spec)->toBe([])
        ->and($empty->expiresAt)->toBe('')
        ->and($odd->id)->toBe('')
        ->and($odd->status)->toBe(ConversionStatus::Unknown)
        ->and($odd->upload?->headers)->toBe(['X-A' => 'a', 'X-B' => 'b'])
        ->and($odd->error?->message)->toBe('')
        ->and($odd->error?->problems)->toBe([])
        ->and(ConversionStatus::fromApi('running'))->toBe(ConversionStatus::Running);
});

it('reads a full conversion', function (): void {
    $conversion = Conversion::fromArray(conversion([
        'status' => 'done',
        'report' => ['table' => 'ventas'],
        'created_at' => '2026-10-15T00:00:00+00:00',
        'started_at' => '2026-10-15T00:00:01+00:00',
        'finished_at' => '2026-10-15T00:00:02+00:00',
    ]));

    expect($conversion->name)->toBe('ventas.csv')
        ->and($conversion->bytes)->toBe(3)
        ->and($conversion->spec['output'])->toMatchArray(['dialect' => 'postgresql'])
        ->and($conversion->report)->toBe(['table' => 'ventas'])
        ->and($conversion->error)->toBeNull()
        ->and($conversion->output)->toBeNull()
        ->and($conversion->upload)->toBeNull()
        ->and($conversion->createdAt)->toBe('2026-10-15T00:00:00+00:00')
        ->and($conversion->startedAt)->toBe('2026-10-15T00:00:01+00:00')
        ->and($conversion->finishedAt)->toBe('2026-10-15T00:00:02+00:00')
        ->and($conversion->expiresAt)->toBe('2026-10-16T00:00:00+00:00');
});
