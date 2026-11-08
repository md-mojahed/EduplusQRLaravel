<?php

namespace Eduplus\Qr;

use Eduplus\Qr\Concerns\HasOptions;
use Eduplus\Qr\Exceptions\EduplusException;
use Eduplus\Qr\Exceptions\GenerationException;

/**
 * Many codes in one process (all CPU cores). Options set on the batch are
 * defaults for every row; each row can override them.
 *
 *     $results = $qr->batch()->type('code128')->showText()
 *         ->add('stu-1', 'STU-0001')
 *         ->add('stu-2', 'STU-0002', ['label' => 'Rahim'])
 *         ->toBase64();
 *
 *     foreach ($results as $id => $item) {
 *         echo $item->ok() ? $item->dataUri() : $item->error();
 *     }
 */
class Batch
{
    use HasOptions;

    /** @var Client */
    private $client;

    /** @var array<string, array<string, mixed>> rows keyed by id, in order */
    private $rows = [];

    /** @var string|null */
    private $sequence;

    /** @var int|null */
    private $workers;

    /** @var bool */
    private $skipExisting = false;

    /** @var string|null */
    private $manifest;

    /**
     * @param array<string, string|int|float|bool> $defaults
     */
    public function __construct(Client $client, array $defaults = [])
    {
        $this->client = $client;
        $this->options($defaults);
    }

    /**
     * Add one row.
     *
     * @param string|int|null $id your identifier (e.g. a student id); null = automatic
     * @param array<string, string|int|float|bool> $options per-row options, e.g. ['type' => 'qr', 'label' => 'Rahim']
     */
    public function add($id, string $text, array $options = []): self
    {
        $row = $this->rowOptions($options);
        $row['t'] = $text;
        return $this->push($id, $row);
    }

    /**
     * Add a row built from a content format (wifi, vcard, email, ...).
     *
     * @param string|int|null $id
     * @param array<string, string|int|float|bool> $fields
     * @param array<string, string|int|float|bool> $options
     */
    public function addPayload($id, string $kind, array $fields, array $options = []): self
    {
        $code = (new Code($this->client))->payload($kind, $fields);
        $row = $this->rowOptions($options);
        return $this->push($id, array_merge($row, $code->toRow()));
    }

    /**
     * Add a fully configured Code (its options override the batch defaults).
     *
     * @param string|int|null $id
     */
    public function addCode($id, Code $code): self
    {
        return $this->push($id, $code->toRow());
    }

    /**
     * Add many texts at once. Array keys become ids.
     *
     *     ->addMany($students->pluck('roll', 'id')->all())
     *
     * @param iterable<string|int, string> $texts
     * @param array<string, string|int|float|bool> $options
     */
    public function addMany(iterable $texts, array $options = []): self
    {
        foreach ($texts as $id => $text) {
            $this->add($id, (string) $text, $options);
        }
        return $this;
    }

    /**
     * Generate a numbered series instead of adding rows, e.g.
     * "LIB-{00001..05000}" or "{1..100..5}". Results are keyed by the text.
     */
    public function sequence(string $pattern): self
    {
        if ($this->rows) {
            throw new EduplusException('A batch uses either sequence() or add(), not both.');
        }
        $this->sequence = $pattern;
        return $this;
    }

    /** Parallel workers (default: number of CPUs). */
    public function workers(int $workers): self
    {
        $this->workers = max(1, $workers);
        return $this;
    }

    /** Leave files that already exist untouched (resume an interrupted run). */
    public function skipExisting(bool $skip = true): self
    {
        $this->skipExisting = $skip;
        return $this;
    }

    /** Also write a .csv or .jsonl record of every row (with sha256). */
    public function manifest(string $path): self
    {
        $this->manifest = $path;
        return $this;
    }

    /** Number of rows added. */
    public function count(): int
    {
        return count($this->rows);
    }

    // ------------------------------------------------------------ run

