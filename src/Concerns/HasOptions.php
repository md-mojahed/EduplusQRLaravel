<?php

namespace Eduplus\Qr\Concerns;

use Eduplus\Qr\Exceptions\EduplusException;

/**
 * Fluent setters for every EduplusQR option. Used by Code (one code) and
 * Batch (defaults for every row). Option names are the binary's flag names.
 */
trait HasOptions
{
    /** @var array<string, string|int|float|bool> */
    protected $options = [];

    /** Code type: qr (default), code128, ean13, datamatrix, ... (see QrCode::types()). */
    public function type(string $type): self
    {
        return $this->option('type', $type);
    }

    /**
     * Error correction. QR: L, M, Q or H (a minimum; raised automatically
     * when it fits). PDF417: 0-8. Aztec: percentage 5-95.
     *
     * @param string|int $level
     */
    public function ecc($level): self
    {
        return $this->option('ecc', (string) $level);
    }

    /** Pixels per module (the narrowest bar / smallest square). */
    public function module(int $pixels): self
    {
        return $this->option('module', $pixels);
    }

    /** Module size in millimetres for print (uses dpi(), default 300). */
    public function xdim(float $millimetres): self
    {
        return $this->option('xdim', $millimetres);
    }

    /** Exact image size in pixels; the module size is fitted to it. */
    public function size(int $width, ?int $height = null): self
    {
        $this->option('w', $width);
        return $this->option('height', $height === null ? $width : $height);
    }

    public function width(int $pixels): self
    {
        return $this->option('w', $pixels);
    }

    public function height(int $pixels): self
    {
        return $this->option('height', $pixels);
    }

    /** Quiet zone (blank border) in modules. 0 removes it. */
    public function quiet(int $modules): self
    {
        return $this->option('quiet', $modules);
    }

    /** Extra padding in pixels on every side. */
    public function margin(int $pixels): self
    {
        return $this->option('margin', $pixels);
    }

    /** Print resolution: stored in PNG, used for SVG/PDF physical size. */
    public function dpi(int $dpi): self
    {
        return $this->option('dpi', $dpi);
    }

    /** Show (or hide) the human-readable text under the code. */
    public function showText(bool $show = true): self
    {
        return $this->option('text', $show);
    }

    public function hideText(): self
    {
        return $this->showText(false);
    }

    /** Custom caption under the code, e.g. "Scan to verify". */
    public function label(string $label): self
    {
        return $this->option('label', $label);
    }

    public function fontSize(float $pixels): self
    {
        return $this->option('font-size', $pixels);
    }

    /** Colours: #RRGGBB, #RRGGBBAA, a name, or "transparent" (background). */
    public function color(string $foreground, ?string $background = null): self
    {
        $this->option('fg', $foreground);
        if ($background !== null) {
            $this->option('bg', $background);
        }
        return $this;
    }

    public function foreground(string $color): self
    {
        return $this->option('fg', $color);
    }

    public function background(string $color): self
    {
        return $this->option('bg', $color);
    }

    public function transparent(): self
    {
        return $this->option('bg', 'transparent');
    }

    /** No quiet zone, no padding, transparent background, no text. */
    public function bare(): self
    {
        return $this->quiet(0)->margin(0)->transparent()->hideText();
    }

    /** 0, 90, 180 or 270 degrees clockwise. */
    public function rotate(int $degrees): self
    {
        return $this->option('rotate', $degrees);
    }

    /** png, svg or pdf. */
    public function format(string $format): self
    {
        return $this->option('format', strtolower($format));
    }

    /** Force QR version 1-40 (same size and density for every code). */
    public function qrVersion(int $version): self
    {
        return $this->option('qr-version', $version);
    }

    public function qrMinVersion(int $version): self
    {
        return $this->option('qr-min-version', $version);
    }

    /** Force QR mask pattern 0-7. */
    public function mask(int $mask): self
    {
        return $this->option('qr-mask', (string) $mask);
    }

