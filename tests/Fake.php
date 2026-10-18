<?php

declare(strict_types=1);

namespace IntoSQL\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use IntoSQL\IntoSQL;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/** A fake API answering each call with the next response in line, and remembering every call. */
final class Fake
{
    public const BASE = 'https://intosql.test/api/v1';

    public readonly IntoSQL $client;

    /** @var array<int, array{request: RequestInterface, options: array<string, mixed>}> */
    public array $history = [];

    /** @var list<float> */
    public array $sleeps = [];

    public function __construct(ResponseInterface|Throwable ...$responses)
    {
        $this->client = new IntoSQL(
            apiKey: 'key_1',
            baseUrl: self::BASE.'/',
            httpClient: self::http($responses, $this->history),
            sleep: function (float $seconds): void {
                $this->sleeps[] = $seconds;
            },
        );
    }

    /**
     * A Guzzle client that answers from `$responses` and records each call in `$history`.
     *
     * @param  array<ResponseInterface|Throwable>  $responses
     * @param  array<int, array{request: RequestInterface, options: array<string, mixed>}>  $history
     */
    public static function http(array $responses, array &$history): Client
    {
        $stack = HandlerStack::create(new MockHandler(array_values($responses)));
        $stack->push(Middleware::tap(static function (RequestInterface $request, array $options) use (&$history): void {
            $history[] = ['request' => $request, 'options' => $options];
        }));

        return new Client(['handler' => $stack]);
    }

    /** @return list<string> */
    public function calls(): array
    {
        return array_values(array_map(
            static fn (array $call): string => $call['request']->getMethod().' '.$call['request']->getUri(),
            $this->history,
        ));
    }

    public function request(int $index): RequestInterface
    {
        return $this->history[$index]['request'];
    }

    /**
     * The `output` of the spec a call sent.
     *
     * @return array<mixed>
     */
    public function sentOutput(int $index): array
    {
        $spec = $this->sent($index)['spec'] ?? null;
        $output = is_array($spec) ? $spec['output'] ?? null : null;

        return is_array($output) ? $output : [];
    }

    /** @return array<mixed> */
    public function sent(int $index): array
    {
        $body = json_decode((string) $this->request($index)->getBody(), true);

        return is_array($body) ? $body : [];
    }
}
