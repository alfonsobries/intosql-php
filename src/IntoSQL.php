<?php

declare(strict_types=1);

namespace IntoSQL;

use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/** The IntoSQL API: convert spreadsheets, CSV and JSON into SQL on IntoSQL's machines. */
final class IntoSQL
{
    public const VERSION = '0.2.0';

    public const DEFAULT_BASE_URL = 'https://intosql.com/api/v1';

    /** Codes worth sending again: waiting fixes them. Never `already_started`, which means it is on its way. */
    private const RETRYABLE_CODES = ['rate_limited', 'too_many_active', 'request_in_progress'];

    /** Statuses from the network in between (gateways, timeouts), which carry no code of ours. */
    private const RETRYABLE_STATUSES = [408, 425, 502, 503, 504];

    public readonly Conversions $conversions;

    private readonly string $baseUrl;

    private readonly ClientInterface $http;

    /** @var Closure(float): void */
    private readonly Closure $sleep;

    /**
     * @param  string  $apiKey  An API key from Settings, API keys.
     * @param  string  $baseUrl  The API's base address. A trailing slash is removed.
     * @param  int  $maxRetries  Attempts after the first one for rate limits, a full account, server errors and lost connections.
     * @param  float  $timeout  Seconds each request to the API may take; 0 waits forever. The file upload has no limit.
     * @param  ClientInterface|null  $httpClient  The Guzzle client every call goes through, the upload included.
     * @param  (callable(float): void)|null  $sleep  Waits the given seconds between retries and reads; replace it in tests.
     */
    public function __construct(
        private readonly string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly int $maxRetries = 2,
        private readonly float $timeout = 60.0,
        ?ClientInterface $httpClient = null,
        ?callable $sleep = null,
    ) {
        if ($apiKey === '') {
            throw new InvalidArgumentException('An API key is required: make one in Settings, API keys.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->http = $httpClient ?? new Client;
        $this->sleep = $sleep === null
            ? static function (float $seconds): void {
                usleep((int) round($seconds * 1_000_000));
            }
        : $sleep(...);
        $this->conversions = new Conversions($this);
    }

    /**
     * Uploads `$file`, converts it and waits for the result.
     *
     * @param  string|resource|StreamInterface  $file  A path, a stream resource or a PSR-7 stream.
     * @param  array<string, mixed>  $spec  The conversion request; build it with `Spec::make()`.
     * @param  string|null  $name  Helps tell the format and names the result. Defaults to the file name of a path.
     * @param  int|null  $bytes  The file's exact size, for a stream that doesn't know it.
     * @param  float  $timeout  Seconds to wait for the result.
     * @param  float|null  $interval  Seconds between reads; growing pauses when null.
     *
     * @throws ConversionFailedException|ConversionCanceledException|IntoSQLException
     */
    public function convert(
        mixed $file,
        array $spec,
        ?string $name = null,
        ?int $bytes = null,
        float $timeout = 3600.0,
        ?float $interval = null,
    ): Conversion {
        $name ??= is_string($file)
            ? basename($file)
            : throw new InvalidArgumentException('A name is required when the file is a stream.');
        $stream = Upload::stream($file);

        try {
            $opened = $this->conversions->create(
                $name,
                $bytes ?? $stream->getSize() ?? throw new InvalidArgumentException('The stream has no known size: pass bytes.'),
                $spec,
            );
            $this->conversions->upload($opened, $stream);
        } finally {
            if (is_string($file)) {
                $stream->close();
            }
        }

        $this->conversions->start($opened->id);

        return $this->conversions->wait($opened->id, $timeout, $interval);
    }

    /**
     * Converts the file at a public HTTPS address and waits for the result.
     *
     * @param  array<string, mixed>  $spec
     *
     * @throws ConversionFailedException|ConversionCanceledException|IntoSQLException
     */
    public function convertUrl(
        string $url,
        array $spec,
        ?string $name = null,
        float $timeout = 3600.0,
        ?float $interval = null,
    ): Conversion {
        $queued = $this->conversions->createFromUrl($url, $spec, $name);

        return $this->conversions->wait($queued->id, $timeout, $interval);
    }

    /** Bytes of conversions the plan includes this month, used and left. */
    public function usage(): Usage
    {
        return Usage::fromArray($this->data('GET', '/usage'));
    }

    /**
     * The `data` of an answer.
     *
     * @internal
     *
     * @param  array<mixed>|null  $body
     * @return array<mixed>
     */
    public function data(string $method, string $path, ?array $body = null): array
    {
        return Data::map($this->request($method, $path, $body) ?? [], 'data');
    }

    /**
     * Sends a request to the API, retrying what waiting fixes, and answers its decoded body.
     *
     * @internal
     *
     * @param  array<mixed>|null  $body
     * @return array<mixed>|null
     */
    public function request(string $method, string $path, ?array $body = null): ?array
    {
        $url = $this->url($path);
        $headers = [
            'Authorization' => "Bearer {$this->apiKey}",
            'Accept' => 'application/json',
            'User-Agent' => 'intosql-php/'.self::VERSION,
        ];
        $options = ['http_errors' => false, 'timeout' => $this->timeout];

        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $options['body'] = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        if ($method === 'POST') {
            $headers['Idempotency-Key'] = self::uuid();
        }

        $options['headers'] = $headers;

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $this->http->request($method, $url, $options);
            } catch (TransferException $exception) {
                if ($attempt >= $this->maxRetries) {
                    throw $exception;
                }

                $this->sleep(self::backoff($attempt, ''));

                continue;
            }

            $status = $response->getStatusCode();

            if ($status >= 200 && $status < 300) {
                return $status === 204 ? null : self::decode($response);
            }

            $error = self::toException($response);

            if (self::retryable($error) && $attempt < $this->maxRetries) {
                $this->sleep(self::backoff($attempt, $response->getHeaderLine('Retry-After')));

                continue;
            }

            throw $error;
        }
    }

