<?php

namespace Eduplus\Qr;

/**
 * Outcome of one row of a batch.
 */
final class BatchItem implements \JsonSerializable
{
    public const OK = 'ok';
    public const ERROR = 'error';
    public const SKIPPED = 'skipped';

    /** @var string */
    private $id;

    /** @var int */
    private $line;

    /** @var string */
    private $status;

    /** @var Result|null */
    private $result;

    /** @var string|null */
    private $error;

    public function __construct(string $id, int $line, string $status, ?Result $result, ?string $error = null)
    {
        $this->id = $id;
        $this->line = $line;
        $this->status = $status;
        $this->result = $result;
        $this->error = $error;
    }

    /** Your id for this row (as passed to Batch::add()). */
    public function id(): string
    {
        return $this->id;
    }

    /** Position of the row in the batch (1-based). */
    public function line(): int
    {
        return $this->line;
    }

    /** ok, error or skipped. */
    public function status(): string
    {
        return $this->status;
    }

    public function ok(): bool
    {
        return $this->status === self::OK;
    }

    public function failed(): bool
    {
        return $this->status === self::ERROR;
    }

    public function skipped(): bool
    {
        return $this->status === self::SKIPPED;
    }

    /** Error message for failed rows. */
    public function error(): ?string
    {
        return $this->error;
    }

    public function result(): ?Result
    {
        return $this->result;
    }

    /** Written file path (files mode), or the entry name inside a ZIP. */
    public function path(): ?string
    {
        return $this->result ? $this->result->path() : null;
    }

    /** Base64 image (in-memory mode). */
    public function base64(): ?string
    {
        return $this->result ? $this->result->base64() : null;
    }

    /** "data:image/png;base64,..." (in-memory mode). */
    public function dataUri(): ?string
    {
        return $this->result ? $this->result->dataUri() : null;
    }

    /** Raw image bytes (in-memory mode). */
    public function contents(): ?string
    {
        return $this->result ? $this->result->contents() : null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'line' => $this->line,
            'status' => $this->status,
            'error' => $this->error,
            'result' => $this->result ? $this->result->toArray() : null,
        ];
    }

    /** @return array<string, mixed> */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
