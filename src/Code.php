<?php

namespace Eduplus\Qr;

use Eduplus\Qr\Concerns\HasOptions;
use Eduplus\Qr\Exceptions\EduplusException;
use Eduplus\Qr\Exceptions\GenerationException;

/**
 * One QR code or barcode. Configure it fluently, then save it or get the
 * image back:
 *
 *     $qr->qr('https://eduplus-bd.com')->style('rounded')->save($path);
 *     $qr->barcode('STU-1', 'code128')->showText()->toDataUri();
 */
class Code
{
    use HasOptions;

    /** @var Client */
    private $client;

    /** @var string|null */
    private $text;

    /** @var string|null */
    private $payloadKind;

    /** @var array<string, string> */
    private $fields = [];

    /**
     * @param array<string, string|int|float|bool> $defaults
     */
    public function __construct(Client $client, array $defaults = [])
    {
        $this->client = $client;
        $this->options($defaults);
    }

    /** Text to encode. */
    public function text(string $text): self
    {
        $this->text = $text;
        $this->payloadKind = null;
        $this->fields = [];
        return $this;
    }

    /**
     * Build the text from a standard format (wifi, vcard, mecard, email,
     * sms, tel, geo, event, emv, url).
     *
     * @param array<string, string|int|float|bool> $fields e.g. ['ssid' => 'Campus', 'password' => 'x']
     */
    public function payload(string $kind, array $fields): self
    {
        $this->text = null;
        $this->payloadKind = $kind;
        $this->fields = [];
        foreach ($fields as $k => $v) {
            if ($v === null) {
                continue;
            }
            if (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            }
            if (!is_scalar($v)) {
                throw new EduplusException("Payload field '{$k}' must be a string, number or boolean.");
            }
            $this->fields[(string) $k] = (string) $v;
        }
        return $this;
    }

    /**
     * Save to a file (.png, .svg or .pdf; missing folders are created).
     * Use an absolute path such as storage_path('app/public/qr/1.png').
     *
     * @throws GenerationException
     */
    public function save(string $path): Result
    {
        $args = array_merge($this->inputArgs(), ['-o', $path, '-json'], $this->client->baseDirArgs(), self::optionsToArgs($this->options));
        return $this->runJson($args);
    }

    /**
     * Raw image bytes (no file is written).
     *
     * @param string|null $format png, svg or pdf (default: the format option, else png)
     * @throws GenerationException
     */
    public function toString(?string $format = null): string
    {
        $options = $this->options;
        if ($format !== null) {
            $options['format'] = strtolower($format);
        }
        $args = array_merge($this->inputArgs(), ['-o', '-'], self::optionsToArgs($options));
        $res = $this->client->run($args);
        if (!$res->successful()) {
            throw self::failure($res->exitCode, $res->stderr, $res->timedOut);
        }
        if ($res->stderr !== '' && $this->client->warningHandler()) {
            call_user_func($this->client->warningHandler(), trim($res->stderr));
        }
        return $res->stdout;
    }

    /** @throws GenerationException */
    public function toPng(): string
    {
        return $this->toString('png');
    }

    /** @throws GenerationException */
    public function toSvg(): string
    {
        return $this->toString('svg');
    }

    /** @throws GenerationException */
    public function toPdf(): string
    {
        return $this->toString('pdf');
    }

    /**
     * Base64 image data (no file is written).
     *
     * @throws GenerationException
     */
    public function toBase64(?string $format = null): string
    {
        return (string) $this->generateInMemory($format)->base64();
    }

    /**
     * "data:image/png;base64,..." ready for <img src="...">.
     *
     * @throws GenerationException
     */
    public function toDataUri(?string $format = null): string
    {
        return (string) $this->generateInMemory($format)->dataUri();
    }

    /**
     * Generate in memory and return the full Result (base64(), dataUri(),
     * width(), qrVersion(), ...).
     *
     * @throws GenerationException
     */
    public function generateInMemory(?string $format = null): Result
    {
        $options = $this->options;
        if ($format !== null) {
            $options['format'] = strtolower($format);
        }
        $args = array_merge($this->inputArgs(), ['-base64', '-json'], self::optionsToArgs($options));
        $res = $this->client->run($args);
        $data = self::decodeLastJson($res->stdout);
        if ($data === null || ($data['status'] ?? '') !== 'ok') {
            throw self::failure($res->exitCode, isset($data['error']) ? (string) $data['error'] : $res->stderr, $res->timedOut);
        }
        $result = (array) ($data['result'] ?? []);
        $result['base64'] = (string) ($data['base64'] ?? '');
        $this->client->reportWarning($result);
        return new Result($result);
    }

