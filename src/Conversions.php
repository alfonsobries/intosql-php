<?php

declare(strict_types=1);

namespace IntoSQL;

use Generator;
use LogicException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/** Each step of a conversion on its own, for when you want to upload from somewhere else, start later, or not wait. */
final class Conversions
{
    public function __construct(private readonly IntoSQL $client) {}

    /**
     * Opens a conversion; the answer has the address to upload the file to.
     *
     * @param  array<string, mixed>  $spec
     */
    public function create(string $name, int $bytes, array $spec): Conversion
    {
        return Conversion::fromArray($this->client->data('POST', '/conversions', [
            'name' => $name,
            'bytes' => $bytes,
            'spec' => $spec,
        ]));
    }

    /**
     * Queues a conversion of the file at a public HTTPS address.
     *
     * @param  array<string, mixed>  $spec
     */
    public function createFromUrl(string $url, array $spec, ?string $name = null): Conversion
    {
        return Conversion::fromArray($this->client->data('POST', '/conversions', array_filter(
            ['url' => $url, 'spec' => $spec, 'name' => $name],
            static fn (mixed $value): bool => $value !== null,
        )));
    }

    /**
     * Sends the file to the address `create` answered with, streamed as it is. Never retried: a
     * storage answer other than 2xx throws `upload_failed`.
     *
     * @param  string|resource|StreamInterface  $file  A path, a stream resource or a PSR-7 stream.
     */
    public function upload(Conversion $conversion, mixed $file): void
    {
        if ($conversion->upload === null) {
            throw new LogicException("Conversion {$conversion->id} has no upload address: open it with create().");
        }

        $headers = $conversion->upload->headers;

        if (array_filter(array_keys($headers), static fn (string $name): bool => strcasecmp($name, 'Content-Length') === 0) === []) {
            $headers['Content-Length'] = (string) $conversion->bytes;
        }

        $stream = Upload::stream($file);

        try {
            $response = $this->client->http()->request('PUT', $conversion->upload->url, [
                'http_errors' => false,
                'headers' => $headers,
                'body' => $stream,
            ]);
        } finally {
            if (is_string($file)) {
                $stream->close();
            }
        }

        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            throw new IntoSQLException($status, 'upload_failed', "The upload answered {$status}.");
        }
    }

    /** Starts a conversion once its file is uploaded whole. */
    public function start(string $id): Conversion
    {
        return Conversion::fromArray($this->client->data('POST', '/conversions/'.rawurlencode($id).'/start'));
    }

    public function retrieve(string $id): Conversion
    {
        return Conversion::fromArray($this->client->data('GET', '/conversions/'.rawurlencode($id)));
    }

    /**
     * Every conversion, newest first, fetched a page at a time as you iterate; `status` keeps only
     * those in it.
     *
     * @param  int|null  $limit  Conversions per page, 1 to 100 (50 when null).
     * @return Generator<int, Conversion>
     */
    public function list(?int $limit = null, ConversionStatus|string|null $status = null): Generator
    {
        $query = array_filter(
            ['limit' => $limit, 'status' => $status instanceof ConversionStatus ? $status->value : $status],
            static fn (mixed $value): bool => $value !== null,
        );
        $path = $query === [] ? '/conversions' : '/conversions?'.http_build_query($query);

        while ($path !== null) {
            $page = Page::fromArray($this->client->request('GET', $path) ?? []);

            foreach ($page->data as $conversion) {
                yield $conversion;
            }

            $path = $page->next;
        }
    }

    /** Stops a conversion that hasn't ended; canceling it again answers the same. */
    public function cancel(string $id): Conversion
    {
        return Conversion::fromArray($this->client->data('POST', '/conversions/'.rawurlencode($id).'/cancel'));
    }

    /**
     * Sends a conversion that didn't come out right to IntoSQL, so support for it gets added.
     * `consent` must be true: the person agrees to send it. `share: 'file'` sends the file too.
     *
     * @param  string|null  $share  `file` or `report`.
     */
    public function createCase(
        string $id,
        bool $consent,
        ?string $share = null,
        ?string $email = null,
        ?string $note = null,
    ): ConversionCase {
        return ConversionCase::fromArray($this->client->data(
            'POST',
            '/conversions/'.rawurlencode($id).'/cases',
            array_filter(
                ['consent' => $consent, 'share' => $share, 'email' => $email, 'note' => $note],
                static fn (mixed $value): bool => $value !== null,
            ),
        ));
    }

    /** Deletes its files now instead of after 24 hours. */
    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/conversions/'.rawurlencode($id));
    }

    /**
     * Reads the conversion until it is done; throws `ConversionFailedException` if it fails and
     * `ConversionCanceledException` if it is canceled.
     *
     * @param  float  $timeout  Give up after this many seconds. The conversion keeps running.
     * @param  float|null  $interval  Seconds between reads; when null, half a second doubling up to 5.
     *
     * @throws ConversionFailedException|ConversionCanceledException|RuntimeException
     */
    public function wait(string $id, float $timeout = 3600.0, ?float $interval = null): Conversion
    {
        $deadline = microtime(true) + $timeout;

        for ($attempt = 0; ; $attempt++) {
            $conversion = $this->retrieve($id);

            if ($conversion->status === ConversionStatus::Done) {
                return $conversion;
            }

            if ($conversion->status === ConversionStatus::Failed) {
                throw new ConversionFailedException($id, $conversion->error);
            }

            if ($conversion->status === ConversionStatus::Canceled) {
                throw new ConversionCanceledException($id);
            }

            if (microtime(true) >= $deadline) {
                throw new RuntimeException("Conversion {$id} is still {$conversion->status->value}.");
            }

            $this->client->sleep($interval ?? min(0.5 * 2 ** $attempt, 5.0));
        }
    }
}
