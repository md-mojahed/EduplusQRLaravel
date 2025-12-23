<?php

namespace Eduplus;

class BarCode extends Base
{
    protected $binaryPath = null;
    protected $text = null;
    protected $output = null;
    protected $type = 'code128';
    protected $width = 300;
    protected $height = 100;
    public $errors = [];

    public function __construct()
    {
        $this->binaryPath = config('eduplusqr.barcode_binary_path');
        
        if (!$this->binaryPath || !file_exists($this->binaryPath)) {
            $this->errors[] = "Barcode binary not found. Please configure 'barcode_binary_path' in config/eduplusqr.php";
        }
    }

    public static function create()
    {
        return new BarCode();
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

    public function type($type)
    {
        $validTypes = ['code128', 'code39', 'ean13'];
        if (!in_array(strtolower($type), $validTypes)) {
            $this->errors[] = "Invalid barcode type: {$type}. Use code128, code39, or ean13.";
            return $this;
        }
        $this->type = strtolower($type);
        return $this;
    }

    public function width($width)
    {
        $this->width = (int)$width;
        return $this;
    }

    public function height($height)
    {
        $this->height = (int)$height;
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
            '%s -t %s -o %s -type %s -w %d -height %d 2>&1',
            escapeshellarg($this->binaryPath),
            $escapedText,
            $escapedOutput,
            $this->type,
            $this->width,
            $this->height
        );

        $this->terminal($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->errors[] = implode("\n", $output);
            return false;
        }

        if (!file_exists($this->output)) {
            $this->errors[] = "Barcode generation failed";
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

    public static function quick($text, $output, $type = 'code128', $width = 300, $height = 100)
    {
        return self::create()
            ->text($text)
            ->output($output)
            ->type($type)
            ->width($width)
            ->height($height)
            ->generate();
    }
}
