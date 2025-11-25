# Eduplus QR - Laravel Package

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

Laravel package for QR Code and Barcode generation using EduplusQR and EduplusBarcode with configurable binary paths. This approach allows you to use a single set of binaries across all your Laravel projects without bundling heavy binary files in each project.

------------------------------------------------------------
## Features
------------------------------------------------------------

- **Configurable Binary Paths**: Set binary locations once, use across all projects
- **Lightweight Package**: No bundled binaries, reducing package size
- **Fluent API**: Clean, chainable method syntax
- **Laravel Integration**: Service provider and config publishing
- **QR Code Generation**: Full EduplusQR functionality
- **Barcode Generation**: Support for Code128, Code39, EAN-13
- **Environment Configuration**: Set paths via .env file

------------------------------------------------------------
## Installation
------------------------------------------------------------

Install via Composer:
```bash
composer require eduplus/qr
```

Publish the configuration file:
```bash
php artisan vendor:publish --tag=eduplusqr-config
```

This will create `config/eduplusqr.php` in your Laravel project.

------------------------------------------------------------
## Binary Setup
------------------------------------------------------------

### 1. Download Binaries

Download the appropriate binaries for your system from EduplusQR and EduplusBarcode packages, or place them in a shared location:

```bash
# Example: Create a shared binary directory
sudo mkdir -p /usr/local/bin/eduplus

# Copy binaries (adjust paths as needed)
sudo cp EduplusQR-linux-amd64 /usr/local/bin/eduplus/
sudo cp EduplusBarcode-linux-amd64 /usr/local/bin/eduplus/

# Make them executable
sudo chmod +x /usr/local/bin/eduplus/EduplusQR-linux-amd64
sudo chmod +x /usr/local/bin/eduplus/EduplusBarcode-linux-amd64
```

### 2. Configure Binary Paths

Option A: Via `.env` file (recommended):
```env
EDUPLUS_QR_BINARY_PATH=/usr/local/bin/eduplus/EduplusQR-linux-amd64
EDUPLUS_BARCODE_BINARY_PATH=/usr/local/bin/eduplus/EduplusBarcode-linux-amd64
```

Option B: Via `config/eduplusqr.php`:
```php
return [
    'qr_binary_path' => '/usr/local/bin/eduplus/EduplusQR-linux-amd64',
    'barcode_binary_path' => '/usr/local/bin/eduplus/EduplusBarcode-linux-amd64',
];
```

### Platform-Specific Binary Names
- Linux x86_64: `EduplusQR-linux-amd64`, `EduplusBarcode-linux-amd64`
- Linux ARM64: `EduplusQR-linux-arm64`, `EduplusBarcode-linux-arm64`
- macOS Intel: `EduplusQR-darwin-amd64`, `EduplusBarcode-darwin-amd64`
- macOS Apple Silicon: `EduplusQR-darwin-arm64`, `EduplusBarcode-darwin-arm64`
- Windows: `EduplusQR-windows-amd64.exe`, `EduplusBarcode-windows-amd64.exe`

------------------------------------------------------------
## Usage
------------------------------------------------------------

### QR Code Generation

#### Basic Usage
```php
use Eduplus\QrCode;

// Simple QR code
QrCode::quick("Hello World", public_path('qr.png'));

// Fluent API
QrCode::create()
    ->text("https://eduplus-bd.com")
    ->output(public_path('qr.png'))
    ->size(512)
    ->errorCorrection('H')
    ->generate();
```

#### JSON Data QR Code
```php
$data = json_encode([
    'user_id' => 12345,
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

QrCode::create()
    ->text($data)
    ->output(storage_path('app/public/user_qr.png'))
    ->size(512)
    ->errorCorrection('H')
    ->generate();
```

#### Get Base64 Encoded QR Code
```php
$base64 = QrCode::create()
    ->text("Base64 QR Code")
    ->output(storage_path('app/temp_qr.png'))
    ->generateBase64();

// Use in Blade template
<img src="data:image/png;base64,{{ $base64 }}" />
```

