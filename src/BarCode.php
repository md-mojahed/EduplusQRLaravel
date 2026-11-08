<?php

namespace Eduplus\Qr;

/**
 * Barcodes (and the other 2D types: datamatrix, pdf417, aztec).
 *
 *     BarCode::make('STU-2025-0142')->showText()->save($path);      // Code 128
 *     BarCode::make('590123412345', 'ean13')->toDataUri();          // check digit added
 *     BarCode::batch()->addMany($rolls)->saveTo($dir . '/{id}.png');
 *
 * Shares its configuration with QrCode (see QrCode::configure()).
 */
final class BarCode
{
    /**
     * @param string $type code128 (default), gs1-128, code39, code93, codabar,
     *                     itf, itf14, 2of5, ean13, ean8, upca, datamatrix, pdf417, aztec
     */
    public static function make(string $text, string $type = 'code128'): Code
    {
        return QrCode::client()->barcode($text, $type);
    }

    /** Many barcodes in one process (Code 128 unless you pass or set a type). */
    public static function batch(string $type = 'code128'): Batch
    {
        return QrCode::client()->batch($type);
    }

    /** A numbered series, e.g. BarCode::sequence('LIB-{00001..02400}'). */
    public static function sequence(string $pattern, string $type = 'code128'): Batch
    {
        return self::batch($type)->sequence($pattern);
    }

    /**
     * Supported code types: name => description.
     *
     * @return array<string, string>
     */
    public static function types(): array
    {
        return QrCode::types();
    }
}
