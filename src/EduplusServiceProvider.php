<?php

namespace Eduplus;

use Illuminate\Support\ServiceProvider;

class EduplusServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/eduplusqr.php', 'eduplusqr'
        );
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/eduplusqr.php' => config_path('eduplusqr.php'),
        ], 'eduplusqr-config');
    }
}
