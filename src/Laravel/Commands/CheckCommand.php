<?php

namespace Eduplus\Qr\Laravel\Commands;

use Eduplus\Qr\Client;
use Illuminate\Console\Command;

/**
 * php artisan eduplus:qr-check
 *
 * Confirms the binary is found, runs, and can generate a verified code.
 */
class CheckCommand extends Command
{
    protected $signature = 'eduplus:qr-check';

    protected $description = 'Check that the EduplusQR binary (eduplus/qr) is installed and working';

    public function handle(Client $qr): int
    {
        try {
            $this->line('Expected binary name: ' . Client::platformBinaryName());
            $this->line('Binary:  ' . $qr->binary());
            $this->line('Version: ' . $qr->version());
            $result = $qr->qr('https://eduplus-bd.com')->verify()->generateInMemory();
            $this->line(sprintf('Test QR: %dx%d px, version %d, ECC %s, verified', $result->width(), $result->height(), (int) $result->qrVersion(), (string) $result->ecc()));
            $this->info('EduplusQR is working.');
            return 0;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}
