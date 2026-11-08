<?php

namespace Eduplus\Qr;

use Eduplus\Qr\Exceptions\BinaryNotFoundException;
use Eduplus\Qr\Exceptions\GenerationException;
use Eduplus\Qr\Process\ProcessResult;
use Eduplus\Qr\Process\Runner;

/**
 * The configured connection to the EduplusQR binary, shared by QrCode and
 * BarCode. You normally don't use it directly: in Laravel it is built from
 * config('eduplus.qr'); elsewhere call QrCode::configure([...]).
 *
 * It can also be used on its own (e.g. two differently configured
 * instances in one app):
 *
 *     $client = new Client('/usr/local/bin/EduplusQR', ['timeout' => 60]);
 *     $client->qr('https://eduplus-bd.com')->save('/tmp/qr.png');
 */
class Client
{
    /** Binary file name prefix. */
    public const BINARY = 'EduplusQR';

    /** @var string|null */
    private $binary;

    /** @var string[] */
    private $searchDirs;

    /** @var float */
    private $timeout;

    /** @var string|null */
    private $baseDir;

    /** @var array<string, string|int|float|bool> */
    private $defaults;

    /** @var array<string, string|int|float|bool> */
    private $qrDefaults;

    /** @var array<string, string|int|float|bool> */
    private $barcodeDefaults;

    /** @var int|null */
    private $workers;

    /** @var callable|null */
    private $warningHandler;

    /** @var self|null */
    private static $default;

    /**
     * @param string|null $binary full path to the binary; null = auto-detect
     * @param array<string, mixed> $config timeout (seconds), base_dir, binary_dir, workers,
     *                                     defaults / qr_defaults / barcode_defaults (option => value)
     */
    public function __construct(?string $binary = null, array $config = [])
    {
        $this->binary = $binary !== null && $binary !== '' ? $binary : null;
        $dirs = $config['binary_dir'] ?? [];
        $this->searchDirs = array_values(array_filter(is_array($dirs) ? $dirs : [$dirs], 'strlen'));
        $this->timeout = (float) ($config['timeout'] ?? 120);
        $this->baseDir = isset($config['base_dir']) && $config['base_dir'] !== '' ? (string) $config['base_dir'] : null;
        $this->defaults = self::arrayOf($config, 'defaults');
        $this->qrDefaults = self::arrayOf($config, 'qr_defaults');
        $this->barcodeDefaults = self::arrayOf($config, 'barcode_defaults');
        $this->workers = isset($config['workers']) && $config['workers'] ? (int) $config['workers'] : null;
        $this->warningHandler = $config['on_warning'] ?? null;
    }

    /**
     * Build a client from a config array (the Laravel config file format).
     *
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(isset($config['binary']) ? (string) $config['binary'] : null, $config);
    }

    // ------------------------------------------------------------ codes

    /** A QR code. */
    public function qr(string $text): Code
    {
        return $this->code($text, 'qr');
    }

    /** A barcode: code128 (default), ean13, code39, upca, itf14, gs1-128, ... */
    public function barcode(string $text, string $type = 'code128'): Code
    {
        return $this->code($text, $type);
    }

    /** Any supported type (see types()). */
    public function code(string $text, string $type): Code
    {
        return $this->newCode($type)->text($text);
    }

    /**
     * A QR code built from a standard content format.
     *
     * @param string $kind url, wifi, vcard, mecard, email, sms, tel, geo, event or emv
     * @param array<string, string|int|float|bool> $fields
     */
    public function payload(string $kind, array $fields): Code
    {
        return $this->newCode('qr')->payload($kind, $fields);
    }

    /** Join a Wi-Fi network. $auth: wpa, wpa3, wep or nopass (default: wpa if a password is set). */
    public function wifi(string $ssid, ?string $password = null, ?string $auth = null, bool $hidden = false): Code
    {
        return $this->payload('wifi', ['ssid' => $ssid, 'password' => $password, 'auth' => $auth, 'hidden' => $hidden ? 'true' : null]);
    }

    /**
     * Contact card. Fields: name, first, last, org, title, phone, mobile,
     * work_phone, email, url, address, note, birthday (YYYY-MM-DD).
     *
     * @param array<string, string> $fields
     */
    public function vcard(array $fields): Code
    {
        return $this->payload('vcard', $fields);
    }

