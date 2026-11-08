<?php

/*
| Shared configuration for Eduplus packages. Each package keeps its
| settings under its own key (qr, pgw, ...).
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Eduplus QR (eduplus/qr)
    |--------------------------------------------------------------------------
    |
    | binary      Full path to the EduplusQR binary. Empty = auto-detect the
    |             file named for this machine (e.g. "EduplusQR-linux-amd64")
    |             or plain "EduplusQR" in binary_dir, base_path('bin'),
    |             /usr/local/bin, /usr/bin, /opt/eduplus and PATH.
    |             Check with: php artisan eduplus:qr-check
    | timeout     Maximum seconds per call (0 = no limit).
    | base_dir    When set, files are never written outside this folder.
    | workers     Parallel batch workers (empty = number of CPU cores).
    | defaults    Options for every code, e.g. ['fg' => '#0d47a1'].
    | qr_defaults / barcode_defaults
    |             Options only for QR codes / only for barcodes, e.g.
    |             ['style' => 'rounded', 'logo' => storage_path('app/logo.png')]
    |             ['module' => 3, 'text' => true]
    | log_warnings  Write binary warnings (e.g. a large logo) to the log.
    |
    */

    'qr' => [
        'binary' => env('EDUPLUS_QR_BINARY'),
        'binary_dir' => env('EDUPLUS_QR_BINARY_DIR'),
        'timeout' => env('EDUPLUS_QR_TIMEOUT', 120),
        'base_dir' => env('EDUPLUS_QR_BASE_DIR'),
        'workers' => env('EDUPLUS_QR_WORKERS'),
        'defaults' => [],
        'qr_defaults' => [],
        'barcode_defaults' => [],
        'log_warnings' => true,
    ],

];