    /**
     * Generate every code in memory and return the images as base64 /
     * data URIs. Nothing is written to disk.
     *
     * @param string|null $format png, svg or pdf
     */
    public function toBase64(?string $format = null): BatchResult
    {
        $args = ['-base64'];
        if ($format !== null) {
            $args[] = '-format';
            $args[] = strtolower($format);
        }
        return $this->execute($args, null, null);
    }

    /**
     * Write each code to a file. The path template may contain {id} (your
     * id, made file-name safe), {t} (the text), {n} (row number), {type}
     * and {shard} (2 hex characters to spread files over 256 folders).
     *
     *     ->saveTo(storage_path('app/public/cards/{id}.png'))
     */
    public function saveTo(string $pathTemplate): BatchResult
    {
        return $this->execute([], $pathTemplate, null);
    }

    /**
     * Write every code into one ZIP file. $nameTemplate names the entries
     * (same placeholders as saveTo()).
     */
    public function toZip(string $zipPath, string $nameTemplate = '{id}.png'): BatchResult
    {
        return $this->execute(['-zip', $zipPath], $nameTemplate, $zipPath);
    }

    /**
     * Lay the codes out on printable PDF label sheets, in the order added.
     *
     * @param string $pageSize a4, a5, a6, a3, letter, legal or "WIDTHxHEIGHT" in mm
     * @param string $grid labels per page as "COLUMNSxROWS"
     */
    public function toSheet(string $pdfPath, string $pageSize = 'a4', string $grid = '3x8', ?float $marginMm = null, ?float $gapMm = null): BatchResult
    {
        $args = ['-sheet', $pageSize, '-grid', $grid, '-o', $pdfPath];
        if ($marginMm !== null) {
            $args[] = '-sheet-margin';
            $args[] = (string) $marginMm;
        }
        if ($gapMm !== null) {
            $args[] = '-gap';
            $args[] = (string) $gapMm;
        }
        return $this->execute($args, null, $pdfPath);
    }

    /** Check every row (check digits, lengths, sizes) without writing anything. */
    public function validate(): BatchResult
    {
        return $this->execute(['-dry-run'], null, null);
    }

    // ------------------------------------------------------------ internals

    /**
     * @param string|int|null $id
     * @param array<string, mixed> $row
     */
    private function push($id, array $row): self
    {
        if ($this->sequence !== null) {
            throw new EduplusException('A batch uses either sequence() or add(), not both.');
        }
        $id = $id === null ? (string) (count($this->rows) + 1) : (string) $id;
        if ($id === '') {
            throw new EduplusException('Batch ids must not be empty.');
        }
        if (isset($this->rows[$id])) {
            throw new EduplusException("Duplicate batch id '{$id}'.");
        }
        $row['id'] = $id;
        $this->rows[$id] = $row;
        return $this;
    }

    /**
     * @param array<string, string|int|float|bool> $options
     * @return array<string, string|int|float|bool>
     */
    private function rowOptions(array $options): array
    {
        $probe = new Code($this->client);
        $probe->options($options); // validates names and values
        return $probe->getOptions();
    }

