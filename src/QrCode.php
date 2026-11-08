<?php

namespace Eduplus\Qr;

/**
 * QR codes, the main entry point of the package.
 *
 *     QrCode::make('https://eduplus-bd.com')->style('rounded')->save($path);
 *     QrCode::wifi('Campus', 'secret')->toDataUri();
 *     QrCode::batch()->add('stu-1', $url1)->add('stu-2', $url2)->toBase64();
 *
 * In Laravel the settings come from config('eduplus.qr'). Elsewhere, call
 * QrCode::configure([...]) once (or rely on auto-detection of the binary).
 * The configuration is shared with BarCode.
 */
final class QrCode
{
    /** A QR code for any text, URL or Unicode string. */
    public static function make(string $text): Code
    {
        return self::client()->qr($text);
    }

    /**
     * A QR code built from a standard content format.
     *
     * @param string $kind url, wifi, vcard, mecard, email, sms, tel, geo, event or emv
     * @param array<string, string|int|float|bool> $fields
     */
    public static function payload(string $kind, array $fields): Code
    {
        return self::client()->payload($kind, $fields);
    }

    /** Join a Wi-Fi network. $auth: wpa, wpa3, wep or nopass (default: wpa if a password is set). */
    public static function wifi(string $ssid, ?string $password = null, ?string $auth = null, bool $hidden = false): Code
    {
        return self::client()->wifi($ssid, $password, $auth, $hidden);
    }

    /**
     * Contact card. Fields: name, first, last, org, title, phone, mobile,
     * work_phone, email, url, address, note, birthday (YYYY-MM-DD).
     *
     * @param array<string, string> $fields
     */
    public static function vcard(array $fields): Code
    {
        return self::client()->vcard($fields);
    }

    public static function email(string $to, ?string $subject = null, ?string $body = null): Code
    {
        return self::client()->email($to, $subject, $body);
    }

    public static function sms(string $number, ?string $message = null): Code
    {
        return self::client()->sms($number, $message);
    }

    public static function tel(string $number): Code
    {
        return self::client()->tel($number);
    }

    public static function geo(float $lat, float $lng): Code
    {
        return self::client()->geo($lat, $lng);
    }

    /**
     * Calendar event. Fields: summary*, start* ("2026-01-10 10:00", a date or
     * RFC 3339), end, location, description.
     *
     * @param array<string, string> $fields
     */
    public static function event(array $fields): Code
    {
        return self::client()->event($fields);
    }

    /**
     * EMVCo merchant payment (Bangla QR and similar). Fields: merchant_name*,
     * merchant_city*, guid + merchant_id (or accountNN), amount, currency
     * (default 050 = BDT), country (default BD), mcc, bill, reference, ...
     *
     * @param array<string, string> $fields
     */
    public static function emv(array $fields): Code
    {
        return self::client()->emv($fields);
    }

    /**
     * Many QR codes in one process. Rows may override the type, so one batch
     * can also contain barcodes.
     */
    public static function batch(): Batch
    {
        return self::client()->batch('qr');
    }

    /** A numbered series of QR codes, e.g. "TICKET-{00001..05000}". */
    public static function sequence(string $pattern): Batch
    {
        return self::batch()->sequence($pattern);
    }

    // ------------------------------------------------------------ setup

    /**
     * Configure the binary and defaults for QrCode and BarCode (outside
     * Laravel, or to override the Laravel config at runtime).
     *
     * @param array<string, mixed> $config binary, binary_dir, timeout, base_dir, workers,
     *                                     defaults, qr_defaults, barcode_defaults
     */
    public static function configure(array $config): void
    {
        Client::setDefault(Client::fromConfig($config));
    }

    /** The shared, configured client. */
    public static function client(): Client
    {
        return Client::getDefault();
    }

    /** Binary version, e.g. "1.0.0". */
    public static function version(): string
    {
        return self::client()->version();
    }

    /**
     * Supported code types: name => description.
     *
     * @return array<string, string>
     */
    public static function types(): array
    {
        return self::client()->types();
    }
}
