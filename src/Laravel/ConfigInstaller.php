<?php

namespace Eduplus\Qr\Laravel;

use Eduplus\Qr\Exceptions\EduplusException;
use Eduplus\Qr\Process\Runner;

/**
 * Adds the "qr" section to the shared config/eduplus.php used by all
 * Eduplus packages: creates the file when it is missing, appends the
 * section when the file exists without it, and leaves every other
 * package's section untouched. The file is parsed with PHP's tokenizer
 * (never executed) and the result is syntax-checked before it is saved.
 */
final class ConfigInstaller
{
    public const CREATED = 'created';
    public const APPENDED = 'appended';
    public const ALREADY_PRESENT = 'already-present';

    /** The "qr" => [...] block, indented for a config array. */
    public static function stub(): string
    {
        $stub = file_get_contents(__DIR__ . '/../../stubs/qr-config.stub');
        if ($stub === false) {
            throw new EduplusException('Missing stubs/qr-config.stub');
        }
        return rtrim($stub) . "\n";
    }

    /**
     * @return string one of the class constants
     * @throws EduplusException when the existing file cannot be updated safely
     */
    public static function install(string $path): string
    {
        if (!file_exists($path)) {
            $content = "<?php\n\n/*\n| Shared configuration for Eduplus packages. Each package keeps its\n"
                . "| settings under its own key (qr, pgw, ...).\n*/\n\nreturn [\n\n" . self::stub() . "\n];\n";
            self::write($path, $content);
            return self::CREATED;
        }

        $source = file_get_contents($path);
        if ($source === false) {
            throw new EduplusException("Cannot read {$path}");
        }
        $array = self::findReturnedArray($source);
        if ($array === null) {
            throw new EduplusException("{$path} does not contain a plain `return [ ... ];` array; add the 'qr' section manually (see stubs/qr-config.stub).");
        }
        if ($array['hasQr']) {
            return self::ALREADY_PRESENT;
        }

        $insert = "\n" . self::stub();
        if (!$array['endsWithComma'] && !$array['empty']) {
            $insert = ',' . $insert;
        }
        $updated = substr($source, 0, $array['insertAt']) . $insert . substr($source, $array['insertAt']);
        self::write($path, $updated);
        return self::APPENDED;
    }

    /**
     * Locate the array returned by the file.
     *
     * @return array{insertAt:int, hasQr:bool, endsWithComma:bool, empty:bool}|null
     */
    public static function findReturnedArray(string $source): ?array
    {
        $tokens = token_get_all($source);
        $offset = 0;
        $state = 'seek-return';
        $depth = 0;
        $closer = '';
        $lastSignificant = '';
        $hasQr = false;
        $pendingKey = false;
        $empty = true;
        $sawArrayKeyword = false;
        foreach ($tokens as $tok) {
            $text = is_array($tok) ? $tok[1] : $tok;
            $id = is_array($tok) ? $tok[0] : null;
            $start = $offset;
            $offset += strlen($text);
            if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if ($state === 'seek-return') {
                if ($id === T_RETURN) {
                    $state = 'seek-open';
                }
                continue;
            }
            if ($state === 'seek-open') {
                if ($text === '[' && !$sawArrayKeyword) {
                    $closer = ']';
                } elseif ($id === T_ARRAY && !$sawArrayKeyword) {
                    $sawArrayKeyword = true; // "return array( ... );"
                    continue;
                } elseif ($text === '(' && $sawArrayKeyword) {
                    $closer = ')';
                } else {
                    return null;
                }
                $state = 'inside';
                $depth = 1;
                $lastSignificant = $text;
                continue;
            }
            // inside the returned array
            if ($text === '[' || $text === '(' || $text === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
            } elseif ($text === ']' || $text === ')' || $text === '}') {
                $depth--;
                if ($depth === 0) {
                    if ($text !== $closer) {
                        return null;
                    }
                    return [
                        'insertAt' => $start,
                        'hasQr' => $hasQr,
                        'endsWithComma' => $lastSignificant === ',',
                        'empty' => $empty,
                    ];
                }
            }
            if ($depth === 1) {
                $empty = false;
                if ($id === T_CONSTANT_ENCAPSED_STRING && strtolower(trim($text, '\'"')) === 'qr') {
                    $pendingKey = true;
                } elseif ($id === T_DOUBLE_ARROW && $pendingKey) {
                    $hasQr = true;
                } else {
                    $pendingKey = false;
                }
            }
            $lastSignificant = $text;
        }
        return null;
    }

    private static function write(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new EduplusException("Cannot create {$dir}");
        }
        $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, $content) === false) {
            throw new EduplusException("Cannot write {$tmp}");
        }
        $lint = (new Runner(30))->run([PHP_BINARY, '-l', $tmp]);
        if (!$lint->successful()) {
            @unlink($tmp);
            throw new EduplusException("The updated {$path} would not be valid PHP; it was left unchanged. Add the 'qr' section manually (see stubs/qr-config.stub).");
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new EduplusException("Cannot write {$path}");
        }
    }
}
