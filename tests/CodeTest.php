<?php

namespace Eduplus\Qr\Tests;

use Eduplus\Qr\BarCode;
use Eduplus\Qr\Exceptions\GenerationException;
use Eduplus\Qr\QrCode;

/**
 * Single codes against the real binary.
 */
class CodeTest extends TestCase
{
    public function testSaveQr(): void
    {
        $path = $this->path('nested/dir/qr.png');
        $r = $this->client()->qr('https://eduplus-bd.com')->verify()->save($path);
        $this->assertFileExists($path);
        $this->assertSame($path, $r->path());
        $this->assertSame('qr', $r->type());
        $this->assertTrue($r->verified());
        $this->assertNotNull($r->qrVersion());
        $this->assertSame(hash_file('sha256', $path), $r->sha256());
        $this->assertSame("\x89PNG", substr((string) file_get_contents($path), 0, 4));
    }

    public function testBarcodeWithCheckDigit(): void
    {
        $r = $this->client()->barcode('590123412345', 'ean13')->save($this->path('ean.svg'));
        $this->assertSame('5901234123457', $r->content());
        $this->assertSame('svg', $r->format());
        $this->assertStringContainsString('<svg', (string) file_get_contents($this->path('ean.svg')));
    }

    public function testInMemoryFormats(): void
    {
        $c = $this->client()->qr('hello');
        $this->assertSame("\x89PNG", substr($c->toPng(), 0, 4));
        $this->assertStringStartsWith('<?xml', $c->toSvg());
        $this->assertStringStartsWith('%PDF-1.4', $c->toPdf());
        $this->assertStringStartsWith('iVBORw0KGgo', $c->toBase64());
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $c->toDataUri('svg'));
        $res = $c->generateInMemory();
        $this->assertSame("\x89PNG", substr((string) $res->contents(), 0, 4));
        $this->assertGreaterThan(0, $res->width());
    }

    public function testUnicodeAndShellCharacters(): void
    {
        $text = 'বাংলা "quoted" $(whoami) `id` ; & | > <';
        $r = $this->client()->qr($text)->verify()->generateInMemory();
        $this->assertSame($text, $r->content());
        $this->assertTrue($r->verified());
    }

    public function testPayloads(): void
    {
        $qr = $this->client();
        $this->assertSame('WIFI:T:WPA;S:Campus;P:a\;b;;', $qr->wifi('Campus', 'a;b')->generateInMemory()->content());
        $this->assertSame('tel:+8801711000000', $qr->tel('+880 1711-000000')->generateInMemory()->content());
        $this->assertStringStartsWith('BEGIN:VCARD', $qr->vcard(['name' => 'Rahim'])->generateInMemory()->content());
        $this->assertStringStartsWith('mailto:a@b.co?subject=Hi', $qr->email('a@b.co', 'Hi')->generateInMemory()->content());
        $emv = $qr->emv(['merchant_name' => 'EDUPLUS', 'merchant_city' => 'DHAKA', 'guid' => 'bd.bank', 'merchant_id' => '1'])->generateInMemory()->content();
        $this->assertStringStartsWith('000201', $emv);
        $this->assertStringContainsString('6304', $emv);
    }

    public function testErrorsCarryExitCodes(): void
    {
        try {
            $this->client()->barcode('5901234123458', 'ean13')->save($this->path('x.png'));
            $this->fail('expected exception');
        } catch (GenerationException $e) {
            $this->assertTrue($e->isInvalidInput());
            $this->assertStringContainsString('invalid check digit: got 8, expected 7', $e->getMessage());
        }
        try {
            $this->client()->qr('x')->toString();
            $this->client()->barcode('12', 'ean13')->toString();
            $this->fail('expected exception');
        } catch (GenerationException $e) {
            $this->assertSame(GenerationException::INVALID_INPUT, $e->getExitCode());
            $this->assertStringContainsString('ean13', $e->getMessage());
        }
        $this->assertFalse($this->client()->barcode('12', 'ean13')->isValid());
        $this->assertTrue($this->client()->barcode('590123412345', 'ean13')->isValid());
    }

    public function testBaseDirProtection(): void
    {
        $qr = $this->client(['base_dir' => $this->path('public')]);
        mkdir($this->path('public'));
        $qr->qr('ok')->save($this->path('public/a.png'));
        $this->expectException(GenerationException::class);
        $qr->qr('x')->save($this->path('evil.png'));
    }

    public function testLogoAndStyle(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD not available');
        }
        $logo = $this->path('logo.png');
        $img = imagecreatetruecolor(60, 60);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 200, 30, 30));
        imagepng($img, $logo);
        $warnings = [];
        $qr = $this->client()->onWarning(function (string $w) use (&$warnings) {
            $warnings[] = $w;
        });
        $r = $qr->qr('https://eduplus-bd.com/verify?id=42')->logo($logo)->style('rounded')->save($this->path('logo.png.out.png'));
        $this->assertTrue($r->verified(), 'logo codes are always verified');
        $this->assertSame('H', $r->ecc());
        $this->assertIsArray($warnings);
    }

    public function testStaticApi(): void
    {
        QrCode::configure(['binary' => $this->client()->binary(), 'barcode_defaults' => ['text' => true]]);
        $this->assertSame('qr', QrCode::make('a')->generateInMemory()->type());
        $this->assertSame('code128', BarCode::make('A-1')->generateInMemory()->type());
        $this->assertSame('ean8', BarCode::make('9638507', 'ean8')->generateInMemory()->type());
        $this->assertStringStartsWith('WIFI:', QrCode::wifi('x')->generateInMemory()->content());
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', QrCode::version());
        $this->assertArrayHasKey('datamatrix', BarCode::types());
        $this->assertContains('-text=true', BarCode::make('X')->toArguments(), 'barcode_defaults apply to BarCode');

        $series = BarCode::sequence('LIB-{01..03}')->validate();
        $this->assertSame(['LIB-01', 'LIB-02', 'LIB-03'], array_keys($series->all()));
        $mixed = QrCode::batch()->add('q', 'https://x.y')->add('b', 'B-1', ['type' => 'code128'])->toBase64();
        $this->assertSame('qr', $mixed['q']->result()->type());
        $this->assertSame('code128', $mixed['b']->result()->type());
    }

    public function testVersionAndTypes(): void
    {
        $qr = $this->client();
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', $qr->version());
        $types = $qr->types();
        $this->assertArrayHasKey('qr', $types);
        $this->assertArrayHasKey('ean13', $types);
    }
}
