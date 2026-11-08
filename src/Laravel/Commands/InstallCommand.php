<?php

namespace Eduplus\Qr\Laravel\Commands;

use Eduplus\Qr\Client;
use Eduplus\Qr\Laravel\ConfigInstaller;
use Illuminate\Console\Command;

/**
 * php artisan eduplus:qr-install
 *
 * Adds the "qr" section to config/eduplus.php: creates the file if it does
 * not exist, or appends the section to the existing file (shared with other
 * Eduplus packages) without changing anything else.
 */
class InstallCommand extends Command
{
    protected $signature = 'eduplus:qr-install';

    protected $description = 'Add the eduplus/qr settings ("qr" section) to config/eduplus.php';

    public function handle(): int
    {
        $path = $this->laravel->configPath('eduplus.php');
        try {
            $status = ConfigInstaller::install($path);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }
        switch ($status) {
            case ConfigInstaller::CREATED:
                $this->info("Created {$path} with the 'qr' section.");
                break;
            case ConfigInstaller::APPENDED:
                $this->info("Added the 'qr' section to {$path} (other settings were left unchanged).");
                break;
            default:
                $this->line("{$path} already has a 'qr' section; nothing changed.");
        }
        $this->line('Binary name for this machine: ' . Client::platformBinaryName());
        $this->line('Set EDUPLUS_QR_BINARY in .env if it is not in a standard location, then run: php artisan eduplus:qr-check');
        return 0;
    }
}
