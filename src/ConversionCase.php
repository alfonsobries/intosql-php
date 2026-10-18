<?php

declare(strict_types=1);

namespace IntoSQL;

/** A conversion sent to IntoSQL because it didn't come out right. */
final class ConversionCase
{
    /**
     * @param  string  $share  `file` or `report`.
     * @param  string  $status  `open`, `fixed` or `closed`.
     * @param  string  $expiresAt  When the case and its file are deleted.
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $conversionId,
        public readonly string $share,
        public readonly string $status,
        public readonly ?string $createdAt,
        public readonly string $expiresAt,
    ) {}

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Data::string($data, 'id'),
            conversionId: Data::nullableString($data, 'conversion_id'),
            share: Data::string($data, 'share'),
            status: Data::string($data, 'status'),
            createdAt: Data::nullableString($data, 'created_at'),
            expiresAt: Data::string($data, 'expires_at'),
        );
    }
}