    public function email(string $to, ?string $subject = null, ?string $body = null): Code
    {
        return $this->payload('email', ['to' => $to, 'subject' => $subject, 'body' => $body]);
    }

    public function sms(string $number, ?string $message = null): Code
    {
        return $this->payload('sms', ['number' => $number, 'message' => $message]);
    }

    public function tel(string $number): Code
    {
        return $this->payload('tel', ['number' => $number]);
    }

    public function geo(float $lat, float $lng): Code
    {
        return $this->payload('geo', ['lat' => (string) $lat, 'lng' => (string) $lng]);
    }

    /**
     * Calendar event. Fields: summary*, start* ("2026-01-10 10:00", a date,
     * or RFC 3339), end, location, description.
     *
     * @param array<string, string> $fields
     */
    public function event(array $fields): Code
    {
        return $this->payload('event', $fields);
    }

    /**
     * EMVCo merchant payment (Bangla QR and similar). Fields: merchant_name*,
     * merchant_city*, guid + merchant_id (or accountNN), amount, currency
     * (default 050 = BDT), country (default BD), mcc, bill, reference, ...
     *
     * @param array<string, string> $fields
     */
    public function emv(array $fields): Code
    {
        return $this->payload('emv', $fields);
    }

    /**
     * Many codes in one process. With a type, the matching qr_defaults or
     * barcode_defaults are applied too.
     */
    public function batch(?string $type = null): Batch
    {
        $b = new Batch($this, $type === null ? $this->defaults : $this->defaultsFor($type));
        if ($type !== null) {
            $b->type($type);
        }
        if ($this->workers !== null) {
            $b->workers($this->workers);
        }
        return $b;
    }

    /** A numbered series, e.g. "LIB-{00001..05000}". */
    public function sequence(string $pattern): Batch
    {
        return $this->batch()->sequence($pattern);
    }

    // ------------------------------------------------------------ info

    /** Binary version, e.g. "1.0.0". */
    public function version(): string
    {
        $res = $this->run(['-version']);
        if (!$res->successful() || !preg_match('/EduplusQR\s+(\S+)/', $res->stdout, $m)) {
            throw new GenerationException('Could not read the EduplusQR version: ' . trim($res->stdout . ' ' . $res->stderr), $res->exitCode);
        }
        return $m[1];
    }

    /**
     * Supported code types: name => description.
     *
     * @return array<string, string>
     */
    public function types(): array
    {
        $res = $this->run(['-types']);
        $out = [];
        foreach (preg_split('/\r\n|\n/', $res->stdout) ?: [] as $line) {
            if (preg_match('/^\s+(\S+)\s+(.+)$/', $line, $m)) {
                $out[$m[1]] = trim($m[2]);
            }
        }
        return $out;
    }

    /** Full path of the binary in use (resolved on first use). */
    public function binary(): string
    {
        if ($this->binary === null) {
            $found = self::detectBinary($this->searchDirs);
            if ($found === null) {
                throw new BinaryNotFoundException(sprintf(
                    'EduplusQR binary not found. Set EDUPLUS_QR_BINARY to its full path, or place "%s" (or "%s") in one of: %s',
                    self::platformBinaryName(),
                    self::BINARY . (self::isWindows() ? '.exe' : ''),
                    implode(', ', self::searchPath($this->searchDirs))
                ));
            }
            $this->binary = $found;
        }
        if (!is_file($this->binary)) {
            throw new BinaryNotFoundException("EduplusQR binary not found at {$this->binary}");
        }
        if (!self::isWindows() && !is_executable($this->binary)) {
            throw new BinaryNotFoundException("EduplusQR binary is not executable: {$this->binary} (run: chmod +x {$this->binary})");
        }
        return $this->binary;
    }

    /**
     * Run the binary with raw arguments.
     *
     * @param string[] $args
     */
    public function run(array $args, string $stdin = ''): ProcessResult
    {
        array_unshift($args, $this->binary());
        return (new Runner($this->timeout))->run($args, $stdin);
    }

    /** @return string[] */
    public function baseDirArgs(): array
    {
        return $this->baseDir === null ? [] : ['-base-dir', $this->baseDir];
    }

