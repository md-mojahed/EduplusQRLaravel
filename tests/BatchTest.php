<?php

namespace Eduplus\Qr\Tests;

use Eduplus\Qr\Exceptions\EduplusException;

/**
 * Batches against the real binary.
 */
class BatchTest extends TestCase
{
    public function testInMemoryKeepsOrderAndIds(): void
    {
        $results = $this->client()->batch('code128')->showText()
            ->add('stu-3', 'STU-0003')
            ->add('stu-1', 'STU-0001', ['label' => 'Rahim'])
            ->add('bad', '12', ['type' => 'ean13'])
            ->add(42, 'https://eduplus-bd.com', ['type' => 'qr'])
            ->toBase64();

        $this->assertSame(['stu-3', 'stu-1', 'bad', '42'], array_map('strval', array_keys($results->all())));
        $this->assertSame('42', $results[42]->id());
        $this->assertTrue($results['stu-1']->ok());
        $this->assertStringStartsWith('data:image/png;base64,', (string) $results['stu-1']->dataUri());
        $this->assertSame("\x89PNG", substr((string) $results['stu-3']->contents(), 0, 4));
        $this->assertTrue($results['bad']->failed());
        $this->assertStringContainsString('ean13', (string) $results['bad']->error());
        $this->assertSame('qr', $results['42']->result()->type());
        $this->assertSame(['bad'], array_keys($results->failed()));
        $this->assertSame(4, $results->summary()['total']);
        $this->assertFalse($results->allSuccessful());
    }

    public function testSaveToWithIdTemplate(): void
    {
        $results = $this->client()->batch()
            ->addMany(['a/1' => 'ONE', 'b 2' => 'TWO'])
            ->saveTo($this->path('out/{id}.png'));
        $this->assertTrue($results->allSuccessful());
        $this->assertSame($this->path('out/a_1.png'), $results['a/1']->path());
        $this->assertFileExists($this->path('out/b_2.png'));

        // Resume: existing files are skipped.
        $again = $this->client()->batch()->add('a/1', 'ONE')->skipExisting()->saveTo($this->path('out/{id}.png'));
        $this->assertTrue($again['a/1']->skipped());
    }

    public function testZipSheetValidateSequence(): void
    {
        $qr = $this->client();
        $zip = $qr->batch()->add('x', 'X')->add('y', 'Y')->toZip($this->path('codes.zip'), 'qr/{id}.png');
        $this->assertSame($this->path('codes.zip'), $zip->output());
        $z = new \ZipArchive();
        $this->assertTrue($z->open($this->path('codes.zip')) === true);
        $this->assertNotFalse($z->locateName('qr/x.png'));
        $z->close();

        $sheet = $qr->sequence('LIB-{001..030}')->type('code128')->showText()->toSheet($this->path('labels.pdf'), 'a4', '3x8');
        $this->assertSame(2, $sheet->pages());
        $this->assertCount(30, $sheet);
        $this->assertSame('LIB-001', array_key_first($sheet->all()));
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($this->path('labels.pdf')));

        $check = $qr->batch('ean13')->add('ok', '590123412345')->add('bad', '123')->validate();
        $this->assertTrue($check['ok']->ok());
        $this->assertTrue($check['bad']->failed());
        $this->assertFileDoesNotExist($this->path('ok.png'));
    }

    public function testPayloadRowsAndManifest(): void
    {
        $manifest = $this->path('m.csv');
        $res = $this->client()->batch()
            ->addPayload('lab', 'wifi', ['ssid' => 'Lab-1', 'password' => 'pw'])
            ->addCode('call', $this->client()->tel('+8801711000000')->style('dots'))
            ->manifest($manifest)
            ->toBase64();
        $this->assertSame('WIFI:T:WPA;S:Lab-1;P:pw;;', $res['lab']->result()->content());
        $this->assertSame('tel:+8801711000000', $res['call']->result()->content());
        $this->assertCount(3, file($manifest) ?: []);
    }

    public function testLargeBatchDoesNotDeadlock(): void
    {
        $batch = $this->client(['timeout' => 120])->batch('code128');
        for ($i = 1; $i <= 3000; $i++) {
            $batch->add($i, sprintf('STU-%05d', $i));
        }
        $res = $batch->toBase64();
        $this->assertCount(3000, $res);
        $this->assertTrue($res->allSuccessful());
        $this->assertSame('STU-02999', $res['2999']->result()->content());
    }

    public function testDuplicateIdsRejected(): void
    {
        $this->expectException(EduplusException::class);
        $this->client()->batch()->add('a', 'x')->add('a', 'y');
    }

    public function testEmptyBatch(): void
    {
        $res = $this->client()->batch()->toBase64();
        $this->assertCount(0, $res);
        $this->assertTrue($res->allSuccessful());
    }
}
