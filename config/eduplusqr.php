<?php

return [
    /*
    |--------------------------------------------------------------------------
    | QR Code Binary Path
    |--------------------------------------------------------------------------
    |
    | Path to the EduplusQR binary executable. This should point to the
    | appropriate binary for your system (linux-amd64, darwin-arm64, etc.)
    |
    | Example: '/usr/local/bin/EduplusQR-linux-amd64'
    |          '/home/user/binaries/EduplusQR-darwin-arm64'
    |
    */
    'qr_binary_path' => env('EDUPLUS_QR_BINARY_PATH', '/usr/local/bin/EduplusQR-linux-amd64'),

    /*
    |--------------------------------------------------------------------------
    | Barcode Binary Path
    |--------------------------------------------------------------------------
    |
    | Path to the EduplusBarcode binary executable. This should point to the
    | appropriate binary for your system (linux-amd64, darwin-arm64, etc.)
    |
    | Example: '/usr/local/bin/EduplusBarcode-linux-amd64'
    |          '/home/user/binaries/EduplusBarcode-darwin-arm64'
    |
    */
    'barcode_binary_path' => env('EDUPLUS_BARCODE_BINARY_PATH', '/usr/local/bin/EduplusBarcode-linux-amd64'),
];
