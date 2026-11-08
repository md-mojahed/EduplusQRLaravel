<?php

namespace Eduplus\Qr;

use Eduplus\Qr\Laravel\Commands\CheckCommand;
use Eduplus\Qr\Laravel\Commands\InstallCommand;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel integration for eduplus/qr.
 *
 * Settings live under the "qr" key of the shared config/eduplus.php (other
 * Eduplus packages use their own keys in the same file). Missing settings
 * fall back to the package defaults, so a partial "qr" section is fine.
 */
class EduplusServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeQrConfig();

        $this->app->singleton(Client::class, function ($app) {
            $config = (array) $app['config']->get('eduplus.qr', []);
            $client = Client::fromConfig($config);
            if (!empty($config['log_warnings']) && $app->bound('log')) {
                $client->onWarning(function (string $warning) use ($app) {
                    $app['log']->warning('EduplusQR: ' . $warning);
                });
            }
            return $client;
        });
        $this->app->alias(Client::class, 'eduplus.qr');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, CheckCommand::class]);
        }
    }

    /**
     * Fill in missing eduplus.qr settings from the package defaults without
     * touching the other keys of config/eduplus.php.
     */
    private function mergeQrConfig(): void
    {
        $cached = interface_exists('Illuminate\Contracts\Foundation\CachesConfiguration')
            && $this->app instanceof \Illuminate\Contracts\Foundation\CachesConfiguration
            && $this->app->configurationIsCached();
        if ($cached) {
            return;
        }
        $defaults = require __DIR__ . '/../config/eduplus.php';
        $config = $this->app->make('config');
        $config->set('eduplus.qr', array_replace((array) $defaults['qr'], (array) $config->get('eduplus.qr', [])));
    }
}