    /** @internal */
    public function http(): ClientInterface
    {
        return $this->http;
    }

    /** @internal */
    public function sleep(float $seconds): void
    {
        ($this->sleep)($seconds);
    }

    /** A path under the API, or one of its own absolute addresses (pagination links). */
    private function url(string $path): string
    {
        if (! str_starts_with($path, 'http')) {
            return $this->baseUrl.$path;
        }

        $origin = self::origin($path);

        if ($origin !== self::origin($this->baseUrl)) {
            throw new RuntimeException("Refusing to send the API key to {$origin}.");
        }

        return $path;
    }

    private static function origin(string $url): string
    {
        $parts = parse_url($url) ?: [];
        $port = isset($parts['port']) ? ":{$parts['port']}" : '';

        return strtolower(($parts['scheme'] ?? '').'://'.($parts['host'] ?? '')).$port;
    }

    /** @return array<mixed>|null */
    private static function decode(ResponseInterface $response): ?array
    {
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return is_array($body) ? $body : null;
    }

    private static function toException(ResponseInterface $response): IntoSQLException
    {
        try {
            $body = self::decode($response) ?? [];
        } catch (JsonException) {
            $body = [];
        }

        $error = Data::map($body, 'error');
        $status = $response->getStatusCode();
        $fields = [];

        foreach (Data::map($error, 'fields') as $field => $messages) {
            $fields[(string) $field] = array_values(array_filter(is_array($messages) ? $messages : [], is_string(...)));
        }

        return new IntoSQLException(
            $status,
            Data::nullableString($error, 'code') ?? 'http_error',
            Data::nullableString($error, 'message') ?? "The API answered {$status}.",
            $fields,
            Data::nullableString($error, 'request_id')
                ?? ($response->hasHeader('X-Request-Id') ? $response->getHeaderLine('X-Request-Id') : null),
        );
    }

    private static function retryable(IntoSQLException $error): bool
    {
        return in_array($error->errorCode, self::RETRYABLE_CODES, true)
            || in_array($error->status, self::RETRYABLE_STATUSES, true)
            || $error->status >= 500;
    }

    /** Seconds before the next attempt: `Retry-After` when the answer has it, otherwise doubling from half a second up to 8. */
    private static function backoff(int $attempt, string $retryAfter): float
    {
        return is_numeric($retryAfter) && (float) $retryAfter > 0
            ? (float) $retryAfter
            : min(0.5 * 2 ** $attempt, 8.0);
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