    /** Mark non-ASCII text as UTF-8 for strict industrial scanners. */
    public function eci(bool $enable = true): self
    {
        return $this->option('eci', $enable);
    }

    /** Keep exactly the requested ECC level instead of raising it. */
    public function exactEcc(bool $enable = true): self
    {
        return $this->option('no-ecc-boost', $enable);
    }

    /** QR module style: square, dots or rounded. */
    public function style(string $style): self
    {
        return $this->option('style', strtolower($style));
    }

    /**
     * Logo in the centre of a QR code (PNG/JPEG/GIF). ECC defaults to H and
     * the result is always verified by the binary.
     */
    public function logo(string $path, ?float $size = null): self
    {
        $this->option('logo', $path);
        if ($size !== null) {
            $this->option('logo-size', $size);
        }
        return $this;
    }

    /** Logo width as a fraction of the QR width (0.05 - 0.32, default 0.22). */
    public function logoSize(float $fraction): self
    {
        return $this->option('logo-size', $fraction);
    }

    /** Add the optional check digit (code39, itf, 2of5). */
    public function checksum(bool $enable = true): self
    {
        return $this->option('checksum', $enable);
    }

    /** Code 39 full-ASCII mode. */
    public function fullAscii(bool $enable = true): self
    {
        return $this->option('full-ascii', $enable);
    }

    /** Decode the generated image and fail if it does not read back. */
    public function verify(bool $enable = true): self
    {
        return $this->option('verify', $enable);
    }

    /** Allow low-contrast or inverted colours. */
    public function force(bool $enable = true): self
    {
        return $this->option('force', $enable);
    }

    /**
     * Set any binary option by its flag name (without the dash).
     *
     * @param string|int|float|bool|null $value null removes the option
     */
    public function option(string $name, $value): self
    {
        $name = ltrim($name, '-');
        if ($name === '' || preg_match('/^[a-z0-9][a-z0-9-]*$/', $name) !== 1) {
            throw new EduplusException("Invalid option name '{$name}'.");
        }
        if (in_array($name, self::reservedOptions(), true)) {
            throw new EduplusException("Option '{$name}' is managed by the package; use the dedicated method instead.");
        }
        if ($value === null) {
            unset($this->options[$name]);
        } elseif (is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            $this->options[$name] = $value;
        } else {
            throw new EduplusException("Option '{$name}' must be a string, number or boolean.");
        }
        return $this;
    }

    /**
     * Set several options at once, e.g. ['type' => 'code128', 'text' => true].
     *
     * @param array<string, string|int|float|bool|null> $options
     */
    public function options(array $options): self
    {
        foreach ($options as $name => $value) {
            $this->option((string) $name, $value);
        }
        return $this;
    }

    /** @return array<string, string|int|float|bool> */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Options the package sets itself (input, output and process control).
     *
     * @return string[]
     */
    protected static function reservedOptions(): array
    {
        return ['t', 'o', 'payload', 'p', 'id', 'json', 'base64', 'batch', 'batch-format', 'seq', 'serve',
            'zip', 'sheet', 'grid', 'sheet-margin', 'gap', 'manifest', 'dry-run', 'workers', 'base-dir',
            'skip-existing', 'progress', 'h', 'help', 'version', 'v', 'types', 'payloads'];
    }

    /**
     * Convert options to command-line arguments. Booleans are passed as
     * -name=true / -name=false so they can never swallow the next argument.
     *
     * @param array<string, string|int|float|bool> $options
     * @return string[]
     */
    protected static function optionsToArgs(array $options): array
    {
        $args = [];
        foreach ($options as $name => $value) {
            if (is_bool($value)) {
                $args[] = '-' . $name . '=' . ($value ? 'true' : 'false');
            } else {
                $args[] = '-' . $name;
                $args[] = is_float($value) ? rtrim(rtrim(sprintf('%.6F', $value), '0'), '.') : (string) $value;
            }
        }
        return $args;
    }
}
