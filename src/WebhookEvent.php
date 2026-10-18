<?php

declare(strict_types=1);

namespace IntoSQL;

/** What a webhook call carries. `id` is the same on every retry: handle each event once. */
final class WebhookEvent
{
    /**
     * @param  string  $type  `conversion.done`, `conversion.failed`, `conversion.canceled` or `ping`.
     * @param  Conversion|array<mixed>  $data  The conversion for `conversion.*` events; for `ping` (or a type added later), the decoded object, such as `['sent_at' => '...']`.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $createdAt,
        public readonly Conversion|array $data,
    ) {}

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        $type = Data::string($data, 'type');
        $payload = Data::map($data, 'data');

        return new self(
            Data::string($data, 'id'),
            $type,
            Data::string($data, 'created_at'),
            str_starts_with($type, 'conversion.') ? Conversion::fromArray($payload) : $payload,
        );
    }
}
