<?php

namespace Eduplus\Qr\Tests;

use Eduplus\Qr\Client;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eduplusqr-test-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->dir);
        Client::setDefault(null);
        parent::tearDown();
    }

    /** A client for the real binary, or skip when none is available. */
    protected function client(array $config = []): Client
    {
        $bin = (string) getenv('EDUPLUS_QR_BINARY');
        if ($bin === '') {
            $bin = (string) Client::detectBinary();
        }
        if ($bin === '' || !is_file($bin)) {
            $this->markTestSkipped('Set EDUPLUS_QR_BINARY to a built EduplusQR binary to run integration tests.');
        }
        return new Client($bin, $config);
    }

    protected function path(string $name): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . $name;
    }

    private function rmrf(string $path): void
    {
        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $f) {
                if ($f !== '.' && $f !== '..') {
                    $this->rmrf($path . DIRECTORY_SEPARATOR . $f);
                }
            }
            @rmdir($path);
        } elseif (file_exists($path)) {
            @unlink($path);
        }
    }
}
