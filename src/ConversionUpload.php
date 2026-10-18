<?php

declare(strict_types=1);

namespace IntoSQL;

/** Where to `PUT` the file of a conversion just opened with `create`, and the headers to send. */
final class ConversionUpload
{
    /**
     * @param  array<string, string>  $headers  Headers with several values are joined with ", ".
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers,
    ) {}

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        $headers = [];

        foreach (Data::map($data, 'headers') as $name => $value) {
            $values = array_filter(is_array($value) ? $value : [$value], is_string(...));
            $headers[(string) $name] = implode(', ', $values);
        }

        return new self(Data::string($data, 'method'), Data::string($data, 'url'), $headers);
    }
}