#### Controller Example
```php
namespace App\Http\Controllers;

use Eduplus\QrCode;
use Illuminate\Http\Request;

class QrController extends Controller
{
    public function generate(Request $request)
    {
        $qr = QrCode::create()
            ->text($request->input('text'))
            ->output(storage_path('app/public/qr_' . time() . '.png'))
            ->size(400)
            ->generate();

        if ($qr) {
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 500);
    }

    public function generateInline(Request $request)
    {
        $binary = QrCode::create()
            ->text($request->input('text'))
            ->output(storage_path('app/temp_qr.png'))
            ->size(400)
            ->generateAndReturn();

        return response($binary)
            ->header('Content-Type', 'image/png');
    }
}
```

### Barcode Generation

#### Basic Usage
```php
use Eduplus\BarCode;

// Simple barcode
BarCode::quick("ABC123", public_path('barcode.png'));

// Fluent API
BarCode::create()
    ->text("PRODUCT-001")
    ->output(public_path('barcode.png'))
    ->type('code39')
    ->width(400)
    ->height(120)
    ->generate();
```

#### Different Barcode Types
```php
// Code128 (default)
BarCode::create()
    ->text("ABC-123-XYZ")
    ->output(storage_path('app/public/code128.png'))
    ->type('code128')
    ->generate();

// Code39
BarCode::create()
    ->text("PRODUCT-001")
    ->output(storage_path('app/public/code39.png'))
    ->type('code39')
    ->generate();

// EAN-13
BarCode::create()
    ->text("1234567890128")
    ->output(storage_path('app/public/ean13.png'))
    ->type('ean13')
    ->generate();
```

#### Get Base64 Encoded Barcode
```php
$base64 = BarCode::create()
    ->text("SKU-12345")
    ->output(storage_path('app/temp_barcode.png'))
    ->generateBase64();

// Use in Blade template
<img src="data:image/png;base64,{{ $base64 }}" />
```

------------------------------------------------------------
## API Reference
------------------------------------------------------------

### QrCode Methods

- `create()` - Create new QrCode instance
- `text($text)` - Set text or JSON data to encode
- `output($path)` - Set output file path
- `size($pixels)` - Set QR code size (default: 256)
- `errorCorrection($level)` - Set error correction: L, M, Q, H (default: M)
- `margin($pixels)` - Set border margin (default: 0)
- `generate()` - Generate and save QR code
- `generateAndReturn()` - Generate and return binary data
- `generateBase64()` - Generate and return base64 string
- `quick($text, $output, $size = 256)` - Quick generation

### BarCode Methods

- `create()` - Create new BarCode instance
- `text($text)` - Set text to encode
- `output($path)` - Set output file path
- `type($type)` - Set barcode type: code128, code39, ean13 (default: code128)
- `width($pixels)` - Set barcode width (default: 300)
- `height($pixels)` - Set barcode height (default: 100)
- `generate()` - Generate and save barcode
- `generateAndReturn()` - Generate and return binary data
- `generateBase64()` - Generate and return base64 string
- `quick($text, $output, $type = 'code128', $width = 300, $height = 100)` - Quick generation

### Error Handling

Both classes expose a public `$errors` array:
```php
$qr = QrCode::create()
    ->text("Test")
    ->output("qr.png")
    ->generate();

if (!$qr) {
    $instance = QrCode::create();
    print_r($instance->errors);
}
```

------------------------------------------------------------
## Benefits of This Approach
------------------------------------------------------------

1. **Reduced Package Size**: No bundled binaries in each project
2. **Single Binary Location**: One set of binaries for all Laravel projects
3. **Easy Updates**: Update binaries once, all projects benefit
4. **Flexible Deployment**: Configure different paths per environment
5. **Server Optimization**: Shared binaries reduce disk usage

------------------------------------------------------------
## Requirements
------------------------------------------------------------

- PHP 7.4 or higher
- Laravel 8.x, 9.x, 10.x, or 11.x
- EduplusQR and EduplusBarcode binaries installed on the system

------------------------------------------------------------
## Author
------------------------------------------------------------

**Md Mojahedul Islam**
- Email: dev.mojahedul@gmail.com
- Website: https://md-mojahed.github.io

------------------------------------------------------------
## License
------------------------------------------------------------

MIT License - see LICENSE file for details
