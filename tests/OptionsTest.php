<?php

namespace Eduplus\Qr\Tests;

use Eduplus\Qr\BatchItem;
use Eduplus\Qr\BatchResult;
use Eduplus\Qr\Code;
use Eduplus\Qr\Client;
use Eduplus\Qr\Exceptions\BinaryNotFoundException;
use Eduplus\Qr\Exceptions\EduplusException;
use Eduplus\Qr\Exceptions\GenerationException;
use Eduplus\Qr\Result;

/**
 * Tests that need no binary.
 */
class OptionsTest extends TestCase
{
    private function code(): Code
    {
        return new Code(new Client('/nonexistent'));
    }

    public function testArgumentsAreBuiltSafely(): void
    {
        $args = $this->code()->text('a "b" $(rm -rf /); c')
            ->type('code128')->module(3)->showText()->hideText()->label('Roll 42')
            ->logoSize(0.22)->xdim(0.33)->color('#000', 'transparent')
            ->toArguments();

        $this->assertSame([
            '-t', 'a "b" $(rm -rf /); c',
            '-type', 'code128', '-module', '3', '-text=false', '-label', 'Roll 42',
            '-logo-size', '0.22', '-xdim', '0.33', '-fg', '#000', '-bg', 'transparent',
        ], $args);
    }

    public function testBareAndSize(): void
    {
        $args = $this->code()->text('x')->size(300)->bare()->toArguments();
        $this->assertSame(['-t', 'x', '-w', '300', '-height', '300', '-quiet', '0', '-margin', '0', '-bg', 'transparent', '-text=false'], $args);
    }

    public function testPayloadArguments(): void
    {
        $args = $this->code()->payload('wifi', ['ssid' => 'Lab', 'password' => 'a;b', 'hidden' => true, 'auth' => null])->toArguments();
        $this->assertSame(['-payload', 'wifi', '-p', 'ssid=Lab', '-p', 'password=a;b', '-p', 'hidden=true'], $args);
        $row = $this->code()->payload('tel', ['number' => '+880'])->toRow();
        $this->assertSame('tel', $row['payload']);
        $this->assertEquals((object) ['number' => '+880'], $row['p']);
    }

    public function testOptionValidation(): void
    {
        $this->expectException(EduplusException::class);
        $this->code()->option('o', '/etc/passwd');
    }

    public function testInvalidOptionName(): void
    {
        $this->expectException(EduplusException::class);
        $this->code()->option('bad name', 1);
    }

    public function testNullRemovesOption(): void
    {
        $c = $this->code()->text('x')->module(3)->option('module', null);
        $this->assertSame(['-t', 'x'], $c->toArguments());
    }

    public function testMissingText(): void
    {
        $this->expectException(GenerationException::class);
        $this->code()->toArguments();
    }

    public function testDefaultsPerType(): void
    {
        $client = new Client('/nonexistent', [
            'defaults' => ['fg' => '#111111'],
            'qr_defaults' => ['style' => 'rounded'],
            'barcode_defaults' => ['text' => true],
        ]);
        $this->assertSame(['-t', 'x', '-fg', '#111111', '-style', 'rounded', '-type', 'qr'], $client->qr('x')->toArguments());
        $this->assertSame(['-t', 'x', '-fg', '#111111', '-text=true', '-type', 'ean13'], $client->barcode('x', 'ean13')->toArguments());
    }

    public function testBinaryNotFound(): void
    {
        $this->expectException(BinaryNotFoundException::class);
        (new Client($this->path('missing')))->binary();
    }

    public function testPlatformBinaryName(): void
    {
        $this->assertMatchesRegularExpression('/^EduplusQR-(linux|darwin|windows)-[a-z0-9]+(\.exe)?$/', Client::platformBinaryName());
    }

    public function testDetectBinaryInDirectory(): void
    {
        $name = Client::platformBinaryName();
        $file = $this->path($name);
        file_put_contents($file, "#!/bin/sh\n");
        chmod($file, 0755);
        $this->assertSame($file, Client::detectBinary([$this->dir]));
    }

    public function testResultAccessors(): void
    {
        $r = new Result(['id' => 'a', 'output' => '/x.png', 'type' => 'qr', 'content' => 'hi', 'format' => 'svg',
            'width' => 10, 'height' => 12, 'module' => 2, 'qr' => ['version' => 3, 'ecc' => 'Q', 'mask' => 5],
            'bytes' => 99, 'sha256' => 'abc', 'verified' => true, 'base64' => base64_encode('<svg/>')]);
        $this->assertSame('a', $r->id());
        $this->assertSame('/x.png', $r->path());
        $this->assertSame(3, $r->qrVersion());
        $this->assertSame('Q', $r->ecc());
        $this->assertTrue($r->verified());
        $this->assertSame('<svg/>', $r->contents());
        $this->assertSame('data:image/svg+xml;base64,' . base64_encode('<svg/>'), $r->dataUri());
        $this->assertNull($r->warning());
    }

    public function testBatchResultCollection(): void
    {
        $items = [
            'b' => new BatchItem('b', 1, BatchItem::OK, new Result(['id' => 'b'])),
            'a' => new BatchItem('a', 2, BatchItem::ERROR, null, 'bad'),
        ];
        $res = new BatchResult($items, [], ['total' => 2, 'ok' => 1, 'failed' => 1, 'skipped' => 0]);
        $this->assertCount(2, $res);
        $this->assertSame(['b', 'a'], array_keys(iterator_to_array($res)));
        $this->assertTrue($res['b']->ok());
        $this->assertSame(['a' => 'bad'], $res->errors());
        $this->assertFalse($res->allSuccessful());
        $this->assertNull($res['zzz']);
        $this->expectException(GenerationException::class);
        $res->throw();
    }
}