    /** Replace {id} with each row's file-name-safe id. */
    private static function expandId(string $template, string $id): string
    {
        $safe = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $id), '._');
        return str_replace('{id}', $safe === '' ? 'code' : $safe, $template);
    }

    /**
     * @param string[] $modeArgs
     */
    private function execute(array $modeArgs, ?string $outputTemplate, ?string $outputFile): BatchResult
    {
        $args = $modeArgs;
        $stdin = '';
        if ($this->sequence !== null) {
            $args[] = '-seq';
            $args[] = $this->sequence;
            if ($outputTemplate !== null) {
                if (strpos($outputTemplate, '{id}') !== false) {
                    $outputTemplate = str_replace('{id}', '{t}', $outputTemplate);
                }
                $args[] = '-o';
                $args[] = $outputTemplate;
            }
        } else {
            if (!$this->rows) {
                return new BatchResult([], [], ['total' => 0, 'ok' => 0, 'failed' => 0, 'skipped' => 0], $outputFile);
            }
            $args[] = '-batch';
            $args[] = '-';
            $args[] = '-batch-format';
            $args[] = 'jsonl';
            $lines = [];
            foreach ($this->rows as $id => $row) {
                if ($outputTemplate !== null) {
                    $row['o'] = self::expandId($outputTemplate, (string) $id);
                }
                $json = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($json === false) {
                    throw new EduplusException("Row '{$id}' cannot be encoded as JSON: " . json_last_error_msg());
                }
                $lines[] = $json;
            }
            $stdin = implode("\n", $lines) . "\n";
        }
        $args[] = '-json';
        if ($this->workers !== null) {
            $args[] = '-workers';
            $args[] = (string) $this->workers;
        }
        if ($this->skipExisting) {
            $args[] = '-skip-existing';
        }
        if ($this->manifest !== null) {
            $args[] = '-manifest';
            $args[] = $this->manifest;
        }
        $args = array_merge($args, $this->client->baseDirArgs(), self::optionsToArgs($this->options));

        $res = $this->client->run($args, $stdin);
        if ($res->timedOut) {
            throw new GenerationException('EduplusQR batch timed out; raise the timeout or split the batch', GenerationException::TIMEOUT);
        }
        return $this->parse($res->stdout, $res->stderr, $res->exitCode, $outputFile);
    }

    private function parse(string $stdout, string $stderr, int $exitCode, ?string $outputFile): BatchResult
    {
        $byId = [];
        $errors = [];
        $summary = null;
        $order = array_keys($this->rows);
        foreach (preg_split('/\r\n|\n/', $stdout) ?: [] as $line) {
            if ($line === '') {
                continue;
            }
            $d = json_decode($line, true);
            if (!is_array($d)) {
                continue;
            }
            $status = (string) ($d['status'] ?? '');
            if ($status === 'summary') {
                $summary = $d;
                continue;
            }
            $lineNo = (int) ($d['line'] ?? 0);
            if ($status === 'error' && $lineNo === 0) {
                $errors[] = (string) ($d['error'] ?? 'unknown error');
                continue;
            }
            $result = isset($d['result']) && is_array($d['result']) ? new Result($d['result']) : null;
            $id = isset($d['id']) ? (string) $d['id'] : ($result && $result->id() !== null ? (string) $result->id() : null);
            if ($id === null) {
                // Sequences have no ids: key by the generated text.
                $id = $result ? $result->content() : 'line:' . $lineNo;
                if ($result === null && $this->sequence === null && isset($order[$lineNo - 1])) {
                    $id = (string) $order[$lineNo - 1];
                }
            }
            if ($status === 'error') {
                $byId[$id] = new BatchItem($id, $lineNo, BatchItem::ERROR, null, (string) ($d['error'] ?? 'unknown error'));
            } else {
                if ($result) {
                    $this->client->reportWarning($result->toArray());
                }
                $byId[$id] = new BatchItem($id, $lineNo, $status === 'skipped' ? BatchItem::SKIPPED : BatchItem::OK, $result);
            }
        }

        if ($summary === null) {
            // The batch could not start (bad option, unreadable input, ...).
            $msg = $errors ? implode('; ', $errors) : trim($stderr);
            throw new GenerationException($msg !== '' ? $msg : 'EduplusQR batch failed with exit code ' . $exitCode, $exitCode ?: 1, $stderr);
        }

        // Keep the caller's order; sequences keep the binary's line order.
        $items = [];
        if ($this->sequence === null) {
            foreach ($order as $id) {
                if (isset($byId[$id])) {
                    $items[$id] = $byId[$id];
                    unset($byId[$id]);
                }
            }
        } else {
            uasort($byId, function (BatchItem $a, BatchItem $b) {
                return $a->line() <=> $b->line();
            });
        }
        foreach ($byId as $id => $item) {
            $items[$id] = $item;
        }
        unset($summary['status']);
        return new BatchResult($items, $errors, $summary, $outputFile);
    }
}