    /**
     * Check everything (data, sizes, colours, logo) without generating a file.
     *
     * @throws GenerationException when the code cannot be generated
     */
    public function validate(): Result
    {
        $args = array_merge($this->inputArgs(), ['-dry-run', '-json'], self::optionsToArgs($this->options));
        return $this->runJson($args);
    }

    /** True when the code can be generated with the current options. */
    public function isValid(): bool
    {
        try {
            $this->validate();
            return true;
        } catch (GenerationException $e) {
            return false;
        }
    }

    /**
     * A Laravel HTTP response with the image (inline, or as a download when
     * a filename is given).
     *
     * @return \Illuminate\Http\Response
     * @throws GenerationException
     */
    public function toResponse(?string $format = null, ?string $downloadName = null)
    {
        if (!class_exists('Illuminate\Http\Response')) {
            throw new EduplusException('toResponse() needs Laravel (illuminate/http).');
        }
        $format = $format ?? (string) ($this->options['format'] ?? 'png');
        $body = $this->toString($format);
        $headers = [
            'Content-Type' => self::mimeFor($format),
            'Content-Length' => (string) strlen($body),
            'Cache-Control' => 'public, max-age=86400',
        ];
        if ($downloadName !== null) {
            $headers['Content-Disposition'] = 'attachment; filename="' . addcslashes($downloadName, "\"\\") . '"';
        }
        return new \Illuminate\Http\Response($body, 200, $headers);
    }

    /**
     * The command-line arguments this code would run with (for debugging).
     *
     * @return string[]
     */
    public function toArguments(): array
    {
        return array_merge($this->inputArgs(), self::optionsToArgs($this->options));
    }

    /**
     * Batch row representation (text or payload plus options).
     *
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        $row = $this->options;
        if ($this->payloadKind !== null) {
            $row['payload'] = $this->payloadKind;
            $row['p'] = (object) $this->fields;
        } else {
            $row['t'] = (string) $this->text;
        }
        return $row;
    }

    public static function mimeFor(string $format): string
    {
        switch (strtolower($format)) {
            case 'svg':
                return 'image/svg+xml';
            case 'pdf':
                return 'application/pdf';
            default:
                return 'image/png';
        }
    }

    /** @return string[] */
    private function inputArgs(): array
    {
        if ($this->payloadKind !== null) {
            $args = ['-payload', $this->payloadKind];
            foreach ($this->fields as $k => $v) {
                $args[] = '-p';
                $args[] = $k . '=' . $v;
            }
            return $args;
        }
        if ($this->text === null || $this->text === '') {
            throw new GenerationException('input text required', GenerationException::INVALID_INPUT);
        }
        return ['-t', $this->text];
    }

    /** @param string[] $args */
    private function runJson(array $args): Result
    {
        $res = $this->client->run($args);
        $data = self::decodeLastJson($res->stdout);
        if ($data === null || ($data['status'] ?? '') === 'error' || !isset($data['result'])) {
            throw self::failure($res->exitCode, isset($data['error']) ? (string) $data['error'] : $res->stderr, $res->timedOut);
        }
        $result = (array) $data['result'];
        $this->client->reportWarning($result);
        return new Result($result);
    }

    /** @return array<string, mixed>|null */
    private static function decodeLastJson(string $stdout): ?array
    {
        $lines = preg_split('/\r\n|\n/', trim($stdout)) ?: [];
        $last = end($lines);
        if ($last === false || $last === '') {
            return null;
        }
        $data = json_decode($last, true);
        return is_array($data) ? $data : null;
    }

    private static function failure(int $exitCode, string $message, bool $timedOut): GenerationException
    {
        if ($timedOut) {
            return new GenerationException('EduplusQR timed out', GenerationException::TIMEOUT);
        }
        $message = trim($message);
        if ($message === '') {
            $message = 'EduplusQR failed with exit code ' . $exitCode;
        }
        return new GenerationException($message, $exitCode, $message);
    }
}
