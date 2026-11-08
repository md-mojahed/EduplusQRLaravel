<?php

namespace Eduplus\Qr;

/**
 * Details of one generated code, as reported by the binary.
 */
final class Result implements \JsonSerializable
{
    /** @var array<string, mixed> */
    private $data;

    /**
     * @param array<string, mixed> $data the binary's JSON "result" object
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /** The caller's id (batches only). */
    public function id(): ?string
    {
        return isset($this->data['id']) ? (string) $this->data['id'] : null;
    }

    /** Path of the written file (null for in-memory results). */
    public function path(): ?string
    {
        return isset($this->data['output']) && $this->data['output'] !== '' ? (string) $this->data['output'] : null;
    }

    public function type(): string
    {
        return (string) ($this->data['type'] ?? '');
    }

    /** The exact data encoded (e.g. with the computed check digit). */
    public function content(): string
    {
        return (string) ($this->data['content'] ?? '');
    }

    /** png, svg or pdf. */
    public function format(): string
    {
        return (string) ($this->data['format'] ?? '');
    }

    public function mimeType(): string
    {
        return Code::mimeFor($this->format());
    }

    /** Image width in pixels. */
    public function width(): int
    {
        return (int) ($this->data['width'] ?? 0);
    }

    /** Image height in pixels. */
    public function height(): int
    {
        return (int) ($this->data['height'] ?? 0);
    }

    /** Pixels per module that were used. */
    public function module(): int
    {
        return (int) ($this->data['module'] ?? 0);
    }

    public function qrVersion(): ?int
    {
        return isset($this->data['qr']['version']) ? (int) $this->data['qr']['version'] : null;
    }

    public function ecc(): ?string
    {
        return isset($this->data['qr']['ecc']) ? (string) $this->data['qr']['ecc'] : null;
    }

    public function mask(): ?int
    {
        return isset($this->data['qr']['mask']) ? (int) $this->data['qr']['mask'] : null;
    }

    /** Size of the generated file in bytes. */
    public function bytes(): int
    {
        return (int) ($this->data['bytes'] ?? 0);
    }

    public function sha256(): ?string
    {
        return isset($this->data['sha256']) ? (string) $this->data['sha256'] : null;
    }

    /** True when the image was decoded back and matched (verify / logo). */
    public function verified(): bool
    {
        return !empty($this->data['verified']);
    }

    public function warning(): ?string
    {
        return isset($this->data['warning']) && $this->data['warning'] !== '' ? (string) $this->data['warning'] : null;
    }

    public function skipped(): bool
    {
        return !empty($this->data['skipped']);
    }

    /** Base64 image data (in-memory results only). */
    public function base64(): ?string
    {
        return isset($this->data['base64']) ? (string) $this->data['base64'] : null;
    }

    /** Raw image bytes (in-memory results only). */
    public function contents(): ?string
    {
        $b = $this->base64();
        if ($b === null) {
            return null;
        }
        $raw = base64_decode($b, true);
        return $raw === false ? null : $raw;
    }

    /** "data:image/png;base64,..." for <img src>, or null without base64 data. */
    public function dataUri(): ?string
    {
        $b = $this->base64();
        return $b === null ? null : 'data:' . $this->mimeType() . ';base64,' . $b;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    /** @return array<string, mixed> */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->data;
    }
}
