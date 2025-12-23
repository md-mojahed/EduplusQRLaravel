<?php

namespace Eduplus;

class QrCode extends Base
{
    protected $binaryPath = null;
    protected $text = null;
    protected $output = null;
    protected $size = 256;
    protected $errorCorrection = 'M';
    protected $margin = 0;
    public $errors = [];

    public function __construct()
    {
        $this->binaryPath = config('eduplusqr.qr_binary_path');
        
        if (!$this->binaryPath || !file_exists($this->binaryPath)) {
            $this->errors[] = "QR binary not found. Please configure 'qr_binary_path' in config/eduplusqr.php";
        }
    }

    public static function create()
    {
        return new QrCode();
    }

    public function text($text)
    {
        $this->text = $text;
        return $this;
    }

    public function output($output)
    {
        $this->output = $output;
        return $this;
    }

    public function size($size)
    {
        $this->size = (int)$size;
        return $this;
    }

    public function errorCorrection($level)
    {
        if (!in_array($level, ['L', 'M', 'Q', 'H'])) {
            $this->errors[] = "Invalid error correction level: {$level}. Use L, M, Q, or H.";
            return $this;
        }
        $this->errorCorrection = $level;
        return $this;
    }

    public function margin($margin)
    {
        $this->margin = (int)$margin;
        return $this;
    }

    public function generate()
    {
        if (empty($this->text)) {
            $this->errors[] = "Text is required";
            return false;
        }

        if (empty($this->output)) {
            $this->errors[] = "Output path is required";
            return false;
        }

        if (!$this->binaryPath || !file_exists($this->binaryPath)) {
            $this->errors[] = "Binary not available";
            return false;
        }

        // Create output directory if it doesn't exist
        $outputDir = dirname($this->output);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $escapedText = escapeshellarg($this->text);
        $escapedOutput = escapeshellarg($this->output);
        
        $command = sprintf(
            '%s -t %s -o %s -size %d -ec %s -margin %d 2>&1',
            escapeshellarg($this->binaryPath),
            $escapedText,
            $escapedOutput,
            $this->size,
            $this->errorCorrection,
            $this->margin
        );

        $this->terminal($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->errors[] = implode("\n", $output);
            return false;
        }

        if (!file_exists($this->output)) {
            $this->errors[] = "QR code generation failed";
            return false;
        }

        return true;
    }

    public function generateAndReturn()
    {
        if ($this->generate()) {
            return file_get_contents($this->output);
        }
        return null;
    }

    public function generateBase64()
    {
        if ($this->generate()) {
            return base64_encode(file_get_contents($this->output));
        }
        return null;
    }

    public static function quick($text, $output, $size = 256)
    {
        return self::create()
            ->text($text)
            ->output($output)
            ->size($size)
            ->generate();
    }
}