    /** @return array<string, string|int|float|bool> */
    public function defaults(): array
    {
        return $this->defaults;
    }

    /**
     * Defaults for a code type: defaults plus qr_defaults or barcode_defaults.
     *
     * @return array<string, string|int|float|bool>
     */
    public function defaultsFor(string $type): array
    {
        $isQR = in_array(strtolower($type), ['qr', 'qrcode', 'qr-code'], true);
        return array_merge($this->defaults, $isQR ? $this->qrDefaults : $this->barcodeDefaults);
    }

    private function newCode(string $type): Code
    {
        return (new Code($this, $this->defaultsFor($type)))->type($type);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, string|int|float|bool>
     */
    private static function arrayOf(array $config, string $key): array
    {
        return isset($config[$key]) && is_array($config[$key]) ? $config[$key] : [];
    }

    /** Receive warnings (e.g. "logo uses 70% of the error-correction capacity"). */
    public function onWarning(?callable $handler): self
    {
        $this->warningHandler = $handler;
        return $this;
    }

    /** @return callable|null */
    public function warningHandler()
    {
        return $this->warningHandler;
    }

    /**
     * @internal
     * @param array<string, mixed> $result
     */
    public function reportWarning(array $result): void
    {
        if ($this->warningHandler !== null && !empty($result['warning'])) {
            call_user_func($this->warningHandler, (string) $result['warning'], $result);
        }
    }

    // ------------------------------------------------------------ default instance

    /** Set the client used by QrCode / BarCode static helpers outside Laravel. */
    public static function setDefault(?self $client): void
    {
        self::$default = $client;
    }

    /** The default client: the one set with setDefault(), Laravel's, or auto-detected. */
    public static function getDefault(): self
    {
        if (self::$default !== null) {
            return self::$default;
        }
        if (function_exists('app') && class_exists('Illuminate\Container\Container')) {
            $app = \Illuminate\Container\Container::getInstance();
            if ($app->bound(self::class)) {
                return $app->make(self::class);
            }
        }
        return self::$default = new self();
    }

    // ------------------------------------------------------------ binary detection

    /** "EduplusQR-linux-amd64", "EduplusQR-windows-amd64.exe", ... for this machine. */
    public static function platformBinaryName(): string
    {
        $os = self::isWindows() ? 'windows' : (PHP_OS_FAMILY === 'Darwin' ? 'darwin' : 'linux');
        $m = strtolower(php_uname('m'));
        if (in_array($m, ['x86_64', 'amd64', 'x64'], true)) {
            $arch = 'amd64';
        } elseif (in_array($m, ['aarch64', 'arm64', 'armv8', 'armv8l'], true)) {
            $arch = 'arm64';
        } elseif (strpos($m, 'arm') === 0) {
            $arch = 'armv6';
        } elseif (in_array($m, ['i386', 'i486', 'i586', 'i686', 'x86'], true)) {
            $arch = '386';
        } else {
            $arch = $m;
        }
        return self::BINARY . '-' . $os . '-' . $arch . ($os === 'windows' ? '.exe' : '');
    }

    /**
     * Look for the binary in the given directories, then the default ones
     * and PATH.
     *
     * @param string[] $dirs
     */
    public static function detectBinary(array $dirs = []): ?string
    {
        $names = [self::platformBinaryName()];
        if (PHP_OS_FAMILY === 'Darwin') {
            $names[] = self::BINARY . '-darwin-universal';
        }
        $names[] = self::BINARY . (self::isWindows() ? '.exe' : '');
        foreach (self::searchPath($dirs) as $dir) {
            foreach ($names as $name) {
                $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;
                if (is_file($path) && (self::isWindows() || is_executable($path))) {
                    return $path;
                }
            }
        }
        return null;
    }

    /**
     * @param string[] $dirs
     * @return string[]
     */
    private static function searchPath(array $dirs): array
    {
        $all = $dirs;
        if (function_exists('base_path')) {
            $all[] = base_path('bin');
        }
        $all[] = '/usr/local/bin';
        $all[] = '/usr/bin';
        $all[] = '/opt/eduplus';
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $p) {
            if ($p !== '') {
                $all[] = $p;
            }
        }
        return array_values(array_unique($all));
    }

    private static function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}
