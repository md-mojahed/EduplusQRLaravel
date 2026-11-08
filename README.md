# Eduplus QR for PHP & Laravel

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

Fast QR code and barcode generation for **PHP 7.4+** and **Laravel 8 – 13**, powered by the standalone [EduplusQR](https://md-mojahed.github.io) binary. You get PNG, SVG or PDF; logos and module styles; Wi-Fi, vCard and payment content; and batches with images in memory, files, a ZIP or printable label sheets.

```php
use Eduplus\Qr\QrCode;
use Eduplus\Qr\BarCode;

QrCode::make('https://eduplus-bd.com/verify/8F2K')->logo($logo)->style('rounded')->save($path);
BarCode::make('STU-2025-0142')->showText()->toDataUri();       // <img src="...">
QrCode::wifi('Campus', 'secret')->label('Scan to join')->toResponse();
```

## Contents

- [Installation](#installation)
- [Single codes](#single-codes)
- [Getting the output](#getting-the-output)
- [Options](#options)
- [Content formats](#content-formats)
- [Batches (bulk generation)](#batches-bulk-generation)
- [Errors](#errors)
- [Configuration](#configuration)
- [Without Laravel](#without-laravel)
- [Upgrading from 1.0.1](#upgrading-from-101)

## Installation

```bash
composer require eduplus/qr
```

**1. Install the binary.** Download the EduplusQR binary for your server, put it in a folder such as `/usr/local/bin` and make it executable:

```bash
sudo cp EduplusQR-linux-amd64 /usr/local/bin/
sudo chmod +x /usr/local/bin/EduplusQR-linux-amd64
```

The package finds the binary named for your machine (`EduplusQR-linux-amd64`, `EduplusQR-linux-arm64`, `EduplusQR-darwin-arm64`, `EduplusQR-windows-amd64.exe`, …) or a plain `EduplusQR`. It looks in `bin/` of your project, `/usr/local/bin`, `/usr/bin`, `/opt/eduplus` and your `PATH`. You can also set the path explicitly:

```env
EDUPLUS_QR_BINARY=/usr/local/bin/EduplusQR-linux-amd64
```

**2. Add the settings** to `config/eduplus.php`, the config file shared by all Eduplus packages:

```bash
php artisan eduplus:qr-install
```

This creates `config/eduplus.php` if it doesn't exist. If it already exists (for example with `eduplus/pgw` settings), it appends a `'qr' => [...]` section and leaves everything else exactly as it was. Running it again changes nothing. The step is optional: without a `qr` section the defaults are used.

**3. Check it:**

```bash
php artisan eduplus:qr-check
```

```
Binary:  /usr/local/bin/EduplusQR-linux-amd64
Version: 1.0.0
Test QR: 132x132 px, version 2, ECC M, verified
EduplusQR is working.
```

The service provider (`Eduplus\Qr\EduplusServiceProvider`) is registered automatically. Everything lives in the `Eduplus\Qr` namespace, so it can't clash with other Eduplus packages. `proc_open()` must be enabled in PHP; it is enabled by default.

## Single codes

Everything starts from two classes:

```php
use Eduplus\Qr\QrCode;
use Eduplus\Qr\BarCode;

QrCode::make('https://eduplus-bd.com');        // QR code
BarCode::make('STU-2025-0142');                // Code 128 (default)
BarCode::make('590123412345', 'ean13');        // check digit added automatically
BarCode::make('STU-2025-0142', 'datamatrix');  // the other 2D types too
```

Supported types (`QrCode::types()`): `qr`, `code128`, `gs1-128`, `code39`, `code93`, `codabar`, `itf`, `itf14`, `2of5`, `ean13`, `ean8`, `upca`, `datamatrix`, `pdf417`, `aztec`.

## Getting the output

| Method | Returns | Writes a file |
|---|---|---|
| `save($path)` | `Result` (path, width, height, sha256, …) | yes (`.png`, `.svg` or `.pdf`; folders are created) |
| `toPng()`, `toSvg()`, `toPdf()`, `toString($format)` | raw bytes | no |
| `toBase64($format = null)` | base64 string | no |
| `toDataUri($format = null)` | `data:image/png;base64,...` | no |
| `generateInMemory($format = null)` | `Result` with `base64()`, `dataUri()`, `contents()` | no |
| `toResponse($format = null, $downloadName = null)` | Laravel `Response` | no |
| `validate()` / `isValid()` | `Result` / `bool` | no (checks only) |

```php
// Store and remember the path
$result = QrCode::make($url)->save(storage_path("app/public/certs/{$cert->id}.png"));
$result->path();      // the file
$result->width();     // pixels
$result->qrVersion(); // 1-40
$result->sha256();

// Inline in Blade, no file
<img src="{{ \Eduplus\Qr\BarCode::make($student->roll)->showText()->toDataUri() }}" alt="">

// Controller response
return QrCode::make($url)->toResponse();                    // inline PNG
return QrCode::make($url)->toResponse('pdf', 'qr.pdf');     // download
```

Always use absolute paths with `save()` (for example `storage_path(...)`). During web requests the working directory is `public/`.

## Options

Every method returns the code, so you can chain them:

```php
QrCode::make($url)
    ->ecc('H')                     // L, M, Q, H; raised automatically when it fits
    ->module(6)                    // pixels per module
    ->style('rounded')             // square, dots, rounded
    ->logo(storage_path('app/logo.png'), 0.22)   // always verified
    ->color('#0d47a1', '#ffffff')
    ->label('Scan to verify')
    ->save($path);
```

| Method | Purpose |
|---|---|
| `type($t)` | Code type |
| `ecc($level)` | QR `L/M/Q/H`, PDF417 `0-8`, Aztec `5-95` (%) |
| `module($px)` / `xdim($mm)` / `dpi($dpi)` | Module size in pixels, or in millimetres for print |
| `size($w, $h = null)`, `width($px)`, `height($px)` | Exact image size; the module size is fitted to it |
| `quiet($modules)`, `margin($px)` | Quiet zone (0 removes it) and extra padding |
| `bare()` | No quiet zone, no padding, transparent background, no text |
| `showText()`, `hideText()`, `label($text)`, `fontSize($px)` | Human-readable text or a custom caption |
| `color($fg, $bg)`, `foreground()`, `background()`, `transparent()` | Colours (`#RRGGBB`, `#RRGGBBAA`, a name, `transparent`) |
| `rotate($deg)` | 0 / 90 / 180 / 270 |
| `format($f)` | `png`, `svg`, `pdf` |
| `qrVersion($v)`, `qrMinVersion($v)`, `mask($m)`, `eci()`, `exactEcc()` | QR controls (a fixed version gives every code the same size) |
| `style($s)`, `logo($path, $size)`, `logoSize($f)` | QR styling |
| `checksum()`, `fullAscii()` | Code 39 / ITF options |
| `verify()` | Decode the result and fail if it does not read back |
| `force()` | Allow low-contrast colours |
| `option($name, $value)`, `options([...])` | Any binary option by name |

## Content formats

These build correctly escaped content that phones understand:

```php
QrCode::wifi('Campus', 'secret');                       // auth: wpa (default), wpa3, wep, nopass
QrCode::vcard(['name' => 'Rahim Uddin', 'org' => 'Eduplus', 'phone' => '+8801711000000', 'email' => 'r@x.bd']);
QrCode::email('office@eduplus-bd.com', 'Admission query');
QrCode::sms('+8801711000000', 'Roll 42');
QrCode::tel('+8801711000000');
QrCode::geo(23.8103, 90.4125);
QrCode::event(['summary' => 'Admission test', 'start' => '2026-01-10 10:00', 'end' => '2026-01-10 12:00']);
QrCode::emv([                                           // EMVCo payment (Bangla QR and similar)
    'merchant_name' => 'EDUPLUS SCHOOL', 'merchant_city' => 'DHAKA',
    'guid' => '<from your bank>', 'merchant_id' => '<from your bank>', 'amount' => '1500',
]);
QrCode::payload('mecard', ['name' => 'Rahim', 'phone' => '+880...']);   // any kind
```

## Batches (bulk generation)

A batch generates every code in **one process on all CPU cores**. That is much faster than calling `save()` in a loop. You give each row **your own id** (for example a student id), and the results come back **keyed by that id, in the order you added them**.

```php
$batch = BarCode::batch()                  // defaults for every row
    ->showText()
    ->module(3);

foreach ($students as $s) {
    $batch->add($s->id, $s->roll);         // id, text, [per-row options]
}
// or: $batch->addMany($students->pluck('roll', 'id')->all());
```

Then choose where the images go:

### 1. In memory: show them directly, nothing is saved

```php
$results = $batch->toBase64();

foreach ($students as $s) {
    $item = $results[$s->id];
    echo $item->ok()
        ? '<img src="' . $item->dataUri() . '">'
        : 'Error: ' . $item->error();
}
```

### 2. Files

```php
$results = $batch->saveTo(storage_path('app/public/cards/{id}.png'));

foreach ($results as $id => $item) {
    if ($item->ok()) {
        Student::whereKey($id)->update(['barcode_path' => $item->path()]);
    }
}
```

Path placeholders: `{id}` (your id, made file-name safe), `{t}` (the text), `{n}` (row number), `{type}`, `{shard}` (2 hex characters, to spread files over 256 folders). Add `->skipExisting()` to resume an interrupted run.

### 3. One ZIP

```php
$results = $batch->toZip(storage_path('app/admit-cards.zip'), '{id}.png');
return response()->download($results->output());
```

### 4. Printable label sheets (PDF)

```php
$results = BarCode::batch()->showText()
    ->addMany($books->pluck('accession_no', 'id')->all())
    ->toSheet(storage_path('app/labels.pdf'), 'a4', '3x8');   // page size, columns x rows
$results->pages();
```

### 5. Validate only

```php
$check = BarCode::batch('ean13')->addMany($products->pluck('gtin', 'id')->all())->validate();
$check->errors();   // ['17' => 'ean13: invalid check digit: got 8, expected 7', ...]
```

### Mixed rows, content formats and series

```php
QrCode::batch()                                  // rows can override the type
    ->add('cert-1', $url, ['style' => 'rounded'])
    ->add('roll-1', 'STU-1', ['type' => 'code128', 'text' => true])
    ->addPayload('lab-wifi', 'wifi', ['ssid' => 'Lab-1', 'password' => 'x'])
    ->addCode('call', QrCode::tel('+8801711000000')->style('dots'))
    ->toBase64();

// Numbered series, no rows needed (results keyed by the generated text)
BarCode::sequence('LIB-{00001..02400}')->showText()
    ->toSheet(storage_path('app/library-labels.pdf'));
```

### Working with results

```php
$results->all();            // [id => BatchItem] in your order
$results->successful();     // only the ok items
$results->failed();         // only the failed items
$results->errors();         // [id => message]
$results->allSuccessful();  // bool
$results->summary();        // ['total' => 100, 'ok' => 99, 'failed' => 1, 'skipped' => 0]
$results->throw();          // throw a GenerationException if anything failed

$item = $results['stu-7'];
$item->ok(); $item->failed(); $item->skipped(); $item->error();
$item->path(); $item->dataUri(); $item->base64(); $item->contents();
$item->result()->width();  $item->result()->content();
```

One bad row never stops a batch; it appears in `failed()`. Other batch settings: `->workers(4)`, `->manifest(storage_path('app/batch.csv'))`.

## Errors

Generation problems throw `Eduplus\Qr\Exceptions\GenerationException` with the binary's own message:

```php
use Eduplus\Qr\Exceptions\GenerationException;

try {
    BarCode::make($input, 'ean13')->save($path);
} catch (GenerationException $e) {
    $e->getMessage();       // "ean13: invalid check digit: got 8, expected 7"
    $e->isInvalidInput();   // bad data (show it to the user)
    $e->isIoError();        // file could not be written
    $e->isVerifyFailure();  // image did not decode back
    $e->isTimeout();
}
```

`Eduplus\Qr\Exceptions\BinaryNotFoundException` means the binary is missing or not executable. Both exceptions extend `Eduplus\Qr\Exceptions\EduplusException`.

## Configuration

The settings live under the `qr` key of `config/eduplus.php`, a file shared with other Eduplus packages:

```php
return [
    'pgw' => [ /* another Eduplus package */ ],

    'qr' => [
        'binary' => env('EDUPLUS_QR_BINARY'),
        'timeout' => env('EDUPLUS_QR_TIMEOUT', 120),
        'qr_defaults' => ['style' => 'rounded'],
        // ...
    ],
];
```

Any key you leave out falls back to its default, so a partial `qr` section is fine. Read values with `config('eduplus.qr.timeout')`.

| Key | `.env` | Default | |
|---|---|---|---|
| `binary` | `EDUPLUS_QR_BINARY` | auto-detect | Full path to the binary |
| `binary_dir` | `EDUPLUS_QR_BINARY_DIR` | | Extra folder to search |
| `timeout` | `EDUPLUS_QR_TIMEOUT` | 120 | Seconds per call (0 = no limit) |
| `base_dir` | `EDUPLUS_QR_BASE_DIR` | | Refuse to write outside this folder |
| `workers` | `EDUPLUS_QR_WORKERS` | CPU cores | Batch parallelism |
| `defaults` | | `[]` | Options for every code, e.g. `['fg' => '#0d47a1']` |
| `qr_defaults` | | `[]` | Only QR codes, e.g. `['style' => 'rounded', 'logo' => storage_path('app/logo.png')]` |
| `barcode_defaults` | | `[]` | Only barcodes, e.g. `['module' => 3, 'text' => true]` |
| `log_warnings` | | `true` | Log binary warnings (e.g. a large logo) |

`QrCode` and `BarCode` share this configuration. To change it at runtime, call `QrCode::configure([...])`.

## Without Laravel

Everything works in plain PHP. Configure once, then use `QrCode` and `BarCode` the same way:

```php
require 'vendor/autoload.php';

use Eduplus\Qr\QrCode;
use Eduplus\Qr\BarCode;

QrCode::configure([
    'binary' => '/usr/local/bin/EduplusQR-linux-amd64',   // omit to auto-detect
    'timeout' => 60,
    'qr_defaults' => ['style' => 'rounded'],
]);

QrCode::make('https://eduplus-bd.com')->save('/var/www/qr.png');
$results = BarCode::batch()->addMany(['a' => 'A-1', 'b' => 'B-2'])->toBase64();
```

If you need two independent configurations in one app, `new \Eduplus\Qr\Client($binary, $config)` gives you a separate instance with the same methods (`->qr()`, `->barcode()`, `->batch()`, …).

## Upgrading from 1.0.1

1.0.2 is a full rewrite for the single EduplusQR binary, which now also makes barcodes. The separate EduplusBarcode binary is no longer used.

| 1.0.1 | 1.0.2 |
|---|---|
| Namespace `Eduplus\…` | `Eduplus\Qr\…` (e.g. `Eduplus\Qr\QrCode`, `Eduplus\Qr\EduplusServiceProvider`) |
| `config/eduplusqr.php` with `qr_binary_path`, `barcode_binary_path` | `config/eduplus.php` → `'qr' => ['binary' => ...]` (run `php artisan eduplus:qr-install`) |
| `EDUPLUS_QR_BINARY_PATH`, `EDUPLUS_BARCODE_BINARY_PATH` | `EDUPLUS_QR_BINARY` (one binary, auto-detected) |
| `QrCode::create()->text($t)->output($p)->generate()` | `QrCode::make($t)->save($p)` |
| `->size(256)` | `->size(256)` (exact image size) or `->module(4)` |
| `->errorCorrection('H')` | `->ecc('H')` |
| `->margin(0)` | `->quiet(0)`; `->margin($px)` is now extra padding |
| `BarCode::create()->type('ean13')->width(300)->height(100)` | `BarCode::make($t, 'ean13')->size(300, 100)` |
| `generateAndReturn()` / `generateBase64()` (wrote a temp file) | `toPng()` / `toBase64()` (no file) |
| `$instance->errors` array, `false` on failure | `GenerationException` with the exact message |
| `QrCode::quick(...)`, `BarCode::quick(...)` | `QrCode::make(...)->save(...)` |
| `new QrCode()` / `QrCode::create()` | `QrCode::make($text)` (static entry points only) |

After upgrading, delete the old `config/eduplusqr.php`. If Laravel reports that `Eduplus\EduplusServiceProvider` can't be found, clear its cached manifests with `rm bootstrap/cache/packages.php bootstrap/cache/services.php` and run `php artisan package:discover`.

## Testing

```bash
composer install
EDUPLUS_QR_BINARY=/path/to/EduplusQR composer test
```

## License

MIT. See [LICENSE](LICENSE).

## Author

**Md Mojahedul Islam**: dev.mojahedul@gmail.com · https://md-mojahed.github.io

Made With (❤️ Love) By: md-mojahed.github.io
