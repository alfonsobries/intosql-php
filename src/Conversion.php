<?php

declare(strict_types=1);

namespace IntoSQL;

/** A conversion as the API answers it. Times are ISO 8601 strings. */
final class Conversion
{
    /**
     * @param  int  $bytes  The file's size; 0 until a file sent by URL arrives.
     * @param  array<mixed>  $spec  The conversion request (a `JobSpec`).
     * @param  array<mixed>|null  $report  What the conversion did (a `JobReport`), once it ran.
     * @param  ConversionFailure|null  $error  When failed: why.
     * @param  ConversionOutput|null  $output  When done: the result, downloadable for ten minutes.
     * @param  ConversionUpload|null  $upload  Right after `create`: where to `PUT` the file.
     * @param  string|null  $finishedAt  When it ended: done, failed or canceled.
     * @param  string  $expiresAt  When its files are deleted.
     */
    public function __construct(
        public readonly string $id,
        public readonly ConversionStatus $status,
        public readonly string $name,
        public readonly int $bytes,
        public readonly array $spec,
        public readonly ?array $report,
        public readonly ?ConversionFailure $error,
        public readonly ?ConversionOutput $output,
        public readonly ?ConversionUpload $upload,
        public readonly ?string $createdAt,
        public readonly ?string $startedAt,
        public readonly ?string $finishedAt,
        public readonly string $expiresAt,
    ) {}

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        $error = Data::nullableMap($data, 'error');
        $output = Data::nullableMap($data, 'output');
        $upload = Data::nullableMap($data, 'upload');

        return new self(
            id: Data::string($data, 'id'),
            status: ConversionStatus::fromApi(Data::string($data, 'status')),
            name: Data::string($data, 'name'),
            bytes: Data::int($data, 'bytes'),
            spec: Data::map($data, 'spec'),
            report: Data::nullableMap($data, 'report'),
            error: $error === null ? null : ConversionFailure::fromArray($error),
            output: $output === null ? null : ConversionOutput::fromArray($output),
            upload: $upload === null ? null : ConversionUpload::fromArray($upload),
            createdAt: Data::nullableString($data, 'created_at'),
            startedAt: Data::nullableString($data, 'started_at'),
            finishedAt: Data::nullableString($data, 'finished_at'),
            expiresAt: Data::string($data, 'expires_at'),
        );
    }

    /** `report` read into classes, or null before the conversion ran. */
    public function jobReport(): ?JobReport
    {
        return $this->report === null ? null : JobReport::fromArray($this->report);
    }
}
