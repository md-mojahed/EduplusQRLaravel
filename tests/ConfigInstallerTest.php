<?php

namespace Eduplus\Qr\Tests;

use Eduplus\Qr\Exceptions\EduplusException;
use Eduplus\Qr\Laravel\ConfigInstaller;

/**
 * The installer must create or extend config/eduplus.php without touching
 * other packages' settings.
 */
class ConfigInstallerTest extends TestCase
{
    /** @return array<string, mixed> */
    private function load(string $path): array
    {
        if (!function_exists('env')) {
            eval('function env($key, $default = null) { return $default; }');
        }
        return require $path;
    }

    public function testCreatesMissingFile(): void
    {
        $path = $this->path('config/eduplus.php');
        $this->assertSame(ConfigInstaller::CREATED, ConfigInstaller::install($path));
        $config = $this->load($path);
        $this->assertSame(120, $config['qr']['timeout']);
        $this->assertSame(ConfigInstaller::ALREADY_PRESENT, ConfigInstaller::install($path));
    }

    /** @return array<string, array{0: string}> */
    public function existingFiles(): array
    {
        return [
            'short array, trailing comma' => ["<?php\n\nreturn [\n    'pgw' => [\n        'key' => 'abc',\n    ],\n];\n"],
            'no trailing comma' => ["<?php\nreturn [\n    'pgw' => ['key' => 'abc', 'list' => [1, 2]]\n];"],
            'long array syntax' => ["<?php\n\nreturn array(\n    'pgw' => array('key' => 'abc'),\n);\n"],
            'comments and closing comment' => ["<?php\n// shared\nreturn [\n    // payment gateway\n    'pgw' => ['key' => 'abc'], // inline\n    /* end */\n];\n"],
            'empty array' => ["<?php\nreturn [];\n"],
            'nested qr key is not top level' => ["<?php\nreturn ['pgw' => ['qr' => true]];\n"],
            'braces in strings' => ["<?php\nreturn ['pgw' => ['tpl' => '{x} ] ) [', 'k' => \"a\\\"]\"]];\n"],
        ];
    }

    /** @dataProvider existingFiles */
    public function testAppendsToExistingFile(string $source): void
    {
        $path = $this->path('eduplus.php');
        file_put_contents($path, $source);
        $before = $this->load($path);

        $this->assertSame(ConfigInstaller::APPENDED, ConfigInstaller::install($path));
        $after = $this->load($path);

        $this->assertArrayHasKey('qr', $after);
        $this->assertSame('', (string) $after['qr']['binary']);
        unset($after['qr']);
        $this->assertSame($before, $after, 'other settings must be unchanged');
        $this->assertSame(ConfigInstaller::ALREADY_PRESENT, ConfigInstaller::install($path));
    }

    public function testLeavesExistingQrSectionAlone(): void
    {
        $path = $this->path('eduplus.php');
        $source = "<?php\nreturn [\n    'qr' => ['timeout' => 5],\n    'pgw' => [],\n];\n";
        file_put_contents($path, $source);
        $this->assertSame(ConfigInstaller::ALREADY_PRESENT, ConfigInstaller::install($path));
        $this->assertSame($source, file_get_contents($path));
    }

    public function testRefusesUnusualFiles(): void
    {
        $path = $this->path('eduplus.php');
        $source = "<?php\n\$c = ['pgw' => []];\nreturn \$c;\n";
        file_put_contents($path, $source);
        try {
            ConfigInstaller::install($path);
            $this->fail('expected exception');
        } catch (EduplusException $e) {
            $this->assertStringContainsString('manually', $e->getMessage());
        }
        $this->assertSame($source, file_get_contents($path), 'file must be left unchanged');
    }

    public function testPackageConfigMatchesStub(): void
    {
        $this->assertStringContainsString(rtrim(ConfigInstaller::stub()), (string) file_get_contents(__DIR__ . '/../config/eduplus.php'));
    }
}
