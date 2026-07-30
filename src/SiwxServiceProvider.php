<?php

namespace LexWebDev\Siwx;

use Illuminate\Support\ServiceProvider;

class SiwxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/siwx.php', 'siwx');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/siwx.php' => config_path('siwx.php'),
            ], 'siwx-config');
        }
    }
}
