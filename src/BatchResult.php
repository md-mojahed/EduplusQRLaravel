<?php

namespace Eduplus\Qr;

use Eduplus\Qr\Exceptions\GenerationException;

/**
 * Results of a batch, keyed by your ids and kept in the order you added
 * the rows (the binary finishes rows in parallel, out of order).
 *
 *     foreach ($results as $id => $item) { ... }
 *     $results['stu-7']->dataUri();
 *     $results->failed();
 *
 * @implements \IteratorAggregate<string, BatchItem>
 * @implements \ArrayAccess<string, BatchItem>
 */
final class BatchResult implements \IteratorAggregate, \Countable, \ArrayAccess, \JsonSerializable
{
    /** @var array<string, BatchItem> */
    private $items;

    /** @var string[] */
    private $errors;

    /** @var array<string, mixed> */
    private $summary;

    /** @var string|null */
    private $output;

    /**
     * @param array<string, BatchItem> $items in input order
     * @param string[] $errors batch-level errors (not tied to a row)
     * @param array<string, mixed> $summary
     */
    public function __construct(array $items, array $errors, array $summary, ?string $output = null)
    {
        $this->items = $items;
        $this->errors = $errors;
        $this->summary = $summary;
        $this->output = $output;
    }

    /** All rows, keyed by id. @return array<string, BatchItem> */
    public function all(): array
    {
        return $this->items;
    }

    public function get(string $id): ?BatchItem
    {
        return $this->items[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->items[$id]);
    }

    /** @return array<string, BatchItem> */
    public function successful(): array
    {
        return array_filter($this->items, function (BatchItem $i) {
            return $i->ok();
        });
    }

    /** @return array<string, BatchItem> */
    public function failed(): array
    {
        return array_filter($this->items, function (BatchItem $i) {
            return $i->failed();
        });
    }

    /** @return array<string, BatchItem> */
    public function skipped(): array
    {
        return array_filter($this->items, function (BatchItem $i) {
            return $i->skipped();
        });
    }

    /** Error message per failed id. @return array<string, string> */
    public function errors(): array
    {
        $out = [];
        foreach ($this->failed() as $id => $item) {
            $out[$id] = (string) $item->error();
        }
        return $out;
    }

    /** Errors not tied to a row (e.g. the ZIP could not be written). @return string[] */
    public function batchErrors(): array
    {
        return $this->errors;
    }

    /** True when every row succeeded (or was skipped) and nothing else failed. */
    public function allSuccessful(): bool
    {
        return !$this->failed() && !$this->errors;
    }

    /** The ZIP or PDF file written (zip / sheet modes). */
    public function output(): ?string
    {
        return $this->output;
    }

    /** Number of PDF pages (sheet mode). */
    public function pages(): int
    {
        return (int) ($this->summary['pages'] ?? 0);
    }

    /** The binary's summary: total, ok, failed, skipped (and pages). @return array<string, mixed> */
    public function summary(): array
    {
        return $this->summary;
    }

    /**
     * Throw if anything failed, e.g. $batch->toBase64()->throw().
     *
     * @throws GenerationException
     */
    public function throw(): self
    {
        if ($this->allSuccessful()) {
            return $this;
        }
        $msgs = $this->errors;
        foreach ($this->errors() as $id => $err) {
            $msgs[] = "[{$id}] {$err}";
        }
        $n = count($msgs);
        $shown = array_slice($msgs, 0, 5);
        throw new GenerationException(
            "{$n} batch error(s): " . implode('; ', $shown) . ($n > 5 ? '; ...' : ''),
            GenerationException::BATCH_PARTIAL
        );
    }

    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @param mixed $offset */
    public function offsetExists($offset): bool
    {
        return isset($this->items[(string) $offset]);
    }

    /**
     * @param mixed $offset
     * @return BatchItem|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->items[(string) $offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        throw new \LogicException('BatchResult is read-only.');
    }

    /** @param mixed $offset */
    public function offsetUnset($offset): void
    {
        throw new \LogicException('BatchResult is read-only.');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'output' => $this->output,
            'errors' => $this->errors,
            'items' => array_map(function (BatchItem $i) {
                return $i->toArray();
            }, $this->items),
        ];
    }

    /** @return array<string, mixed> */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
